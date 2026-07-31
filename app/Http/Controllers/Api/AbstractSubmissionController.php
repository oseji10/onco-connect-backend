<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAbstractRequest;
use App\Http\Requests\UpdateAbstractStatusRequest;
use App\Http\Resources\AbstractResource;
use App\Models\AbstractSubmission;
use App\Notifications\AbstractDecisionNotification;
use App\Notifications\AbstractSubmittedNotification;
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

    private function notifyAuthorOfSubmission(AbstractSubmission $abstract): void
    {
        $author = $abstract->correspondingAuthor();
        if (! $author || ! $author->email) {
            return;
        }

        Notification::route('mail', $author->email)
            ->notify(new AbstractSubmittedNotification($abstract));
    }

    /**
     * GET /api/abstracts
     * Admin — list with search/filter/pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AbstractSubmission::query()
            ->with(['authors', 'assignments.reviewer', 'assignments.review'])
            ->latest('submitted_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($subTheme = $request->query('subTheme')) {
            $query->where('sub_theme', $subTheme);
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
        $author = $abstract->correspondingAuthor();
        if (! $author || ! $author->email) {
            return;
        }

        Notification::route('mail', $author->email)
            ->notify(new AbstractDecisionNotification($abstract));
    }
}