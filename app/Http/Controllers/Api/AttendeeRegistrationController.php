<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventPass;
use App\Services\AttendeeRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;



class AttendeeRegistrationController extends Controller
{
    public function __construct(
        protected AttendeeRegistrationService $attendeeRegistrationService
    ) {
    }

    /**
     * Get scoped attendee IDs for the logged-in user
     */
    protected function getScopedAttendeeIds(): array
    {
        $subCl = DB::table('sub_cls')
            ->where('userId', auth()->id())
            ->first();

        if (!$subCl) {
            return ['isScoped' => false, 'attendeeIds' => collect()];
        }

        $attendeeIds = DB::table('attendees')
            ->where('subClId', $subCl->subClId)
            ->where('isRegistered', 1)
            ->pluck('attendeeId');

        return ['isScoped' => true, 'attendeeIds' => $attendeeIds];
    }

    /**
     * Search attendee by phone or unique ID
     */
    public function search(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string'],
        ]);

        $query = $validated['q'];

        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }
        
        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        // ── Scoped user filter ────────────────────────────────────────────────
        $scope = $this->getScopedAttendeeIds();
        $isScoped = $scope['isScoped'];
        $scopedAttendeeIds = $scope['attendeeIds'];

        $attendee = Attendee::with('pass')
            ->where('eventId', $eventId)
            ->where(function ($q) use ($query) {
                $q->where('phone', $query)
                  ->orWhere('uniqueId', $query)
                  ->orWhere('uniqueId', 'LIKE', "%{$query}"); 
            })
            ->when($isScoped, fn ($q) => $q->whereIn('attendeeId', $scopedAttendeeIds))
            ->first();

        if (!$attendee) {
            return response()->json([
                'success' => false,
                'message' => 'Attendee not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Attendee found.',
            'data' => [
                'attendee' => $this->formatAttendee($attendee),
                'assignedPass' => $attendee->pass ? $this->formatPass($attendee->pass) : null,
            ],
        ]);
    }

    /**
     * Verify QR serial before assignment
     */
    public function verifyPass(Request $request, Event $event): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }
        
        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        $validated = $request->validate([
            'serialNumber' => ['required', 'string', 'max:100'],
        ]);

        // ── Scoped user filter ────────────────────────────────────────────────
        $scope = $this->getScopedAttendeeIds();
        $isScoped = $scope['isScoped'];
        $scopedAttendeeIds = $scope['attendeeIds'];

        $pass = EventPass::with(['attendee' => function ($query) use ($isScoped, $scopedAttendeeIds) {
                $query->when($isScoped, fn ($q) => $q->whereIn('attendeeId', $scopedAttendeeIds));
            }])
            ->where('eventId', $eventId)
            ->where('serialNumber', $validated['serialNumber'])
            ->first();

        if (!$pass) {
            return response()->json([
                'success' => false,
                'message' => 'QR code serial number not found.',
            ], 404);
        }

        // If scoped and the attendee is not in scope, hide attendee info
        $attendeeData = null;
        if ($pass->attendee) {
            if (!$isScoped || $scopedAttendeeIds->contains($pass->attendee->attendeeId)) {
                $attendeeData = $this->formatAttendee($pass->attendee);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'QR code found.',
            'data' => [
                'pass' => $this->formatPass($pass),
                'attendee' => $attendeeData,
            ],
        ]);
    }

    /**
     * Final registration: tie attendee to a printed QR code
     */
    public function register(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'attendeeId' => ['required', 'integer'],
            'serialNumber' => ['required', 'string', 'max:100'],
        ]);

        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }
        
        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        // ── Scoped user filter ────────────────────────────────────────────────
        $scope = $this->getScopedAttendeeIds();
        $isScoped = $scope['isScoped'];
        $scopedAttendeeIds = $scope['attendeeIds'];

        $attendee = Attendee::where('eventId', $eventId)
            ->where('attendeeId', $validated['attendeeId'])
            ->when($isScoped, fn ($q) => $q->whereIn('attendeeId', $scopedAttendeeIds))
            ->first();

        if (!$attendee) {
            return response()->json([
                'success' => false,
                'message' => 'Attendee not found for this event.',
            ], 404);
        }

        $pass = EventPass::where('eventId', $eventId)
            ->where('serialNumber', $validated['serialNumber'])
            ->first();

        if (!$pass) {
            return response()->json([
                'success' => false,
                'message' => 'QR code serial number not found for this event.',
            ], 404);
        }

        try {
            $result = $this->attendeeRegistrationService->assignPassToAttendee(
                $attendee,
                $pass,
                $request->accommodation
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Attendee registered successfully.',
                'data' => [
                    'attendee' => $this->formatAttendee($result['attendee']),
                    'pass' => $this->formatPass($result['pass']),
                    'color' => $result['color'],
                    'subCL' => $result['subCL'],
                ],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    protected function formatAttendee(Attendee $attendee): array
    {
        return [
            'attendeeId' => $attendee->attendeeId,
            'eventId' => $attendee->eventId,
            'uniqueId' => $attendee->uniqueId,
            'fullName' => $attendee->fullName,
            'phone' => $attendee->phone,
            'email' => $attendee->email,
            'organization' => $attendee->organization,
            'gender' => $attendee->gender,
            'category' => $attendee->category,

            'age' => $attendee->age,
            'state' => $attendee->state,
            'lga' => $attendee->lga,
            'ward' => $attendee->ward,
            'community' => $attendee->community,
            'religion' => $attendee->religion,
            'bank' => $attendee->bank,
            'accountName' => $attendee->accountName,
            'accountNumber' => $attendee->accountNumber,
            'photoUrl' => $attendee->photoUrl,

            'isRegistered' => (bool) $attendee->isRegistered,
            'registeredAt' => optional($attendee->registeredAt)?->toDateTimeString(),
            'registeredBy' => $attendee->registeredBy,
            'createdAt' => optional($attendee->created_at)?->toDateTimeString(),
            'updatedAt' => optional($attendee->updated_at)?->toDateTimeString(),
        ];
    }

    protected function formatPass(EventPass $pass): array
    {
        return [
            'passId' => $pass->passId,
            'eventId' => $pass->eventId,
            'attendeeId' => $pass->attendeeId,
            'serialNumber' => $pass->serialNumber,
            'status' => $pass->status,
            'isAssigned' => (bool) $pass->isAssigned,
            'assignedAt' => optional($pass->assignedAt)?->toDateTimeString(),
            'assignedBy' => $pass->assignedBy,
            'createdAt' => optional($pass->created_at)?->toDateTimeString(),
            'updatedAt' => optional($pass->updated_at)?->toDateTimeString(),
        ];
    }

    public function registeredAttendees(): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        // ── Scoped user filter ────────────────────────────────────────────────
        $scope = $this->getScopedAttendeeIds();
        $isScoped = $scope['isScoped'];
        $scopedAttendeeIds = $scope['attendeeIds'];

        $attendees = Attendee::query()
            ->with('pass', 'group_color', 'subCommunityLead.user')
            ->where('eventId', $activeEvent->eventId)
            ->where('isRegistered', true)
            ->when($isScoped, fn ($q) => $q->whereIn('attendeeId', $scopedAttendeeIds))
            ->orderByDesc('registeredAt')
            ->get()
            ->map(function (Attendee $attendee) {
                return [
                    'attendeeId' => $attendee->attendeeId,
                    'fullName' => $attendee->fullName,
                    'uniqueId' => $attendee->uniqueId,
                    'phone' => $attendee->phone,
                    'gender' => $attendee->gender,
                    'accommodation' => $attendee->accommodation,
                    'color' => $attendee->group_color?->colorName,
                    'colorHex' => $attendee->group_color?->hexCode,
                    'subcl' => $attendee->subCommunityLead?->user?->firstName . ' ' . $attendee->subCommunityLead?->user?->lastName,
                    'serialNumber' => $attendee->pass?->serialNumber,
                    'registeredAt' => $attendee->registeredAt?->format('Y-m-d H:i:s'),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Registered attendees retrieved successfully.',
            'data' => [
                'event' => $activeEvent->name ?? null,
                'attendees' => $attendees,
            ],
        ]);
    }

    public function registeredAttendees2(Request $request, Event $event): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $search = trim((string) $request->query('search', ''));

        // ── Scoped user filter ────────────────────────────────────────────────
        $scope = $this->getScopedAttendeeIds();
        $isScoped = $scope['isScoped'];
        $scopedAttendeeIds = $scope['attendeeIds'];

        $query = Attendee::with([
                'currentRoomAllocation',
                'colors'
            ])
            ->where('eventId', $activeEvent->eventId)
            ->when($isScoped, fn ($q) => $q->whereIn('attendeeId', $scopedAttendeeIds));

        if ($search !== '') {
    $query->where(function ($q) use ($search) {
        $q->where('phone', 'LIKE', "%{$search}%")
            ->orWhere('uniqueId', 'LIKE', "%{$search}%")
            ->orWhereRaw(
                "CONCAT(COALESCE(fullName, '')) LIKE ?",
                ["%{$search}%"]
            )
            ->orWhereHas('color', function ($q2) use ($search) {
                $q2->where('colorName', 'LIKE', "%{$search}%");
            }); // 👈 THIS is the key
    });
}

        $attendees = $query
            ->orderBy('fullName')
            ->get()
            ->map(function ($attendee) {
                $allocation = $attendee->currentRoomAllocation;

                return [
                    'attendeeId' => $attendee->attendeeId,
                    'fullName' => trim(($attendee->fullName ?? '')),
                    'uniqueId' => $attendee->uniqueId,
                    'phone' => $attendee->phone,
                    'gender' => $attendee->gender,
                    'passportUrl' => $attendee->passportUrl ?? null,
                    'accommodation' => $attendee->accommodation ?? null,
                    'color' => $attendee->color ?? null,

                    'currentRoomAllocation' => $allocation ? [
                        'allocationId' => $allocation->allocationId,
                        'hotel' => $allocation->accommodation,
                        'roomNumber' => $allocation->roomId,
                        'checkedInAt' => optional($allocation->allocatedAt)->toDateTimeString(),
                        'reason' => $allocation->reason,
                        'status' => $allocation->status,
                    ] : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Registered attendees retrieved successfully.',
            'data' => $attendees,
        ]);
    }

    public function show(Event $event, $attendeeId): JsonResponse
    {
        // ── Scoped user filter ────────────────────────────────────────────────
        $scope = $this->getScopedAttendeeIds();
        $isScoped = $scope['isScoped'];
        $scopedAttendeeIds = $scope['attendeeIds'];

        $event_pass = EventPass::where('serialNumber', $attendeeId)->first();
        
        if (!$event_pass) {
            return response()->json([
                'success' => false,
                'message' => 'Pass not found',
            ], 404);
        }

        $attendee = Attendee::where('eventId', $event->eventId)
            ->where('attendeeId', $event_pass->attendeeId)
            ->when($isScoped, fn ($q) => $q->whereIn('attendeeId', $scopedAttendeeIds))
            ->first();

        if (!$attendee) {
            return response()->json([
                'success' => false,
                'message' => 'Attendee not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $attendee,
        ]);
    }
}