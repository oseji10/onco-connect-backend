<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\AbstractResource;
use App\Models\AbstractSubmission;
use App\Models\Review;
use App\Models\Reviewer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use App\Notifications\ReviewSubmittedNotification;

class ReviewController extends Controller
{
    /**
     * GET /api/abstracts/reviews/assigned
     * Reviewer only — abstracts assigned to the authenticated reviewer.
     * Each abstract's "assignments" relation is constrained to *this*
     * reviewer's own assignment, so the frontend's `reviewers[0]` is always
     * "my assignment", never someone else's.
     */
    public function assigned(Request $request): JsonResponse
    {
        $reviewer = $this->resolveReviewer($request);

        $abstracts = AbstractSubmission::query()
            ->whereHas('assignments', fn ($q) => $q->where('reviewer_id', $reviewer->id))
            ->with(['assignments' => function ($q) use ($reviewer) {
                $q->where('reviewer_id', $reviewer->id)->with(['reviewer', 'review']);
            }])
            ->latest('submitted_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Assigned abstracts retrieved.',
            'data' => [
                'items' => AbstractResource::collection($abstracts),
            ],
        ]);
    }

    /**
     * POST /api/abstracts/{abstract}/review
     * Reviewer only — submit (or update, if not yet submitted) a score.
     */
    public function store(
        StoreReviewRequest $request,
        AbstractSubmission $abstract
    ): JsonResponse {
        $reviewer = $this->resolveReviewer($request);

        $assignment = $abstract->assignments()
            ->where('reviewer_id', $reviewer->id)
            ->with('review')
            ->first();

        if (! $assignment) {
            throw ValidationException::withMessages([
                'abstract' => 'You are not assigned to review this abstract.',
            ]);
        }

        if ($assignment->status === 'submitted') {
            throw ValidationException::withMessages([
                'abstract' => 'You have already submitted a review for this abstract.',
            ]);
        }

        $scores = $request->input('scores');
        $average = round(
            ($scores['significance'] + $scores['relevance'] + $scores['originality']) / 3,
            2
        );

        Review::updateOrCreate(
            ['review_assignment_id' => $assignment->id],
            [
                'significance' => $scores['significance'],
                'relevance' => $scores['relevance'],
                'originality' => $scores['originality'],
                'average' => $average,
                'comment' => $request->input('comment'),
                'recommended_rejection_reason' => $request->input('recommendedRejectionReason'),
                'submitted_at' => now(),
            ]
        );

        $assignment->update(['status' => 'submitted']);
        $abstract->refreshScoring();

        $this->notifyAuthorOfReview($abstract);

        $abstract->load(['assignments' => function ($q) use ($reviewer) {
            $q->where('reviewer_id', $reviewer->id)->with(['reviewer', 'review']);
        }]);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted.',
            'data' => new AbstractResource($abstract),
        ]);
    }

    private function notifyAuthorOfReview(AbstractSubmission $abstract): void
    {
        $author = $abstract->correspondingAuthor();
        if (! $author || ! $author->email) {
            return;
        }

        $abstract->loadMissing('assignments');
        $assigned = $abstract->assignments->count();
        $submitted = $abstract->assignments->where('status', 'submitted')->count();

        Notification::route('mail', $author->email)
            ->notify(new ReviewSubmittedNotification($abstract, $submitted, $assigned));
    }

    private function resolveReviewer(Request $request): Reviewer
    {
        $reviewer = Reviewer::where('user_id', $request->user('api')?->id)->first();

        if (! $reviewer) {
            throw ValidationException::withMessages([
                'reviewer' => 'This account is not registered as a reviewer.',
            ]);
        }

        return $reviewer;
    }
}