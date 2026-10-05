<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbstractSubmission;
use App\Models\OralPanelist;
use App\Models\OralScore;
use App\Services\OralScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ADMIN endpoints: manage panelists and view the scored presentations.
 * Put these routes behind ->middleware(['auth:api', 'role:super_admin,admin']).
 */
class OralScoringAdminController extends Controller
{
    public function __construct(protected OralScoringService $service) {}

    // ── Results ──────────────────────────────────────────────────────────

    /** GET /oral-scoring/results */
    public function results(): JsonResponse
    {
        $presentations = $this->service->presentations();

        $scoresByAbstract = OralScore::with('panelist')
            ->whereIn('abstract_id', $presentations->pluck('id'))
            ->get()
            ->groupBy('abstract_id');

        $rows = $presentations->map(function (AbstractSubmission $a) use ($scoresByAbstract) {
            $list  = $scoresByAbstract->get($a->id, collect());
            $count = $list->count();

            $criteriaAverages = null;
            if ($count) {
                $criteriaAverages = [];
                foreach (OralScoringService::CRITERIA as $criterion) {
                    $criteriaAverages[$criterion] = round($list->avg($criterion), 2);
                }
            }

            return [
                'id'                => $a->id,
                'reference'         => $this->service->displayReference($a),
                'title'             => $a->title,
                'subTheme'          => $a->sub_theme,
                'presenter'         => $this->service->presenterName($a),
                'scoresCount'       => $count,
                'averageTotal'      => $count ? round($list->avg('total'), 2) : null,
                'averagePercentage' => $count ? round($list->avg('percentage'), 2) : null,
                'criteriaAverages'  => $criteriaAverages,
                'scores'            => $list
                    ->sortBy(fn (OralScore $s) => strtolower($s->panelist->name ?? ''))
                    ->map(fn (OralScore $s) => array_merge(
                        $this->service->formatScore($s),
                        ['panelistId' => $s->panelist_id, 'panelistName' => $s->panelist->name ?? 'Unknown']
                    ))
                    ->values(),
            ];
        });

        // Rank by average percentage; equal averages share a rank.
        $scored = $rows->filter(fn ($r) => $r['averagePercentage'] !== null)
            ->sortByDesc('averagePercentage')
            ->values();

        $rankById = [];
        $prevAvg  = null;
        $prevRank = 0;
        foreach ($scored as $i => $row) {
            $rank = ($prevAvg !== null && $row['averagePercentage'] == $prevAvg) ? $prevRank : $i + 1;
            $rankById[$row['id']] = $rank;
            $prevAvg  = $row['averagePercentage'];
            $prevRank = $rank;
        }

        $scoredRows   = $scored->map(fn ($r) => $r + ['rank' => $rankById[$r['id']]]);
        $unscoredRows = $rows->filter(fn ($r) => $r['averagePercentage'] === null)
            ->map(fn ($r) => $r + ['rank' => null])
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Oral scoring results retrieved.',
            'data'    => [
                'presentations' => $scoredRows->concat($unscoredRows)->values(),
                'summary'       => [
                    'presentations'    => $presentations->count(),
                    'scoredCount'      => $scored->count(),
                    'totalScores'      => $scoresByAbstract->flatten()->count(),
                    'panelists'        => OralPanelist::count(),
                    'activePanelists'  => OralPanelist::where('is_active', true)->count(),
                ],
            ],
        ]);
    }

    // ── Panelists ────────────────────────────────────────────────────────

    /** GET /oral-scoring/panelists */
    public function panelists(): JsonResponse
    {
        $panelists = OralPanelist::withCount('scores')
            ->orderBy('name')
            ->get()
            ->map(fn (OralPanelist $p) => $this->formatPanelist($p))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Panelists retrieved.',
            'data'    => $panelists,
        ]);
    }

    /** POST /oral-scoring/panelists */
    public function storePanelist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $panelist = OralPanelist::create([
            'name'      => trim($validated['name']),
            'email'     => $validated['email'] ?? null,
            'token'     => OralPanelist::newToken(),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Panelist added.',
            'data'    => $this->formatPanelist($panelist->loadCount('scores')),
        ], 201);
    }

    /** PATCH /oral-scoring/panelists/{panelist} */
    public function updatePanelist(Request $request, OralPanelist $panelist): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['sometimes', 'required', 'string', 'max:255'],
            'email'    => ['sometimes', 'nullable', 'email', 'max:255'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $update = [];
        if (array_key_exists('name', $validated)) {
            $update['name'] = trim($validated['name']);
        }
        if (array_key_exists('email', $validated)) {
            $update['email'] = $validated['email'];
        }
        if (array_key_exists('isActive', $validated)) {
            $update['is_active'] = $validated['isActive'];
        }

        $panelist->update($update);

        return response()->json([
            'success' => true,
            'message' => 'Panelist updated.',
            'data'    => $this->formatPanelist($panelist->loadCount('scores')),
        ]);
    }

    /** POST /oral-scoring/panelists/{panelist}/regenerate  (old link stops working) */
    public function regenerateToken(OralPanelist $panelist): JsonResponse
    {
        $panelist->update(['token' => OralPanelist::newToken()]);

        return response()->json([
            'success' => true,
            'message' => 'New link generated. The old link no longer works.',
            'data'    => $this->formatPanelist($panelist->loadCount('scores')),
        ]);
    }

    /** DELETE /oral-scoring/panelists/{panelist}  (also deletes their scores) */
    public function destroyPanelist(OralPanelist $panelist): JsonResponse
    {
        $panelist->delete();

        return response()->json(['success' => true, 'message' => 'Panelist deleted.']);
    }

    private function formatPanelist(OralPanelist $p): array
    {
        return [
            'id'          => $p->id,
            'name'        => $p->name,
            'email'       => $p->email,
            'token'       => $p->token,
            'isActive'    => (bool) $p->is_active,
            'lastSeenAt'  => optional($p->last_seen_at)->toIso8601String(),
            'scoresCount' => (int) ($p->scores_count ?? 0),
            'createdAt'   => optional($p->created_at)->toIso8601String(),
        ];
    }
}