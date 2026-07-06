<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventPass;
use App\Models\RoomAllocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomCheckinController extends Controller
{
    protected function ensureSupervisorAccess(): void
    {
        $user = Auth::user();

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        // Adjust this to your real authorization logic
        // if (!in_array($user->role ?? null, ['admin', 'supervisor'])) {
        //     abort(403, 'You are not authorized to perform this action.');
        // }
    }
public function scanLookup(Request $request, Event $event): JsonResponse
{
    $activeEvent = Event::where('status', 'active')->first();

    if (!$activeEvent) {
        return response()->json([
            'success' => false,
            'message' => 'No active event found.',
        ], 404);
    }

    $this->ensureSupervisorAccess();

    $validated = $request->validate([
        'qrValue' => ['required', 'string', 'max:500'],
    ]);

    $qrValue = trim($validated['qrValue']);

    $eventPass = EventPass::where('passCode', $qrValue)->first();

    if (!$eventPass) {
        return response()->json([
            'success' => false,
            'message' => 'No event pass found for this QR code.',
        ], 404);
    }

    $attendee = Attendee::where('eventId', $activeEvent->eventId)
        ->where('attendeeId', $eventPass->attendeeId)
        ->first();

    if (!$attendee) {
        return response()->json([
            'success' => false,
            'message' => 'No attendee found for this QR code.',
        ], 404);
    }

    $existing = RoomAllocation::where('eventId', $event->eventId)
        ->where('attendeeId', $attendee->attendeeId)
        ->where('status', 'active')
        ->latest('allocationId')
        ->first();

    return response()->json([
        'success' => true,
        'message' => $existing
            ? 'Attendee found but already checked in.'
            : 'Attendee found.',
        'data' => [
            'attendee' => [
                'attendeeId' => $attendee->attendeeId,
                'firstName' => $attendee->firstName,
                'lastName' => $attendee->lastName,
                'fullName' => trim(($attendee->fullName ?? '')),
                'name' => trim(($attendee->firstName ?? '') . ' ' . ($attendee->lastName ?? '')),
                'uniqueId' => $attendee->uniqueId,
                'phone' => $attendee->phone,
                'gender' => $attendee->gender,
                'passportUrl' => $attendee->photoUrl,
                'accommodation' => $attendee->accommodation ?? null,
                'color' => $attendee->color ?? null,
            ],
            'alreadyCheckedIn' => (bool) $existing,
            'currentAllocation' => $existing ? [
                'allocationId' => $existing->allocationId,
                'hotel' => $existing->hotel,
                'roomNumber' => $existing->roomNumber,
                'checkedInAt' => $existing->allocatedAt,
                'reason' => $existing->reason,
                'status' => $existing->status,
            ] : null,
        ],
    ]);
}

public function checkin(Request $request, Event $event): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

    if (!$activeEvent) {
        return response()->json([
            'success' => false,
            'message' => 'No active event found.',
        ], 404);
    }
    $eventId = $activeEvent->eventId;
        $this->ensureSupervisorAccess();

        $validated = $request->validate([
            'attendeeId' => ['required', 'integer', 'exists:attendees,attendeeId'],
            'hotel' => ['required', 'string', 'max:255'],
            'roomNumber' => ['required', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($validated) {
            $attendee = Attendee::lockForUpdate()
                ->where('attendeeId', $validated['attendeeId'])
                ->firstOrFail();

                 $activeEvent = Event::where('status', 'active')->first();

    if (!$activeEvent) {
        return response()->json([
            'success' => false,
            'message' => 'No active event found.',
        ], 404);
    }
                $eventId = $activeEvent->eventId;
            if ((int) $attendee->eventId !== (int) $eventId) {
                throw ValidationException::withMessages([
                    'attendeeId' => ['Attendee does not belong to this event.'],
                ]);
            }

            $existing = RoomAllocation::where('eventId', $eventId)
                ->where('attendeeId', $attendee->attendeeId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendee already checked in.',
                    'data' => [
                        'allocationId' => $existing->allocationId,
                        'hotel' => $existing->hotel,
                        'roomNumber' => $existing->roomNumber,
                        'checkedInAt' => $existing->allocatedAt,
                    ],
                ], 422);
            }

            $hotel = trim($validated['hotel']);
            $roomNumber = trim($validated['roomNumber']);

            if ($hotel === '') {
                throw ValidationException::withMessages([
                    'hotel' => ['Hotel is required.'],
                ]);
            }

            if ($roomNumber === '') {
                throw ValidationException::withMessages([
                    'roomNumber' => ['Room number is required.'],
                ]);
            }

            $allocation = RoomAllocation::create([
                'eventId' => $activeEvent->eventId,
                'attendeeId' => $attendee->attendeeId,
                'hotel' => $hotel,
                'roomNumber' => $roomNumber,
                'allocationType' => 'initial',
                'status' => 'active',
                'allocatedAt' => now(),
                'allocatedBy' => Auth::id(),
                'reason' => null,
                'previousAllocationId' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Checked in successfully.',
                'data' => [
                    'allocationId' => $allocation->allocationId,
                    'hotel' => $allocation->hotel,
                    'roomNumber' => $allocation->roomNumber,
                    'allocationType' => $allocation->allocationType,
                    'status' => $allocation->status,
                    'allocatedAt' => $allocation->allocatedAt,
                    'attendee' => [
                        'attendeeId' => $attendee->attendeeId,
                        'fullName' => trim(($attendee->firstName ?? '') . ' ' . ($attendee->lastName ?? '')),
                        'uniqueId' => $attendee->uniqueId,
                    ],
                ],
            ]);
        });
    }

    public function reallocate(Request $request, Event $event): JsonResponse
    {
        $this->ensureSupervisorAccess();

        $validated = $request->validate([
            'attendeeId' => ['required', 'integer', 'exists:attendees,attendeeId'],
            'hotel' => ['required', 'string', 'max:255'],
            'roomNumber' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($validated, $event) {
            $attendee = Attendee::lockForUpdate()
                ->where('attendeeId', $validated['attendeeId'])
                ->firstOrFail();

            if ((int) $attendee->eventId !== (int) $event->eventId) {
                throw ValidationException::withMessages([
                    'attendeeId' => ['Attendee does not belong to this event.'],
                ]);
            }

            $currentAllocation = RoomAllocation::where('eventId', $event->eventId)
                ->where('attendeeId', $attendee->attendeeId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$currentAllocation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendee has not been checked into any room yet.',
                ], 422);
            }

            $hotel = trim($validated['hotel']);
            $roomNumber = trim($validated['roomNumber']);
            $reason = trim($validated['reason']);

            if ($hotel === '') {
                throw ValidationException::withMessages([
                    'hotel' => ['Hotel is required.'],
                ]);
            }

            if ($roomNumber === '') {
                throw ValidationException::withMessages([
                    'roomNumber' => ['Room number is required.'],
                ]);
            }

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => ['Reason is required.'],
                ]);
            }

            $sameHotel = mb_strtolower((string) $currentAllocation->hotel) === mb_strtolower($hotel);
            $sameRoomNumber = mb_strtolower((string) $currentAllocation->roomNumber) === mb_strtolower($roomNumber);

            if ($sameHotel && $sameRoomNumber) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendee is already in this hotel and room number.',
                ], 422);
            }

            $currentAllocation->update([
                'status' => 'moved',
            ]);

            $newAllocation = RoomAllocation::create([
                'eventId' => $event->eventId,
                'attendeeId' => $attendee->attendeeId,
                'hotel' => $hotel,
                'roomNumber' => $roomNumber,
                'allocationType' => 'reallocation',
                'status' => 'active',
                'allocatedAt' => now(),
                'allocatedBy' => Auth::id(),
                'reason' => $reason,
                'previousAllocationId' => $currentAllocation->allocationId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Room reallocated successfully.',
                'data' => [
                    'allocationId' => $newAllocation->allocationId,
                    'previousAllocationId' => $currentAllocation->allocationId,
                    'hotel' => $newAllocation->hotel,
                    'roomNumber' => $newAllocation->roomNumber,
                    'reason' => $newAllocation->reason,
                    'status' => $newAllocation->status,
                    'allocatedAt' => $newAllocation->allocatedAt,
                ],
            ]);
        });
    }
}