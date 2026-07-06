<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $rooms = Room::where('eventId', $activeEvent->eventId)
            ->select([
                'roomId',
                'name',
                'code',
                'building',
                'floor',
                'capacity',
            ])
            ->orderBy('building')
            ->orderBy('name')
            ->get()
            ->map(function ($room) {
                return [
                    'roomId' => $room->roomId,
                    'name' => $room->name,
                    'code' => $room->code,
                    'building' => $room->building,
                    'floor' => $room->floor ?? null,
                    'capacity' => $room->capacity ?? null,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Rooms retrieved successfully.',
            'data' => $rooms,
        ]);
    }
}