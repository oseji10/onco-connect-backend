<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMealRequest;
use App\Http\Requests\UpdateMealRequest;
use App\Models\Meal;
use App\Models\MealSession;
use App\Models\FoodSupply;
use App\Models\FoodDistribution;
use App\Models\MealRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MealController extends Controller
{
    // ==========================================
    // EXISTING MEAL MANAGEMENT METHODS
    // ==========================================

    public function index(Request $request): JsonResponse
    {
        $query = Meal::query()->withCount([
            'tickets',
            'tickets as redeemed_tickets_count' => fn ($q) => $q->where('status', 'redeemed'),
            'tickets as unused_tickets_count' => fn ($q) => $q->where('status', 'unused'),
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('mealDate')) {
            $query->whereDate('mealDate', $request->mealDate);
        }

        $meals = $query
            ->latest('mealDate')
            ->latest('mealId')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $meals,
        ]);
    }

    public function store(StoreMealRequest $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $meal = Meal::create([
            'title' => $request->title,
            'slug' => $this->generateUniqueSlug($request->title),
            'description' => $request->description,
            'mealDate' => $request->mealDate,
            'startTime' => $request->startTime,
            'endTime' => $request->endTime,
            'location' => $request->location,
            'status' => 'draft',
            'ticketCount' => $request->ticketCount,
            'redeemedCount' => 0,
            'createdBy' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Meal created successfully.',
            'data' => $meal,
        ], 201);
    }

    public function show(Meal $meal): JsonResponse
    {
        $meal->loadCount([
            'tickets',
            'tickets as redeemed_tickets_count' => fn ($q) => $q->where('status', 'redeemed'),
            'tickets as unused_tickets_count' => fn ($q) => $q->where('status', 'unused'),
            'tickets as void_tickets_count' => fn ($q) => $q->where('status', 'void'),
        ]);

        return response()->json([
            'success' => true,
            'data' => $meal,
        ]);
    }

    public function update(UpdateMealRequest $request, Meal $meal): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }
        $data = $request->validated();

        if (isset($data['title']) && $data['title'] !== $meal->title) {
            $data['slug'] = $this->generateUniqueSlug($data['title'], $meal->mealId);
        }

        $meal->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Meal updated successfully.',
            'data' => $meal->fresh(),
        ]);
    }

    public function destroy(Request $request, Meal $meal): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($meal->tickets()->where('status', 'redeemed')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a meal with redeemed tickets.',
            ], 422);
        }

        $meal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Meal deleted successfully.',
        ]);
    }

    public function updateStatus(Request $request, Meal $meal): JsonResponse
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

        $meal->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Meal status updated successfully.',
            'data' => $meal->fresh(),
        ]);
    }

    // ==========================================
    // NEW FOOD INVENTORY MANAGEMENT METHODS
    // ==========================================

    /**
     * Record a new food supply from vendor
     */
    public function recordSupply(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        $validated = $request->validate([
            'mealSessionId' => ['required', 'integer', 'exists:meal_sessions,mealSessionId'],
            'foodItem' => ['required', 'string', 'max:255'],
            'vendorName' => ['required', 'string', 'max:255'],
            'quantitySupplied' => ['required', 'integer', 'min:1'],
            'supplyDate' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Verify meal session belongs to active event
        $mealSession = MealSession::where('mealSessionId', $validated['mealSessionId'])
            ->where('eventId', $eventId)
            ->firstOrFail();

        $supply = FoodSupply::create([
            'mealSessionId' => $validated['mealSessionId'],
            'eventId' => $eventId,
            'foodItem' => $validated['foodItem'],
            'vendorName' => $validated['vendorName'],
            'quantitySupplied' => $validated['quantitySupplied'],
            'quantityDistributed' => 0,
            'quantityRemaining' => $validated['quantitySupplied'],
            'supplyDate' => $validated['supplyDate'],
            'notes' => $validated['notes'] ?? null,
            'recordedBy' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Successfully recorded {$validated['quantitySupplied']} units of {$validated['foodItem']}",
            'data' => [
                'supply' => $supply,
            ],
        ]);
    }

    /**
     * Get current food inventory for a meal session or event
     */
    public function getInventory(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        // Check if user is scoped to specific sub_cl
        $subCl = DB::table('sub_cls')
            ->where('userId', auth()->id())
            ->first();

        $isScoped = !is_null($subCl);
        $scopedAttendeeIds = $isScoped
            ? DB::table('attendees')
                ->where('subClId', $subCl->subClId)
                ->where('isRegistered', 1)
                ->pluck('attendeeId')
            : collect();

        // Build query for food supplies
        $query = FoodSupply::query()->where('eventId', $eventId);

        if ($request->filled('mealSessionId')) {
            $query->where('mealSessionId', $request->mealSessionId);
        }

        $supplies = $query
            ->with('mealSession')
            ->orderBy('supplyDate', 'desc')
            ->get();

        // Group by food item
        $inventory = $supplies->groupBy('foodItem')->map(function ($items, $foodItem) use ($isScoped, $scopedAttendeeIds) {
            $mealSessionGroups = $items->groupBy('mealSessionId')->map(function ($sessionItems) use ($isScoped, $scopedAttendeeIds) {
                $session = $sessionItems->first()->mealSession;
                
                // Calculate distributed count scoped to user's attendees if applicable
                $distributedCount = $isScoped
                    ? DB::table('food_distributions')
                        ->whereIn('foodSupplyId', $sessionItems->pluck('supplyId'))
                        ->whereIn('attendeeId', $scopedAttendeeIds)
                        ->count()
                    : $sessionItems->sum('quantityDistributed');

                return [
                    'mealSessionId' => $session->mealSessionId,
                    'mealSessionTitle' => $session->title,
                    'totalSupplied' => $sessionItems->sum('quantitySupplied'),
                    'totalDistributed' => $distributedCount,
                    'totalRemaining' => $sessionItems->sum('quantityRemaining'),
                ];
            })->values();

            // Calculate overall distributed for this food item
            $overallDistributed = $isScoped
                ? DB::table('food_distributions')
                    ->whereIn('foodSupplyId', $items->pluck('supplyId'))
                    ->whereIn('attendeeId', $scopedAttendeeIds)
                    ->count()
                : $items->sum('quantityDistributed');

            return [
                'foodItem' => $foodItem,
                'overallTotal' => $items->sum('quantitySupplied'),
                'overallDistributed' => $overallDistributed,
                'overallRemaining' => $items->sum('quantityRemaining'),
                'bySessions' => $mealSessionGroups,
                'supplies' => $items->map(function ($supply) use ($isScoped, $scopedAttendeeIds) {
                    // Get scoped distributed count for this specific supply
                    $distributedCount = $isScoped
                        ? DB::table('food_distributions')
                            ->where('foodSupplyId', $supply->supplyId)
                            ->whereIn('attendeeId', $scopedAttendeeIds)
                            ->count()
                        : $supply->quantityDistributed;

                    return [
                        'supplyId' => $supply->supplyId,
                        'foodItem' => $supply->foodItem,
                        'vendorName' => $supply->vendorName,
                        'quantitySupplied' => $supply->quantitySupplied,
                        'quantityDistributed' => $distributedCount,
                        'quantityRemaining' => $supply->quantityRemaining,
                        'supplyDate' => $supply->supplyDate,
                        'mealSessionTitle' => $supply->mealSession->title,
                        'notes' => $supply->notes,
                        'createdAt' => $supply->created_at->toISOString(),
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'inventory' => $inventory,
            'scopedToUser' => $isScoped,
        ]);
    }

    /**
     * Get recent food supplies
     */
    public function getRecentSupplies(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        $query = FoodSupply::query()
            ->with('mealSession')
            ->where('eventId', $eventId);

        if ($request->filled('mealSessionId')) {
            $query->where('mealSessionId', $request->mealSessionId);
        }

        $supplies = $query
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($supply) {
                return [
                    'supplyId' => $supply->supplyId,
                    'foodItem' => $supply->foodItem,
                    'vendorName' => $supply->vendorName,
                    'quantitySupplied' => $supply->quantitySupplied,
                    'quantityDistributed' => $supply->quantityDistributed,
                    'quantityRemaining' => $supply->quantityRemaining,
                    'supplyDate' => $supply->supplyDate,
                    'mealSessionId' => $supply->mealSessionId,
                    'mealSessionTitle' => $supply->mealSession->title,
                    'notes' => $supply->notes,
                    'createdAt' => $supply->created_at->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'supplies' => $supplies,
        ]);
    }

    // ==========================================
    // MEAL RATING METHODS
    // ==========================================

    /**
     * Get meal sessions available for rating (anonymous - all active sessions)
     */
    // public function getRateableMeals(Request $request): JsonResponse
    // {
    //     // Get active event
    //     $activeEvent = DB::table('events')
    //         ->where('status', 'active')
    //         ->orderByDesc('created_at')
    //         ->first();

    //     if (!$activeEvent) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'No active event found.',
    //         ], 404);
    //     }

    //     $eventId = $activeEvent->eventId ?? $activeEvent->id;

    //     // Get all meal sessions that have had food distributions
    //     // (meaning they actually served food)
    //     $mealSessions = MealSession::where('eventId', $eventId)
    //         ->whereHas('foodDistributions')
    //         ->with(['foodSupplies'])
    //         ->orderBy('mealDate', 'desc')
    //         ->orderBy('startTime', 'desc')
    //         ->get();

    //     $meals = $mealSessions->map(function ($session) {
    //         $foodSupplies = $session->foodSupplies;
            
    //         return [
    //             'mealSessionId' => $session->mealSessionId,
    //             'mealSessionTitle' => $session->title,
    //             'mealDate' => $session->mealDate,
    //             'foodItems' => $foodSupplies->pluck('foodItem')->unique()->values(),
    //             'vendors' => $foodSupplies->pluck('vendorName')->unique()->values(),
    //         ];
    //     });

    //     return response()->json([
    //         'success' => true,
    //         'meals' => $meals,
    //     ]);
    // }

    public function getRateableMeals(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        // $user = auth()->user();
        
        // Get attendee ID - check if user has attendeeId or use user id
        // $attendeeId = $user->attendeeId ?? $user->id;

        // Verify attendee belongs to active event
        // $attendeeBelongsToEvent = DB::table('event_passes')
        //     ->where('eventId', $eventId)
        //     ->where('attendeeId', $attendeeId)
        //     ->exists();

        // if (!$attendeeBelongsToEvent) {
        //     return response()->json([
        //         'success' => true,
        //         'meals' => [],
        //         'message' => 'You are not registered for the active event.',
        //     ]);
        // }

        // Get meal sessions where user received food but hasn't rated yet
        // $receivedMeals = FoodDistribution::
       $receivedMeals = FoodDistribution::
    where('eventId', $eventId)
    ->with(['mealSession', 'foodSupply'])
    ->latest()
    ->get(); // ✅ MUST be get()

$meals = $receivedMeals
    ->groupBy('mealSessionId')
    ->map(function ($items) {
        $first = $items->first();

        return [
            'mealSessionId' => $first->mealSessionId,
            'mealSessionTitle' => $first->mealSession->title,
            'mealDate' => $first->mealSession->mealDate,
            'foodItems' => $items->pluck('foodSupply.foodItem')->unique()->values(),
            'vendors' => $items->pluck('foodSupply.vendorName')->unique()->values(),
            'distributedAt' => $first->created_at->toISOString(),
        ];
    })
    ->values()
    ->take(1); // 👈 this ensures you only return the latest "group"

        return response()->json([
            'success' => true,
            'meals' => $meals,
        ]);
    }


    /**
     * Top up an existing food supply
     */
    public function topUpSupply(Request $request, int $supplyId): JsonResponse
    {
        $validated = $request->validate([
            'additionalQuantity' => ['required', 'integer', 'min:1'],
        ]);

        $supply = FoodSupply::findOrFail($supplyId);

        // Verify belongs to active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        if ($supply->eventId !== $eventId) {
            return response()->json([
                'success' => false,
                'message' => 'Supply not found in active event.',
            ], 404);
        }

        // Update quantities
        $supply->increment('quantitySupplied', $validated['additionalQuantity']);
        $supply->increment('quantityRemaining', $validated['additionalQuantity']);

        return response()->json([
            'success' => true,
            'message' => "Added {$validated['additionalQuantity']} units to {$supply->foodItem}",
            'data' => $supply->fresh(),
        ]);
    }

    /**
     * Submit a meal rating (anonymous but unique per device)
     */
    public function submitRating(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        $validated = $request->validate([
            'mealSessionId' => ['required', 'integer', 'exists:meal_sessions,mealSessionId'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'deviceFingerprint' => ['nullable', 'string', 'max:255'], // Browser fingerprint for uniqueness
        ]);

        // Verify meal session belongs to active event
        $mealSession = MealSession::where('mealSessionId', $validated['mealSessionId'])
            ->where('eventId', $eventId)
            ->first();

        if (!$mealSession) {
            return response()->json([
                'success' => false,
                'message' => 'Meal session not found for active event.',
            ], 404);
        }

        // Generate unique identifier from device fingerprint + IP + User Agent
        $deviceFingerprint = $validated['deviceFingerprint'] ?? null;
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();
        
        // Create a unique hash for this device/session
        $uniqueIdentifier = hash('sha256', implode('|', [
            $deviceFingerprint,
            $ipAddress,
            $userAgent,
            $validated['mealSessionId']
        ]));

        // Check if this device has already rated this meal session
        $existingRating = MealRating::where('mealSessionId', $validated['mealSessionId'])
            ->where('eventId', $eventId)
            ->where('deviceIdentifier', $uniqueIdentifier)
            ->first();

        if ($existingRating) {
            return response()->json([
                'success' => false,
                'message' => 'You have already rated this meal session from this device.',
            ], 422);
        }

        // Create rating
        $rating = MealRating::create([
            'attendeeId' => null, // Anonymous
            'mealSessionId' => $validated['mealSessionId'],
            'eventId' => $eventId,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'deviceIdentifier' => $uniqueIdentifier,
            'ipAddress' => $ipAddress,
            'userAgent' => substr($userAgent, 0, 500), // Store for analytics
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thank you for your feedback!',
            'data' => $rating,
        ]);
    }

    /**
     * Get participant's own ratings
     */
    public function getMyRatings(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        $user = auth()->user();
        $attendeeId = $user->attendeeId ?? $user->id;

        $ratings = MealRating::where('attendeeId', $attendeeId)
            ->where('eventId', $eventId)
            ->with('mealSession')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($rating) {
                return [
                    'ratingId' => $rating->ratingId,
                    'mealSessionId' => $rating->mealSessionId,
                    'mealSessionTitle' => $rating->mealSession->title,
                    'mealDate' => $rating->mealSession->mealDate,
                    'rating' => $rating->rating,
                    'comment' => $rating->comment,
                    'createdAt' => $rating->created_at->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'ratings' => $ratings,
        ]);
    }

    /**
     * Generate daily distribution and rating report
     */
    public function generateDailyReport(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        $date = $request->input('date', today()->toDateString());

        // Check if user is scoped to specific sub_cl
        $subCl = DB::table('sub_cls')
            ->where('userId', auth()->id())
            ->first();

        $isScoped = !is_null($subCl);
        $scopedAttendeeIds = $isScoped
            ? DB::table('attendees')
                ->where('subClId', $subCl->subClId)
                ->where('isRegistered', 1)
                ->pluck('attendeeId')
            : collect();

        // Get food distributions
        $query = FoodDistribution::query()
            ->whereDate('created_at', $date)
            ->where('eventId', $eventId)
            ->with(['mealSession', 'foodSupply', 'attendee']);

        if ($isScoped) {
            $query->whereIn('attendeeId', $scopedAttendeeIds);
        }

        $distributions = $query->get();

        $summary = [
            'date' => $date,
            'totalDistributed' => $distributions->count(),
            'byFoodItem' => $distributions->groupBy('foodSupply.foodItem')->map(function ($items) {
                return $items->count();
            }),
            'byMealSession' => $distributions->groupBy('mealSession.title')->map(function ($items) {
                return $items->count();
            }),
            'byVendor' => $distributions->groupBy('foodSupply.vendorName')->map(function ($items) {
                return $items->count();
            }),
            'timeline' => $distributions->map(function ($dist) {
                return [
                    'time' => $dist->created_at->format('H:i:s'),
                    'attendeeName' => $dist->attendee->name ?? 'Unknown',
                    'foodItem' => $dist->foodSupply->foodItem,
                    'mealSession' => $dist->mealSession->title,
                    'deviceName' => $dist->deviceName,
                ];
            }),
        ];

        // Get ratings for the date
        $ratingsQuery = MealRating::whereDate('created_at', $date)
            ->where('eventId', $eventId)
            ->with('mealSession');

        if ($isScoped) {
            $ratingsQuery->whereIn('attendeeId', $scopedAttendeeIds);
        }

        $ratings = $ratingsQuery->get();

        $ratingsSummary = $ratings->groupBy('mealSession.title')->map(function ($items) {
            return [
                'count' => $items->count(),
                'averageRating' => round($items->avg('rating'), 2),
                'ratingBreakdown' => $items->groupBy('rating')->map->count(),
                'comments' => $items->where('comment', '!=', null)->pluck('comment')->take(5),
            ];
        });

        return response()->json([
            'success' => true,
            'scopedToUser' => $isScoped,
            'report' => [
                'summary' => $summary,
                'ratings' => $ratingsSummary,
            ],
        ]);
    }

    // ==========================================
    // PRIVATE HELPER METHODS
    // ==========================================

    private function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $counter = 1;

        while (
            Meal::query()
                ->when($ignoreId, fn ($q) => $q->where('mealId', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}