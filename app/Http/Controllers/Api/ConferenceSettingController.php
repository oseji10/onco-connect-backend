<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConferenceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConferenceSettingController extends Controller
{
    /**
     * GET /api/conference-settings
     * Public — both the submission and reviewer pages read this.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Conference settings retrieved.',
            'data' => $this->present(ConferenceSetting::current()),
        ]);
    }

    /**
     * PATCH /api/conference-settings
     * Admin only — wire the `auth:api` + admin middleware on the route.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'abstractSubmissionDeadline' => ['nullable', 'date'],
            'abstractReviewDeadline' => ['nullable', 'date'],
        ]);

        $settings = ConferenceSetting::current();
        $settings->update([
            'abstract_submission_deadline' => $validated['abstractSubmissionDeadline'] ?? null,
            'abstract_review_deadline' => $validated['abstractReviewDeadline'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Conference settings updated.',
            'data' => $this->present($settings),
        ]);
    }

    private function present(ConferenceSetting $settings): array
    {
        return [
            'abstractSubmissionDeadline' => $settings->abstract_submission_deadline,
            'abstractReviewDeadline' => $settings->abstract_review_deadline,
            'submissionsClosed' => $settings->submissionsClosed(),
            'reviewsClosed' => $settings->reviewsClosed(),
        ];
    }
}