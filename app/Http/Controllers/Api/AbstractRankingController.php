<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AbstractResource;
use App\Models\AbstractSubmission;
use App\Services\AbstractRankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AbstractRankingController extends Controller
{
    public function __construct(private readonly AbstractRankingService $rankingService)
    {
    }

    /**
     * GET /api/abstracts/rankings
     * Preview only — computes the classification without writing anything.
     * Safe to call as often as you like (e.g. every time the page loads)
     * to see how the buckets look as more reviews come in.
     */
    public function preview(): JsonResponse
    {
        $classification = $this->rankingService->classify();

        return response()->json([
            'success' => true,
            'message' => 'Ranking preview computed.',
            'data' => $this->formatClassification($classification),
        ]);
    }

    /**
     * POST /api/abstracts/rankings/process
     * Persists the classification: accepts + tags every abstract in
     * top30 / sub_theme_top5 / posters, tags (but does not decide)
     * everyone in pending, and — unless sendEmails=false — emails
     * everyone newly accepted.
     *
     * Body: { sendEmails?: bool (default true), resend?: bool (default false) }
     * resend=true will re-send to people already notified; otherwise
     * they're skipped so re-running this is safe.
     */
    public function process(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sendEmails' => 'sometimes|boolean',
            'resend' => 'sometimes|boolean',
        ]);

        $classification = $this->rankingService->classify();
        $result = $this->rankingService->apply(
            $classification,
            $validated['sendEmails'] ?? true,
            $validated['resend'] ?? false
        );

        return response()->json([
            'success' => true,
            'message' => "{$result['accepted']} accepted, {$result['pending']} held as pending, "
                . "{$result['notified']} emails sent, {$result['skipped']} already-notified emails skipped.",
            'data' => $result,
        ]);
    }

    /**
     * GET /api/abstracts/rankings/notifications?notified=yes|no
     * Who has and hasn't received a decision email, among abstracts
     * that have been through classification.
     */
    public function notificationStatus(Request $request): JsonResponse
    {
        $query = AbstractSubmission::query()
            ->with(['authors'])
            ->whereNotNull('classification_group');

        if ($request->query('notified') === 'yes') {
            $query->whereNotNull('decision_notified_at');
        } elseif ($request->query('notified') === 'no') {
            $query->whereNull('decision_notified_at');
        }

        $abstracts = $query->orderByDesc('average_score')->get();

        return response()->json([
            'success' => true,
            'message' => 'Notification status retrieved.',
            'data' => AbstractResource::collection($abstracts),
        ]);
    }

    /**
     * POST /api/abstracts/{abstract}/notify
     * Send (or resend) a single decision email, based on whatever
     * status/presentation_type/rank fields the abstract currently has.
     */
    public function notify(AbstractSubmission $abstract): JsonResponse
    {
        if (! in_array($abstract->status, ['accepted', 'rejected'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This abstract has not been decided yet — classify it first.',
            ], 422);
        }

        $this->rankingService->sendNotification(
            $abstract,
            $abstract->status,
            $abstract->presentation_type,
            $abstract->overall_rank,
            $abstract->sub_theme,
            $abstract->sub_theme_rank
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification sent.',
            'data' => new AbstractResource($abstract->fresh()),
        ]);
    }

    /**
     * PATCH /api/abstracts/{abstract}/classify
     * Manually decide a "pending" (sub-2.5) abstract that the automatic
     * pass held aside — accept it as oral/poster, or reject it, and
     * optionally email the decision right away.
     *
     * Body: { status: 'accepted'|'rejected', presentationType?: 'oral'|'poster', sendEmail?: bool }
     */
    public function classify(Request $request, AbstractSubmission $abstract): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'rejected'])],
            'presentationType' => ['required_if:status,accepted', Rule::in(['oral', 'poster'])],
            'sendEmail' => 'sometimes|boolean',
        ]);

        $abstract->forceFill([
            'status' => $validated['status'],
            'presentation_type' => $validated['status'] === 'accepted' ? $validated['presentationType'] : null,
            'classification_group' => 'manual',
        ])->save();

        if ($validated['sendEmail'] ?? true) {
            $this->rankingService->sendNotification(
                $abstract,
                $validated['status'],
                $abstract->presentation_type,
                $abstract->overall_rank,
                $abstract->sub_theme,
                $abstract->sub_theme_rank
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Abstract manually classified as {$validated['status']}.",
            'data' => new AbstractResource($abstract->fresh()),
        ]);
    }

    private function formatClassification(array $classification): array
    {
        return [
            'top30' => $classification['top30']->map(fn ($row) => [
                'rank' => $row['rank'],
                'notified' => (bool) $row['abstract']->decision_notified_at,
                'abstract' => new AbstractResource($row['abstract']),
            ])->values(),
            'subThemeTop5' => $classification['sub_theme_top5']->map(fn ($row) => [
                'subTheme' => $row['sub_theme'],
                'subThemeRank' => $row['sub_theme_rank'],
                'notified' => (bool) $row['abstract']->decision_notified_at,
                'abstract' => new AbstractResource($row['abstract']),
            ])->values(),
            'posters' => $classification['posters']->map(fn ($abstract) => [
                'notified' => (bool) $abstract->decision_notified_at,
                'abstract' => new AbstractResource($abstract),
            ])->values(),
            'pending' => $classification['pending']->map(fn ($abstract) => [
                'notified' => (bool) $abstract->decision_notified_at,
                'abstract' => new AbstractResource($abstract),
            ])->values(),
            'counts' => [
                'top30' => $classification['top30']->count(),
                'subThemeTop5' => $classification['sub_theme_top5']->count(),
                'posters' => $classification['posters']->count(),
                'pending' => $classification['pending']->count(),
            ],
        ];
    }
}