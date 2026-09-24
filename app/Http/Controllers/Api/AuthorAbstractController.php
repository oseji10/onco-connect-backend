<?php
// app/Http/Controllers/Api/AuthorAbstractController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResubmitAbstractRequest;
use App\Http\Resources\AbstractResource;
use App\Models\AbstractAuthor;
use App\Models\AbstractSubmission;
use App\Services\AbstractResubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthorAbstractController extends Controller
{
    public function __construct(
        private readonly AbstractResubmissionService $resubmissionService,
    ) {}

    /**
     * GET /api/author/abstracts
     * Every abstract this user is an author on, latest version only.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = auth('api')->id();

        $abstracts = AbstractSubmission::query()
            ->whereHas('authors', fn ($q) => $q->where('user_id', $userId))
            ->where('is_current', true)
            ->with(['authors', 'assignments.review', 'assignments.reviewer'])
            ->latest('submitted_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => AbstractResource::collection($abstracts),
        ]);
    }

    /**
     * GET /api/author/abstracts/{abstract}
     * Current version + full version timeline with reviews on each version.
     */
    public function show(AbstractSubmission $abstract): JsonResponse
    {
        $this->authorizeAuthor($abstract);

        return response()->json([
            'success' => true,
            'data' => [
                'current'  => new AbstractResource($abstract->load(['authors', 'assignments.review', 'assignments.reviewer'])),
                'versions' => $this->buildVersionTimeline($abstract),
            ],
        ]);
    }

    /**
     * GET /api/author/abstracts/{abstract}/versions
     */
    public function versions(AbstractSubmission $abstract): JsonResponse
    {
        $this->authorizeAuthor($abstract);

        return response()->json([
            'success' => true,
            'data' => $this->buildVersionTimeline($abstract),
        ]);
    }

    /**
     * POST /api/author/abstracts/{abstract}/resubmit
     * Creates a new version. Reviewers are notified and a fresh
     * "documentation only" review assignment is cloned.
     */
    public function resubmit(
        ResubmitAbstractRequest $request,
        AbstractSubmission $abstract,
    ): JsonResponse {
        $this->authorizeAuthor($abstract);

        if (! $abstract->is_current) {
            return response()->json([
                'success' => false,
                'message' => 'You can only resubmit the latest version of this abstract.',
            ], 422);
        }

        $newVersion = $this->resubmissionService->resubmit(
            $abstract,
            $request->validated(),
            auth('api')->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Abstract resubmitted. Reviewers have been notified.',
            'data' => new AbstractResource(
                $newVersion->load(['authors', 'assignments.review', 'assignments.reviewer'])
            ),
        ], 201);
    }

    /**
     * Build a version timeline with reviews attached to each version.
     */
    private function buildVersionTimeline(AbstractSubmission $abstract): array
    {
        return $abstract->versionChain()->map(function (AbstractSubmission $v) {
            return [
                'id' => $v->id,
                'reference' => $v->reference,
                'version' => $v->version,
                'is_current' => $v->is_current,
                'status' => $v->status,
                'title' => $v->title,
                'body' => $v->body,
                'keywords' => $v->keywords,
                'sub_theme' => $v->sub_theme,
                'presentation_type' => $v->presentation_type,
                'resubmission_note' => $v->resubmission_note,
                'submitted_at' => $v->submitted_at?->toIso8601String(),
                'resubmitted_at' => $v->resubmitted_at?->toIso8601String(),
                'reviews' => $v->assignments->map(fn ($a) => [
                    'reviewer_name' => $a->reviewer?->name,
                    'status' => $a->status,
                    'is_resubmission_review' => $a->is_resubmission_review,
                    'review' => $a->review ? [
                        'significance' => $a->review->significance,
                        'relevance' => $a->review->relevance,
                        'originality' => $a->review->originality,
                        'average' => (float) $a->review->average,
                        'comment' => $a->review->comment,
                        'submitted_at' => $a->review->submitted_at?->toIso8601String(),
                    ] : null,
                ])->values(),
            ];
        })->all();
    }

    /**
     * The authenticated user must appear as an author on this abstract
     * OR on any version in its chain.
     */
    private function authorizeAuthor(AbstractSubmission $abstract): void
    {
        $userId = auth('api')->id();

        $chainIds = $abstract->versionChain()->pluck('id');

        $isAuthor = AbstractAuthor::whereIn('abstract_id', $chainIds)
            ->where('user_id', $userId)
            ->exists();

        abort_unless($isAuthor, 403, 'You are not an author of this abstract.');
    }
}