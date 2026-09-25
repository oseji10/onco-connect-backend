<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbstractSubmission;
use App\Models\Reviewer;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Notifications\AbstractReviewedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    /**
     * GET /api/abstracts/reviews/assigned
     *
     * Get the abstracts assigned to the currently authenticated reviewer.
     *
     * A user may have multiple roles.
     *
     * We therefore DO NOT check the user's role here.
     * We determine whether the user is a reviewer by finding
     * a Reviewer record linked to the authenticated user's ID.
     */
    public function assigned(Request $request): JsonResponse
    {
        $reviewer = $this->resolveReviewer($request);

        if (! $reviewer) {
            $user = $request->user('api');

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

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

        /*
         * Only return assignments whose abstract is currently active.
         *
         * When an author resubmits:
         *
         * Version 1 -> is_current = false
         * Version 2 -> is_current = true
         *
         * The reviewer should therefore see Version 2, not Version 1.
         */
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
            ->filter(function (ReviewAssignment $assignment) {
                return $assignment->abstract !== null;
            })
            ->map(function (ReviewAssignment $assignment) use ($reviewer) {
                /** @var AbstractSubmission $abstract */
                $abstract = $assignment->abstract;

                $review = $assignment->review;

                /*
                 * If this is a resubmission review, retrieve the
                 * review from the previous assignment.
                 *
                 * This lets the reviewer see what they previously
                 * submitted for the old version.
                 */
                $previousReview = null;

                if (
                    $assignment->is_resubmission_review &&
                    $assignment->source_assignment_id
                ) {
                    $sourceAssignment = ReviewAssignment::query()
                        ->with('review')
                        ->find($assignment->source_assignment_id);

                    if ($sourceAssignment && $sourceAssignment->review) {
                        $oldReview = $sourceAssignment->review;

                        $previousReview = [
                            'id' => $oldReview->id,
                            'review_assignment_id' =>
                                $oldReview->review_assignment_id,
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
                    /*
                     * Abstract information.
                     */
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

                    /*
                     * Abstract scoring.
                     */
                    'average_score' => $abstract->average_score ?? null,

                    /*
                     * Authors.
                     */
                    'authors' => $abstract->authors
                        ? $abstract->authors
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
                            ->values()
                            ->all()
                        : [],

                    /*
                     * Current reviewer's assignment.
                     */
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

                    /*
                     * Current review.
                     */
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

                    /*
                     * Previous version's review.
                     */
                    'previous_review' => $previousReview,

                    /*
                     * This structure is kept because your existing
                     * Next.js reviewer dashboard expects reviewers[0].
                     */
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
     * POST /api/abstracts/{abstract}/review
     *
     * Submit or update the review for the currently assigned abstract.
     */
    public function store(
        Request $request,
        AbstractSubmission $abstract
    ): JsonResponse {
        /*
         * Always resolve the authenticated user using the API guard.
         */
        $user = $request->user('api');

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
         * Resolve reviewer profile.
         *
         * This does NOT inspect the user's role.
         *
         * A user can simultaneously be:
         *
         * - admin
         * - author
         * - reviewer
         *
         * The Reviewer table determines reviewer access.
         */
        $reviewer = $this->resolveReviewer($request);

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

        /*
         * Review only the current version.
         */
        if (! $abstract->is_current) {
            return response()->json([
                'success' => false,
                'message' => 'Only the current version of an abstract can be reviewed.',
            ], 422);
        }

        /*
         * Validate review data.
         */
        $validated = $request->validate([
            'significance' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'relevance' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'originality' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'comment' => [
                'nullable',
                'string',
            ],

            'recommended_rejection_reason' => [
                'nullable',
                'string',
            ],
        ]);

        /*
         * Find THIS reviewer's assignment for THIS abstract.
         */
        $assignment = ReviewAssignment::query()
            ->where('abstract_id', $abstract->id)
            ->where('reviewer_id', $reviewer->id)
            ->first();

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'This abstract is not assigned to you.',
            ], 403);
        }

        /*
         * A submitted assignment should not be submitted again
         * unless you explicitly want to support editing reviews.
         *
         * For now, preserve the existing behaviour.
         */
        if ($assignment->status === 'submitted') {
            return response()->json([
                'success' => false,
                'message' => 'You have already submitted a review for this abstract.',
            ], 422);
        }

        /*
         * Calculate average.
         */
        $average = round(
            (
                (float) $validated['significance'] +
                (float) $validated['relevance'] +
                (float) $validated['originality']
            ) / 3,
            2
        );

        DB::transaction(function () use (
            $assignment,
            $validated,
            $average,
            $abstract
        ) {
            Review::updateOrCreate(
                [
                    'review_assignment_id' => $assignment->id,
                ],
                [
                    'significance' =>
                        $validated['significance'],

                    'relevance' =>
                        $validated['relevance'],

                    'originality' =>
                        $validated['originality'],

                    'average' =>
                        $average,

                    'comment' =>
                        $validated['comment'] ?? null,

                    'recommended_rejection_reason' =>
                        $validated['recommended_rejection_reason'] ?? null,

                    'submitted_at' =>
                        now(),
                ]
            );

            $assignment->update([
                'status' => 'submitted',
            ]);

            /*
             * Recalculate abstract scoring.
             */
            $abstract->refreshScoring();
        });

        /*
         * Reload relationships.
         */
        $abstract->load([
            'authors',
            'assignments.reviewer',
            'assignments.review',
        ]);

        /*
         * Notify the author if the notification class exists
         * and the abstract has an author/corresponding author.
         */
        try {
            $author = $abstract->authors
                ->firstWhere('is_corresponding', true)
                ?? $abstract->authors->first();

            if ($author) {
                /*
                 * Keep notification behaviour isolated so a mail
                 * configuration problem does not make a successful
                 * review submission look like it failed.
                 */
                $author->notify(
                    new AbstractReviewedNotification($abstract)
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully.',
            'data' => [
                'abstract' => $abstract,
                'assignment' => $assignment->fresh([
                    'review',
                    'reviewer',
                ]),
            ],
        ]);
    }

    /**
     * Resolve the reviewer profile belonging to the authenticated user.
     *
     * IMPORTANT:
     * A user can have MULTIPLE roles.
     *
     * We therefore do not check:
     *
     *     $user->role === 'reviewer'
     *
     * Instead, reviewer status is determined by the existence of
     * a Reviewer record linked to this user.
     */
    private function resolveReviewer(Request $request): ?Reviewer
    {
        $user = $request->user('api');

        /*
         * Never access $user->id before checking whether authentication
         * actually succeeded.
         */
        if (! $user) {
            return null;
        }

        /*
         * Preferred relationship.
         */
        $reviewer = Reviewer::query()
            ->where('user_id', $user->id)
            ->first();

        /*
         * Backward-compatible fallback for reviewer records created
         * before user_id was populated.
         */
        if (! $reviewer && ! empty($user->email)) {
            $reviewer = Reviewer::query()
                ->where('email', $user->email)
                ->first();
        }

        return $reviewer;
    }
}
