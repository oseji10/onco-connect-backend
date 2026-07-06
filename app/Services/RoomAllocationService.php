<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\RoomAllocation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomAllocationService
{
    public function checkInAttendee(Event $event, Attendee $attendee, string $hotel, string $roomNumber): array
    {
        return DB::transaction(function () use ($event, $attendee, $hotel, $roomNumber) {
            $attendee->refresh();

            $hotel = trim($hotel);
            $roomNumber = trim($roomNumber);

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

            if ($attendee->eventId !== $event->eventId) {
                throw ValidationException::withMessages([
                    'attendeeId' => ['Attendee does not belong to this event.'],
                ]);
            }

            $existingActive = RoomAllocation::where('eventId', $event->eventId)
                ->where('attendeeId', $attendee->attendeeId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($existingActive) {
                return [
                    'success' => false,
                    'message' => 'Attendee has already been checked into a room.',
                    'data' => [
                        'status' => 'already_checked_in',
                        'allocationId' => $existingActive->allocationId,
                        'hotel' => $existingActive->hotel,
                        'roomNumber' => $existingActive->roomNumber,
                    ],
                ];
            }

            $allocation = RoomAllocation::create([
                'eventId' => $event->eventId,
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

            return [
                'success' => true,
                'message' => 'Attendee checked into room successfully.',
                'data' => [
                    'status' => 'checked_in',
                    'allocation' => $allocation,
                ],
            ];
        });
    }

    public function reallocateAttendee(
        Event $event,
        Attendee $attendee,
        string $hotel,
        string $roomNumber,
        string $reason
    ): array {
        return DB::transaction(function () use ($event, $attendee, $hotel, $roomNumber, $reason) {
            $attendee->refresh();

            $hotel = trim($hotel);
            $roomNumber = trim($roomNumber);
            $reason = trim($reason);

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
                    'reason' => ['Reason is required for reallocation.'],
                ]);
            }

            if ($attendee->eventId !== $event->eventId) {
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
                return [
                    'success' => false,
                    'message' => 'Attendee has not been checked into any room yet.',
                    'data' => [
                        'status' => 'not_checked_in',
                    ],
                ];
            }

            $sameHotel = strtolower(trim((string) $currentAllocation->hotel)) === strtolower($hotel);
            $sameRoomNumber = strtolower(trim((string) $currentAllocation->roomNumber)) === strtolower($roomNumber);

            if ($sameHotel && $sameRoomNumber) {
                return [
                    'success' => false,
                    'message' => 'Attendee is already allocated to this hotel and room number.',
                    'data' => [
                        'status' => 'same_room',
                        'hotel' => $currentAllocation->hotel,
                        'roomNumber' => $currentAllocation->roomNumber,
                    ],
                ];
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
                'reason' => $reason,
                'allocatedAt' => now(),
                'allocatedBy' => Auth::id(),
                'previousAllocationId' => $currentAllocation->allocationId,
            ]);

            return [
                'success' => true,
                'message' => 'Room reallocated successfully.',
                'data' => [
                    'status' => 'reallocated',
                    'previousAllocationId' => $currentAllocation->allocationId,
                    'allocation' => $newAllocation,
                ],
            ];
        });
    }
}