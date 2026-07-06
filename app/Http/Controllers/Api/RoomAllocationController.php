<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Room;
use App\Models\RoomAllocation;
use App\Services\RoomAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoomAllocationController extends Controller
{
    public function __construct(
        protected RoomAllocationService $roomAllocationService
    ) {
    }

    protected function ensureSupervisorAccess(): void
    {
        $user = Auth::user();

        if (!$user || !in_array($user->role, ['admin', 'supervisor'])) {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admin or supervisor can manage room allocation.',
            ], 403));
        }
    }

    public function rooms(Event $event): JsonResponse
    {
        $rooms = Room::where('eventId', $event->eventId)
            ->where('status', 'active')
            ->withCount([
                'activeAllocations as occupiedCount',
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Rooms retrieved successfully.',
            'data' => $rooms,
        ]);
    }
public function checkIn(Request $request, Event $event): JsonResponse
{
    $this->ensureSupervisorAccess();

    $validated = $request->validate([
        'attendeeId' => ['required', 'integer', 'exists:attendees,attendeeId'],
        'hotel' => ['required', 'string', 'max:255'],
        'roomNumber' => ['required', 'string', 'max:255'],
    ]);

    $attendee = Attendee::findOrFail($validated['attendeeId']);

    $result = $this->roomAllocationService->checkInAttendee(
        $event,
        $attendee,
        $validated['hotel'],
        $validated['roomNumber']
    );

    return response()->json($result, $result['success'] ? 200 : 422);
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

    $attendee = Attendee::findOrFail($validated['attendeeId']);

    $result = $this->roomAllocationService->reallocateAttendee(
        $event,
        $attendee,
        $validated['hotel'],
        $validated['roomNumber'],
        $validated['reason']
    );

    return response()->json($result, $result['success'] ? 200 : 422);
}
    public function attendeeCurrentRoom(Event $event, Attendee $attendee): JsonResponse
    {
        $allocation = RoomAllocation::with(['room', 'allocator'])
            ->where('eventId', $event->eventId)
            ->where('attendeeId', $attendee->attendeeId)
            ->where('status', 'active')
            ->latest('allocationId')
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Current room allocation retrieved successfully.',
            'data' => $allocation,
        ]);
    }

    public function attendeeAllocationHistory(Event $event, Attendee $attendee): JsonResponse
    {
        $history = RoomAllocation::with(['room', 'allocator', 'previousAllocation'])
            ->where('eventId', $event->eventId)
            ->where('attendeeId', $attendee->attendeeId)
            ->latest('allocationId')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Room allocation history retrieved successfully.',
            'data' => $history,
        ]);
    }

    
}