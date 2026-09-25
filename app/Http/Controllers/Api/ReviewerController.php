<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InviteReviewerRequest;
use App\Http\Resources\ReviewerResource;
use App\Models\AbstractSubmission;
use App\Models\Reviewer;
use App\Models\ReviewAssignment;
use App\Notifications\ReviewerInvitationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $reviewer->notify(
            new ReviewerInvitationNotification($reviewer)
        );

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
     * GET /api/abstracts/reviews/assigned
     *
     * Returns abstracts currently assigned to the authenticated reviewer.
     *
     * Important:
     * - Only the current version of an abstract is returned.
     * - The reviewer's current assignment is returned.
     * - Resubmission assignments are identified.
     * - The previous review is included when this is a resubmission.
     * - Old versions are not returned as separate assignments.
     */
    public function assigned(Request $request): JsonResponse
{
    $user = $request->user('api');

    if (! $user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
        ], 401);
    }

    $reviewer = Reviewer::query()
        ->where('user_id', $user->id)
        ->first();

    if (! $reviewer && ! empty($user->email)) {
        $reviewer = Reviewer::query()
            ->where('email', $user->email)
            ->first();
    }

    if (! $reviewer) {
        return response()->json([
            'success' => false,
            'message' => 'This account is not registered as a reviewer.',
            'errors' => [
                'reviewer' => [
                    'This account is not registered as a reviewer.',
                ],
            ],
        ], 403);
    }

    $assignments = ReviewAssignment::query()
        ->where('reviewer_id', $reviewer->id)
        ->whereHas('abstract', function ($query) {
            $query->where('is_current', true);
        })
        ->with([
            'review',
            'abstract' => function ($query) {
                $query
                    ->where('is_current', true)
                    ->with('authors');
            },
        ])
        ->orderByDesc('assigned_at')
        ->get();

    $items = $assignments
        ->filter(fn (ReviewAssignment $assignment) => $assignment->abstract)
        ->map(function (ReviewAssignment $assignment) use ($reviewer) {
            $abstract = $assignment->abstract;
            $review = $assignment->review;

            $previousReview = null;

            if (
                $assignment->is_resubmission_review &&
                $assignment->source_assignment_id
            ) {
                $sourceAssignment = ReviewAssignment::query()
                    ->with('review')
                    ->find($assignment->source_assignment_id);

                if ($sourceAssignment?->review) {
                    $oldReview = $sourceAssignment->review;

                    $previousReview = [
                        'id' => $oldReview->id,
                        'review_assignment_id' => $oldReview->review_assignment_id,
                        'significance' => $oldReview->significance,
                        'relevance' => $oldReview->relevance,
                        'originality' => $oldReview->originality,
                        'average' => $oldReview->average,
                        'comment' => $oldReview->comment,
                        'recommended_rejection_reason' =>
                            $oldReview->recommended_rejection_reason,
                        'submitted_at' => $oldReview->submitted_at,
                    ];
                }
            }

            return [
                'id' => $abstract->id,
                'reference' => $abstract->reference,
                'title' => $abstract->title,
                'body' => $abstract->body,
                'sub_theme' => $abstract->sub_theme,
                'presentation_type' => $abstract->presentation_type,
                'keywords' => $abstract->keywords,
                'status' => $abstract->status,
                'version' => $abstract->version,
                'is_current' => (bool) $abstract->is_current,
                'submitted_at' => $abstract->submitted_at,
                'resubmission_note' => $abstract->resubmission_note,
                'resubmitted_at' => $abstract->resubmitted_at,
                'average_score' => $abstract->average_score ?? null,

                'authors' => $abstract->authors
                    ->map(function ($author) {
                        return [
                            'id' => $author->id,
                            'name' => $author->name,
                            'email' => $author->email,
                            'phone' => $author->phone,
                            'affiliation' => $author->affiliation,
                            'is_corresponding' =>
                                (bool) $author->is_corresponding,
                        ];
                    })
                    ->values(),

                'assignment_id' => $assignment->id,
                'reviewer_id' => $reviewer->id,
                'reviewer_name' => $reviewer->name,
                'reviewer_email' => $reviewer->email,

                'assignment_status' => $assignment->status,
                'assigned_at' => $assignment->assigned_at,

                'is_resubmission_review' =>
                    (bool) $assignment->is_resubmission_review,

                'source_assignment_id' =>
                    $assignment->source_assignment_id,

                'review' => $review
                    ? [
                        'id' => $review->id,
                        'review_assignment_id' =>
                            $review->review_assignment_id,
                        'significance' => $review->significance,
                        'relevance' => $review->relevance,
                        'originality' => $review->originality,
                        'average' => $review->average,
                        'comment' => $review->comment,
                        'recommended_rejection_reason' =>
                            $review->recommended_rejection_reason,
                        'submitted_at' => $review->submitted_at,
                    ]
                    : null,

                'previous_review' => $previousReview,

                'reviewers' => [
                    [
                        'id' => $reviewer->id,
                        'reviewer_id' => $reviewer->id,
                        'name' => $reviewer->name,
                        'email' => $reviewer->email,
                        'reviewer_name' => $reviewer->name,
                        'status' => $assignment->status,
                        'assigned_at' => $assignment->assigned_at,

                        'isResubmissionReview' =>
                            (bool) $assignment->is_resubmission_review,

                        'is_resubmission_review' =>
                            (bool) $assignment->is_resubmission_review,

                        'review' => $review
                            ? [
                                'id' => $review->id,
                                'significance' => $review->significance,
                                'relevance' => $review->relevance,
                                'originality' => $review->originality,
                                'average' => $review->average,
                                'comment' => $review->comment,
                                'recommended_rejection_reason' =>
                                    $review->recommended_rejection_reason,
                                'submitted_at' => $review->submitted_at,
                            ]
                            : null,
                    ],
                ],
            ];
        })
        ->values();

    return response()->json([
        'success' => true,
        'message' => 'Assigned abstracts retrieved.',
        'data' => [
            'items' => $items,
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

        $reviewer->notify(
            new ReviewerInvitationNotification($reviewer)
        );

        return response()->json([
            'success' => true,
            'message' => "Invitation resent to {$reviewer->email}.",
            'data' => new ReviewerResource($reviewer),
        ]);
    }
}