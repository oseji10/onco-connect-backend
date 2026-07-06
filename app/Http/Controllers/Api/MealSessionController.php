<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\MealSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MealSessionController extends Controller
{
    public function index(Request $request, Event $event): JsonResponse
    {
        $query = $event->mealSessions()->withCount('redemptions');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sessions = $query
            // ->orderBy('mealDate')
            // ->orderBy('startTime')

            ->latest('mealSessionId')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }


    // public function getMealSessions(Request $request, Event $event): JsonResponse
    // {
    //     $query = $event->mealSessions()->withCount('redemptions');

    //     if ($request->filled('status')) {
    //         $query->where('status', $request->status);
    //     }

    //     $sessions = $query
    //         // ->orderBy('mealDate')
    //         // ->orderBy('startTime')

    //         ->latest('mealSessionId')
    //         ->paginate($request->integer('per_page', 20));

    //     return response()->json([
    //         'success' => true,
    //         'data' => $sessions,
    //     ]);
    // }


public function getMealSessions(Request $request): JsonResponse
{
    $event = Event::where('status', 'active')->first();

    if (!$event) {
        return response()->json([
            'success' => false,
            'message' => 'No active event found'
        ], 404);
    }

    $query = $event->mealSessions()->withCount('redemptions');

    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    $sessions = $query
        ->latest('mealSessionId')
        ->paginate($request->integer('per_page', 20));

    return response()->json([
        'success' => true,
        'data' => $sessions,
    ]);
}

    

    public function store(Request $request, Event $event): JsonResponse
    {
       $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'mealDate' => ['required', 'date'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i', 'after:startTime'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:draft,active,closed,cancelled'],
            'sortOrder' => ['nullable', 'integer', 'min:0'],
        ]);

        $mealDate = $validated['mealDate'];

        if ($mealDate < $event->startDate || $mealDate > $event->endDate) {
            return response()->json([
                'success' => false,
                'message' => 'Meal date must fall within the event date range.',
            ], 422);
        }

        if (($validated['status'] ?? 'draft') === 'active') {
            $event->mealSessions()
                ->where('status', 'active')
                ->update(['status' => 'closed']);
        }

        $mealSession = MealSession::create([
            'eventId' => $event->eventId,
            'title' => $validated['title'],
            'slug' => $this->generateUniqueSlug($validated['title']),
            'description' => $validated['description'] ?? null,
            'mealDate' => $validated['mealDate'],
            'startTime' => $validated['startTime'],
            'endTime' => $validated['endTime'],
            'location' => $validated['location'] ?? $event->location,
            'status' => $validated['status'] ?? 'draft',
            'sortOrder' => $validated['sortOrder'] ?? 0,
            'redeemedCount' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Meal session created successfully.',
            'data' => $mealSession,
        ], 201);
    }

    public function show(MealSession $mealSession): JsonResponse
    {
        $mealSession->load(['event']);
        $mealSession->loadCount('redemptions');

        return response()->json([
            'success' => true,
            'data' => $mealSession,
        ]);
    }

    public function update(Request $request, MealSession $mealSession): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'mealDate' => ['sometimes', 'date'],
            'startTime' => ['sometimes', 'date_format:H:i'],
            'endTime' => ['sometimes', 'date_format:H:i'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:draft,active,closed,cancelled'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
        ]);

        $mealDate = $validated['mealDate'] ?? $mealSession->mealDate;
        $startTime = $validated['startTime'] ?? $mealSession->startTime;
        $endTime = $validated['endTime'] ?? $mealSession->endTime;

        if ($endTime <= $startTime) {
            return response()->json([
                'success' => false,
                'message' => 'End time must be after start time.',
            ], 422);
        }

        $event = $mealSession->event;

        if ($mealDate < $event->startDate || $mealDate > $event->endDate) {
            return response()->json([
                'success' => false,
                'message' => 'Meal date must fall within the event date range.',
            ], 422);
        }

        if (isset($validated['title']) && $validated['title'] !== $mealSession->title) {
            $validated['slug'] = $this->generateUniqueSlug($validated['title'], $mealSession->mealSessionId);
        }

        if (($validated['status'] ?? null) === 'active') {
            $event->mealSessions()
                ->where('mealSessionId', '!=', $mealSession->mealSessionId)
                ->where('status', 'active')
                ->update(['status' => 'closed']);
        }

        $mealSession->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Meal session updated successfully.',
            'data' => $mealSession->fresh(),
        ]);
    }

    public function updateStatus(Request $request, MealSession $mealSession): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:draft,active,closed,cancelled'],
        ]);

        if ($validated['status'] === 'active') {
            $mealSession->event->mealSessions()
                ->where('mealSessionId', '!=', $mealSession->mealSessionId)
                ->where('status', 'active')
                ->update(['status' => 'closed']);
        }

        $mealSession->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Meal session status updated successfully.',
            'data' => $mealSession->fresh(),
        ]);
    }

    public function destroy(Request $request, MealSession $mealSession): JsonResponse
    {
       $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($mealSession->redemptions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a meal session with redemptions.',
            ], 422);
        }

        $mealSession->delete();

        return response()->json([
            'success' => true,
            'message' => 'Meal session deleted successfully.',
        ]);
    }

    private function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $counter = 1;

        while (
            MealSession::query()
                ->when($ignoreId, fn ($q) => $q->where('mealSessionId', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}