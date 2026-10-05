<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbstractSubmission;
use App\Models\OralPanelist;
use App\Models\OralScore;
use App\Services\OralScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * PUBLIC endpoints for panelists (no login). The secret token in the URL
 * identifies the panelist. Invalid / deactivated links answer 404 / 403
 * (never 401) so the front end doesn't treat them as an expired login.
 *
 *   GET  /panel/{token}/presentations
 *   POST /panel/{token}/presentations/{abstract}/score
 */
class PanelScoringController extends Controller
{
    public function __construct(protected OralScoringService $service) {}

    private function resolve(string $token): OralPanelist|JsonResponse
    {
        $panelist = OralPanelist::where('token', $token)->first();

        if (!$panelist) {
            return response()->json(['success' => false, 'message' => 'This scoring link is not valid.'], 404);
        }

        if (!$panelist->is_active) {
            return response()->json(['success' => false, 'message' => 'This scoring link has been deactivated.'], 403);
        }

        return $panelist;
    }

    public function index(string $token): JsonResponse
    {
        $panelist = $this->resolve($token);
        if ($panelist instanceof JsonResponse) {
            return $panelist;
        }

        $panelist->forceFill(['last_seen_at' => now()])->save();

        $mine = $panelist->scores()->get()->keyBy('abstract_id');

        $presentations = $this->service->presentations()->map(function (AbstractSubmission $a) use ($mine) {
            $score = $mine->get($a->id);

            return [
                'id'        => $a->id,
                'reference' => $this->service->displayReference($a),
                'title'     => $a->title,
                'subTheme'  => $a->sub_theme,
                'presenter' => $this->service->presenterName($a),
                'myScore'   => $score ? $this->service->formatScore($score) : null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Presentations retrieved.',
            'data'    => [
                'panelist'      => ['name' => $panelist->name],
                'presentations' => $presentations,
            ],
        ]);
    }

    public function score(Request $request, string $token, AbstractSubmission $abstract): JsonResponse
    {
        $panelist = $this->resolve($token);
        if ($panelist instanceof JsonResponse) {
            return $panelist;
        }

        if (!$this->service->isPresentation($abstract->id)) {
            return response()->json(['success' => false, 'message' => 'This presentation is not open for scoring.'], 404);
        }

        $rules = [
            'scores'  => ['required', 'array'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
        foreach (OralScoringService::CRITERIA as $criterion) {
            $rules["scores.$criterion"] = ['required', 'integer', 'between:1,5'];
        }

        $validated = $request->validate($rules);

        [$total, $percentage] = $this->service->summarise($validated['scores']);

        $score = OralScore::updateOrCreate(
            ['abstract_id' => $abstract->id, 'panelist_id' => $panelist->id],
            array_merge(
                Arr::only($validated['scores'], OralScoringService::CRITERIA),
                ['total' => $total, 'percentage' => $percentage, 'comment' => $validated['comment'] ?? null]
            )
        );

        return response()->json([
            'success' => true,
            'message' => 'Score saved.',
            'data'    => $this->service->formatScore($score),
        ]);
    }
}