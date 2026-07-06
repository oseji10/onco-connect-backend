<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Event::query()->withCount([
            'passes',
            'mealSessions',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $events = $query
            ->latest('eventId')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    public function store(Request $request): JsonResponse
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
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:draft,active,closed,cancelled'],
        ]);

        $event = Event::create([
            'title' => $validated['title'],
            'slug' => $this->generateUniqueSlug($validated['title']),
            'description' => $validated['description'] ?? null,
            'startDate' => $validated['startDate'],
            'endDate' => $validated['endDate'],
            'location' => $validated['location'] ?? null,
            'status' => $validated['status'] ?? 'draft',
            // 'passCount' => 0,
            'createdBy' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully.',
            'data' => $event,
        ], 201);
    }

    public function show(Event $event): JsonResponse
    {
        $event->loadCount([
            'passes',
            'mealSessions',
        ]);

        $event->load([
            'mealSessions' => function ($query) {
                $query->orderBy('mealDate')->orderBy('startTime');
            }
        ]);

        return response()->json([
            'success' => true,
            'data' => $event,
        ]);
    }

    public function update(Request $request, Event $event): JsonResponse
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
            'startDate' => ['sometimes', 'date'],
            'endDate' => ['sometimes', 'date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:draft,active,closed,cancelled'],
        ]);

        if (
            isset($validated['startDate'], $validated['endDate']) &&
            $validated['endDate'] < $validated['startDate']
        ) {
            return response()->json([
                'success' => false,
                'message' => 'End date must be after or equal to start date.',
            ], 422);
        }

        if (isset($validated['title']) && $validated['title'] !== $event->title) {
            $validated['slug'] = $this->generateUniqueSlug($validated['title'], $event->eventId);
        }

        $event->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully.',
            'data' => $event->fresh(),
        ]);
    }

    public function destroy(Request $request, Event $event): JsonResponse
    {
     $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($event->passes()->exists() || $event->mealSessions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete an event with passes or meal sessions.',
            ], 422);
        }

        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully.',
        ]);
    }

    private function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $counter = 1;

        while (
            Event::query()
                ->when($ignoreId, fn ($q) => $q->where('eventId', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }



  

public function updateStatus(Request $request, Event $event): JsonResponse
{
    $validated = $request->validate([
        'status' => [
            'required',
            Rule::in(['draft', 'active', 'closed', 'cancelled']),
        ],
    ]);

    // 🚨 Important business rule (optional but recommended)
    if ($validated['status'] === 'active') {
        Event::where('status', 'active')
            ->where('eventId', '!=', $event->id)
            ->update(['status' => 'closed']);
    }

    $event->update([
        'status' => $validated['status'],
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Event status updated successfully',
        'data' => $event,
    ]);
}
}