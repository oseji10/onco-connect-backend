<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InviteReviewerRequest;
use App\Http\Resources\ReviewerResource;
use App\Models\Reviewer;
use App\Notifications\ReviewerInvitationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ReviewerController extends Controller
{
    /**
     * POST /api/abstracts/reviewers/invite
     * Admin only.
     */
    public function invite(InviteReviewerRequest $request): JsonResponse
    {
        $reviewer = Reviewer::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'affiliation' => $request->input('affiliation'),
            'status' => 'invited',
            'invite_token' => Str::random(48),
            'invited_at' => now(),
        ]);

        $reviewer->notify(new ReviewerInvitationNotification($reviewer));

        return response()->json([
            'success' => true,
            'message' => "Invitation sent to {$reviewer->email}.",
            'data' => new ReviewerResource($reviewer),
        ], 201);
    }

    /**
     * GET /api/abstracts/reviewers
     * Admin only — reviewer pool with assignment counts.
     */
    public function index(): JsonResponse
    {
        $reviewers = Reviewer::query()
            ->withCount([
                'assignments',
                'assignments as completed_assignments_count' => function ($q) {
                    $q->where('status', 'submitted');
                },
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Reviewers retrieved.',
            'data' => [
                'items' => ReviewerResource::collection($reviewers),
            ],
        ]);
    }

    /**
     * POST /api/abstracts/reviewers/{reviewer}/resend-invite
     * Admin only — issues a fresh token (invalidating the old link) and
     * resets the 14-day expiry window. No-op if already active.
     */
    public function resendInvite(Reviewer $reviewer): JsonResponse
    {
        if ($reviewer->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This reviewer has already accepted an invite.',
            ], 422);
        }

        $reviewer->update([
            'invite_token' => Str::random(48),
            'invited_at' => now(),
        ]);

        $reviewer->notify(new ReviewerInvitationNotification($reviewer));

        return response()->json([
            'success' => true,
            'message' => "Invitation resent to {$reviewer->email}.",
            'data' => new ReviewerResource($reviewer),
        ]);
    }
}