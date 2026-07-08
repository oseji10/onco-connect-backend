<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignReviewersRequest;
use App\Http\Resources\AbstractResource;
use App\Models\AbstractSubmission;
use App\Models\ReviewAssignment;
use App\Notifications\AbstractAssignedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReviewAssignmentController extends Controller
{
    /**
     * POST /api/abstracts/{abstract}/assign-reviewers
     * Admin only. Adds any reviewer ids not already assigned; existing
     * assignments (and any review already submitted) are left untouched.
     */
    public function store(AssignReviewersRequest $request, AbstractSubmission $abstract): JsonResponse
    {
        $requestedIds = collect($request->input('reviewerIds'))->unique()->values();

        $alreadyAssignedIds = $abstract->assignments()->pluck('reviewer_id');
        $newIds = $requestedIds->diff($alreadyAssignedIds);

        DB::transaction(function () use ($abstract, $newIds) {
            foreach ($newIds as $reviewerId) {
                $assignment = ReviewAssignment::create([
                    'abstract_id' => $abstract->id,
                    'reviewer_id' => $reviewerId,
                    'status' => 'invited',
                    'assigned_at' => now(),
                ]);
                $assignment->reviewer->notify(new AbstractAssignedNotification($abstract));
            }

            if ($abstract->status === 'submitted') {
                $abstract->update(['status' => 'under_review']);
            }
        });

        $abstract->load(['authors', 'assignments.reviewer', 'assignments.review']);

        return response()->json([
            'success' => true,
            'message' => $newIds->isEmpty()
                ? 'Reviewers already assigned.'
                : 'Reviewers assigned.',
            'data' => new AbstractResource($abstract),
        ]);
    }
}