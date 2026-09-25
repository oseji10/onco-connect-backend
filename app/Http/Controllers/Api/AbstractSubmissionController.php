<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAbstractRequest;
use App\Http\Requests\UpdateAbstractStatusRequest;
use App\Http\Resources\AbstractResource;
use App\Models\AbstractSubmission;
use App\Notifications\AbstractDecisionNotification;
use App\Notifications\AbstractSubmittedNotification;
use App\Models\ConferenceSetting;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class AbstractSubmissionController extends Controller
{
    /**
     * POST /api/abstracts/submit
     * Public — no auth required.
     */
    public function store(StoreAbstractRequest $request): JsonResponse
    {
        if (ConferenceSetting::current()->submissionsClosed()) {
        return response()->json([
            'success' => false,
            'message' => 'The abstract submission deadline has passed. Submissions are closed.',
        ], 422);
    }
        $abstract = DB::transaction(function () use ($request) {
            $abstract = AbstractSubmission::create([
                'reference' => 'PENDING', // placeholder, replaced below once we have an id
                'title' => $request->input('title'),
                'sub_theme' => $request->input('subTheme'),
                'presentation_type' => $request->input('presentationType'),
                'keywords' => $request->input('keywords'),
                'body' => $request->input('body'),
                'word_count' => $request->wordCount(),
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            $abstract->update([
                'reference' => sprintf('ICW2026-%04d', $abstract->id),
            ]);

            $authors = collect($request->input('authors'))->map(function ($author, $index) {
                return [
                    'name' => $author['name'],
                    'affiliation' => $author['affiliation'],
                    'email' => $author['email'] ?? null,
                    'phone' => $author['phone'] ?? null,
                    'is_corresponding' => filter_var(
                        $author['isCorresponding'] ?? false,
                        FILTER_VALIDATE_BOOLEAN
                    ),
                    'order' => $index,
                ];
            });
            $abstract->authors()->createMany($authors->all());

            return $abstract;
        });

        $abstract->load('authors');

        $this->notifyAuthorOfSubmission($abstract);

        return (new AbstractResource($abstract))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/abstracts
     * Admin — list with search/filter/pagination.
     *
     * Only the CURRENT version of each abstract is returned (see the
     * is_current filter below) — see the note further down for why.
     *
     * Optional `resubmittedOnly=1` query param restricts results to
     * abstracts that are on version 2+ of their current (live) row,
     * i.e. abstracts that have actually been resubmitted at least
     * once. This is applied server-side, before pagination, so the
     * "Resubmitted only" toggle on the frontend gets an accurate
     * total and can page through ALL matching abstracts — not just
     * whichever ones happen to fall on the currently loaded page.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AbstractSubmission::query()
            ->where('is_current', true)
            ->with(['authors', 'assignments.reviewer', 'assignments.review'])
            ->latest('submitted_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($subTheme = $request->query('subTheme')) {
            $query->where('sub_theme', $subTheme);
        }

        if ($request->boolean('resubmittedOnly')) {
            $query->where('version', '>', 1);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('authors', function ($aq) use ($search) {
                        $aq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = (int) $request->query('perPage', 10);
        $page = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Abstracts retrieved.',
            'data' => [
                'items' => AbstractResource::collection($page->items()),
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'limit' => $page->perPage(),
                'totalPages' => $page->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/abstracts/{abstract}
     * Admin — full detail (also used by reviewers loading a single abstract).
     */
    public function show(AbstractSubmission $abstract): JsonResponse
    {
        $abstract->load(['authors', 'assignments.reviewer', 'assignments.review']);

        return response()->json([
            'success' => true,
            'message' => 'Abstract retrieved.',
            'data' => new AbstractResource($abstract),
        ]);
    }

    /**
     * PATCH /api/abstracts/{abstract}/status
     * Admin — final accept/reject decision.
     */
    public function updateStatus(
        UpdateAbstractStatusRequest $request,
        AbstractSubmission $abstract
    ): JsonResponse {
        $status = $request->input('status');
        $abstract->update(['status' => $status]);

        $this->notifyAuthorOfDecision($abstract);

        return response()->json([
            'success' => true,
            'message' => "Abstract {$status}.",
            'data' => new AbstractResource($abstract->fresh(['authors', 'assignments.reviewer', 'assignments.review'])),
        ]);
    }

    private function notifyAuthorOfDecision(AbstractSubmission $abstract): void
    {
        $author = $abstract->correspondingAuthor()->first() ?? $abstract->authors()->first();

        if (! $author || ! $author->email) {
            return;
        }

        Notification::route('mail', [$author->email => $author->name])->notify(
            new AbstractDecisionNotification(
                $abstract,
                $abstract->status,               // 'accepted' | 'rejected'
                $abstract->presentation_type,    // 'oral' | 'poster' | null
                $abstract->overall_rank,
                $abstract->sub_theme,
                $abstract->sub_theme_rank,
                $author->name
            )
        );

        $abstract->forceFill(['decision_notified_at' => now()])->save();
    }

    /**
     * GET /api/abstracts/{abstract}/versions
     *
     * Returns the entire version chain for the given abstract (itself + every
     * resubmission), oldest first. Works whether the given id is the original
     * abstract or a later resubmission.
     */
    public function versions(AbstractSubmission $abstract): JsonResponse
    {
        // Resolve the root of the chain (the original submission)
        $rootId = $abstract->parent_id ? $abstract->root()->id : $abstract->id;

        $versions = AbstractSubmission::where('id', $rootId)
            ->orWhere('parent_id', $rootId)
            ->orderBy('version')
            ->with(['authors', 'assignments.review', 'assignments.reviewer'])
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Abstract versions retrieved.',
            'data' => $versions->map(function (AbstractSubmission $v) {
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
            }),
        ]);
    }

    private function notifyAuthorOfSubmission(AbstractSubmission $abstract): void
    {
        $author = $abstract->correspondingAuthor()->first() ?? $abstract->authors()->first();
        if (! $author || ! $author->email) {
            return;
        }

        Notification::route('mail', $author->email)
            ->notify(new AbstractSubmittedNotification($abstract));
    }
}