<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AbstractResource;
use App\Models\AbstractSubmission;
use App\Services\AbstractRankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


use App\Models\ReviewAssignment;

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
            // presentation_type is NOT NULL in the schema (it's set at
            // submission time from the author's requested format), so a
            // rejection leaves it as-is instead of nulling it out — only
            // an acceptance overrides it with the committee's decision.
            'presentation_type' => $validated['status'] === 'accepted'
                ? $validated['presentationType']
                : $abstract->presentation_type,
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

    /**
     * POST /api/abstracts/notifications/custom
     * Send the same free-text subject/message either to an entire named
     * category, or to a specific set of hand-picked abstracts. Exactly one
     * of `category` / `abstractIds` must be supplied. This never changes
     * status/presentation_type and never touches decision_notified_at —
     * it's an ad-hoc message, not a decision.
     *
     * Body:
     *   { category: 'oral'|'poster'|'pending'|'rejected'|'all', subject, message }
     *   OR
     *   { abstractIds: number[], subject, message }
     */
    public function sendCustom(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', Rule::in(['oral', 'poster', 'pending', 'rejected', 'all'])],
            'abstractIds' => ['nullable', 'array', 'min:1'],
            'abstractIds.*' => ['integer', 'exists:abstracts,id'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
        ]);

        $hasCategory = ! empty($validated['category']);
        $hasIds = ! empty($validated['abstractIds']);

        if ($hasCategory === $hasIds) {
            return response()->json([
                'success' => false,
                'message' => 'Provide exactly one of "category" or "abstractIds".',
            ], 422);
        }

        $abstracts = $hasIds
            ? AbstractSubmission::query()->with('authors')->whereIn('id', $validated['abstractIds'])->get()
            : $this->rankingService->abstractsForCategory($validated['category']);

        if ($abstracts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No matching abstracts to send to.',
            ], 422);
        }

        $result = $this->rankingService->sendCustomToAbstracts(
            $abstracts,
            $validated['subject'],
            $validated['message']
        );

        $skippedNote = $result['skipped_no_email'] > 0
            ? ", {$result['skipped_no_email']} skipped (no author email on file)"
            : '';

        return response()->json([
            'success' => true,
            'message' => "Sent to {$result['sent']} recipient(s){$skippedNote}.",
            'data' => $result,
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






/**
 * GET /api/reviewer/rankings
 *
 * Returns rankings for abstracts the authenticated reviewer has been
 * assigned. Two views are returned:
 *   - overall: all of their reviewed abstracts ranked by average_score
 *   - sub_themes: the same abstracts grouped by sub-theme
 */
public function reviewerRankings(Request $request): JsonResponse
{
    $user = $request->user();
    if (! $user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
        ], 401);
    }

    // The reviewer record linked to this user. Adjust if your Reviewer
    // model is linked differently (e.g. by email).
    $reviewer = \App\Models\Reviewer::where('user_id', $user->id)->first()
        ?? \App\Models\Reviewer::where('email', $user->email)->first();

    if (! $reviewer) {
        return response()->json([
            'success' => true,
            'message' => 'No reviewer profile linked to this account.',
            'data' => [
                'overall' => [],
                'sub_themes' => (object) [],
            ],
        ]);
    }

    // The abstract ids this reviewer has ever been assigned to.
    $abstractIds = ReviewAssignment::where('reviewer_id', $reviewer->id)
        ->pluck('abstract_id')
        ->unique()
        ->values()
        ->all();

    if (empty($abstractIds)) {
        return response()->json([
            'success' => true,
            'message' => 'No abstracts assigned.',
            'data' => [
                'overall' => [],
                'sub_themes' => (object) [],
            ],
        ]);
    }

    // Only current versions of those abstracts.
    $abstracts = AbstractSubmission::query()
        ->whereIn('id', $abstractIds)
        ->where('is_current', true)
        ->with(['authors', 'assignments.review'])
        ->get();

    // Build the review_count + average_score per abstract.
    $rows = $abstracts->map(function (AbstractSubmission $a) {
        $submittedReviews = $a->assignments
            ->pluck('review')
            ->filter();

        $average = $submittedReviews->isNotEmpty()
            ? round($submittedReviews->pluck('average')->avg(), 2)
            : null;

        return [
            'id' => $a->id,
            'reference' => $a->reference,
            'title' => $a->title,
            'sub_theme' => $a->sub_theme,
            'average_score' => $average ?? 0,
            'status' => $a->status,
            'presentation_type' => $a->presentation_type,
            'review_count' => $submittedReviews->count(),
            'authors' => $a->authors->map(fn ($au) => [
                'id' => $au->id,
                'name' => $au->name,
                'email' => $au->email,
                'affiliation' => $au->affiliation,
                'is_corresponding' => (bool) $au->is_corresponding,
            ])->values(),
        ];
    })
    ->sortByDesc('average_score')
    ->values();

    // Overall ranking
    $overall = $rows->map(function ($row, $index) {
        return [
            'rank' => $index + 1,
            'abstract' => [
                'id' => $row['id'],
                'reference' => $row['reference'],
                'title' => $row['title'],
                'sub_theme' => $row['sub_theme'],
                'average_score' => $row['average_score'],
                'status' => $row['status'],
                'presentation_type' => $row['presentation_type'],
                'authors' => $row['authors'],
            ],
            'average_score' => $row['average_score'],
            'review_count' => $row['review_count'],
            'sub_theme' => $row['sub_theme'],
        ];
    })->all();

    // Group by sub-theme, each group ranked independently
    $subThemes = $rows
        ->groupBy('sub_theme')
        ->map(function ($group) {
            return $group
                ->values()
                ->map(function ($row, $index) {
                    return [
                        'rank' => $index + 1,
                        'abstract' => [
                            'id' => $row['id'],
                            'reference' => $row['reference'],
                            'title' => $row['title'],
                            'sub_theme' => $row['sub_theme'],
                            'average_score' => $row['average_score'],
                            'status' => $row['status'],
                            'presentation_type' => $row['presentation_type'],
                            'authors' => $row['authors'],
                        ],
                        'average_score' => $row['average_score'],
                        'review_count' => $row['review_count'],
                        'sub_theme' => $row['sub_theme'],
                    ];
                })
                ->all();
        })
        ->toArray();

    return response()->json([
        'success' => true,
        'message' => 'Reviewer rankings retrieved.',
        'data' => [
            'overall' => $overall,
            'sub_themes' => $subThemes,
        ],
    ]);
}

/**
 * GET /api/reviewer/rankings/export
 *
 * Optional CSV export of the same data.
 */
public function export(Request $request)
{
    // If you don't need this yet, return a 501 to avoid another missing-method error.
    return response()->json([
        'success' => false,
        'message' => 'Export not implemented yet.',
    ], 501);
}

/**
 * GET /api/reviewer/rankings/statistics
 *
 * Optional summary stats. Same treatment as above.
 */
public function reviewerStatistics(Request $request)
{
    return response()->json([
        'success' => false,
        'message' => 'Statistics not implemented yet.',
    ], 501);
}


}