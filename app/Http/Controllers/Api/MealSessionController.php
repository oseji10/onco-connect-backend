<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\MealRedemption;
use App\Models\MealSession;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Meal sessions for the ACTIVE event. No food items, no vendors:
 * a session is just a title and a time window that people are scanned into.
 */
class MealSessionController extends Controller
{
    public function index(): JsonResponse
    {
        $event = Event::where('status', 'active')->first();
        if (!$event) return $this->noEvent();

        $sessions = MealSession::where('eventId', $event->eventId)
            ->withCount('redemptions')
            ->orderBy('mealDate')
            ->orderBy('startTime')
            ->get()
            ->map(fn (MealSession $s) => $this->transform($s));

        return response()->json([
            'success' => true,
            'message' => 'Meal sessions retrieved.',
            'data'    => [
                'headcount' => [
                    'registered'         => Attendee::where('eventId', $event->eventId)->count(),
                    'inPersonRegistered' => Attendee::where('eventId', $event->eventId)->where('participationType', 'Physical')->count(),
                    'accredited'         => Attendee::where('eventId', $event->eventId)->where('isAccredited', true)->count(),
                ],
                'sessions'  => $sessions,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $event = Event::where('status', 'active')->first();
        if (!$event) return $this->noEvent();

        $data = $this->validated($request);

        if ($error = $this->outsideEventDates($event, $data['mealDate'])) return $error;

        $session = MealSession::create([
            'eventId'       => $event->eventId,
            'title'         => $data['title'],
            'slug'          => $this->uniqueSlug($data['title']),
            'mealDate'      => $data['mealDate'],
            'startTime'     => $data['startTime'],
            'endTime'       => $data['endTime'],
            'location'      => $data['location'] ?? null,
            'status'        => 'draft',
            'sortOrder'     => 0,
            'redeemedCount' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Meal session created.',
            'data'    => $this->transform($session),
        ], 201);
    }

    public function update(Request $request, MealSession $mealSession): JsonResponse
    {
        $data  = $this->validated($request);
        $event = Event::where('eventId', $mealSession->eventId)->first();

        if ($event && ($error = $this->outsideEventDates($event, $data['mealDate']))) return $error;

        $payload = [
            'title'     => $data['title'],
            'mealDate'  => $data['mealDate'],
            'startTime' => $data['startTime'],
            'endTime'   => $data['endTime'],
            'location'  => $data['location'] ?? null,
        ];

        if ($data['title'] !== $mealSession->title) {
            $payload['slug'] = $this->uniqueSlug($data['title'], $mealSession->mealSessionId);
        }

        $mealSession->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'Meal session updated.',
            'data'    => $this->transform($mealSession->fresh()->loadCount('redemptions')),
        ]);
    }

    /** Opening a session closes any other open session for the same event. */
    public function updateStatus(Request $request, MealSession $mealSession): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:draft,active,closed']]);

        DB::transaction(function () use ($mealSession, $data) {
            if ($data['status'] === 'active') {
                MealSession::where('eventId', $mealSession->eventId)
                    ->where('mealSessionId', '!=', $mealSession->mealSessionId)
                    ->where('status', 'active')
                    ->update(['status' => 'closed']);
            }

            $mealSession->update(['status' => $data['status']]);
        });

        return response()->json([
            'success' => true,
            'message' => $data['status'] === 'active' ? 'Service opened.' : 'Status updated.',
        ]);
    }

    public function destroy(MealSession $mealSession): JsonResponse
    {
        if ($mealSession->redemptions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a session that already has entries. Close it instead.',
            ], 422);
        }

        $mealSession->delete();

        return response()->json(['success' => true, 'message' => 'Meal session deleted.']);
    }

    /** GET /meal-sessions/{id}/redemptions?search=&page= : who has been let in. */
    public function redemptions(Request $request, MealSession $mealSession): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $query = MealRedemption::query()
            ->where('meal_redemptions.mealSessionId', $mealSession->mealSessionId)
            ->join('event_passes', 'event_passes.passId', '=', 'meal_redemptions.passId')
            ->join('attendees', 'attendees.attendeeId', '=', 'event_passes.attendeeId')
            ->select([
                'meal_redemptions.redemptionId',
                'meal_redemptions.redeemedAt',
                'meal_redemptions.deviceName',
                'event_passes.serialNumber',
                'attendees.title',
                'attendees.firstName',
                'attendees.lastName',
                'attendees.otherNames',
                'attendees.uniqueId',
                'attendees.category',
            ])
            ->orderByDesc('meal_redemptions.redeemedAt');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('attendees.firstName', 'like', "%{$search}%")
                  ->orWhere('attendees.lastName', 'like', "%{$search}%")
                  ->orWhere('attendees.uniqueId', 'like', "%{$search}%")
                  ->orWhere('event_passes.serialNumber', 'like', "%{$search}%");
            });
        }

        $page = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Entries retrieved.',
            'data'    => [
                'total'   => $page->total(),
                'entries' => $page->getCollection()->map(fn ($r) => [
                    'redemptionId' => $r->redemptionId,
                    'fullName'     => trim(implode(' ', array_filter([$r->title, $r->firstName, $r->lastName, $r->otherNames]))),
                    'uniqueId'     => $r->uniqueId,
                    'serialNumber' => $r->serialNumber,
                    'category'     => $r->category,
                    'deviceName'   => $r->deviceName,
                    'redeemedAt'   => Carbon::parse($r->redeemedAt)->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    /** Undo a mistaken scan so the person can be scanned in again. */
    public function destroyRedemption(MealSession $mealSession, int $redemptionId): JsonResponse
    {
        $deleted = MealRedemption::where('mealSessionId', $mealSession->mealSessionId)
            ->where('redemptionId', $redemptionId)
            ->delete();

        if (!$deleted) {
            return response()->json(['success' => false, 'message' => 'Entry not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Entry removed. They can be scanned again.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'mealDate'  => ['required', 'date'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime'   => ['required', 'date_format:H:i', 'after:startTime'],
            'location'  => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function outsideEventDates(Event $event, string $date): ?JsonResponse
    {
        if (!$event->startDate || !$event->endDate) return null;

        $d = Carbon::parse($date);

        if ($d->lt(Carbon::parse($event->startDate)->startOfDay()) || $d->gt(Carbon::parse($event->endDate)->endOfDay())) {
            return response()->json([
                'success' => false,
                'message' => 'Meal date must fall within the event dates.',
            ], 422);
        }

        return null;
    }

    private function transform(MealSession $s): array
    {
        return [
            'mealSessionId' => $s->mealSessionId,
            'title'         => $s->title,
            'mealDate'      => Carbon::parse($s->mealDate)->toDateString(),
            'startTime'     => substr((string) $s->startTime, 0, 5),
            'endTime'       => substr((string) $s->endTime, 0, 5),
            'location'      => $s->location,
            'status'        => $s->status,
            'redeemedCount' => (int) ($s->redemptions_count ?? $s->redemptions()->count()),
        ];
    }

    private function noEvent(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'No active event found.'], 404);
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'meal';
        $slug = $base;
        $i = 1;

        while (
            MealSession::query()
                ->when($ignoreId, fn ($q) => $q->where('mealSessionId', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
