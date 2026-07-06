<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\EventPass;
use App\Models\Color;
use App\Models\Event;
use App\Models\SubCL;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendeeRegistrationService
{
    public function assignPassToAttendee(
        Attendee $attendee,
        EventPass $pass,
        ?string $accommodation = null
    ): array {
        return DB::transaction(function () use ($attendee, $pass, $accommodation) {
            $attendee->refresh();
            $pass->refresh();

            if ($attendee->eventId !== $pass->eventId) {
                throw ValidationException::withMessages([
                    'serialNumber' => ['Selected QR code does not belong to this event.'],
                ]);
            }

            if ($pass->isAssigned || !empty($pass->attendeeId)) {
                throw ValidationException::withMessages([
                    'serialNumber' => ['This QR code has already been assigned.'],
                ]);
            }

            $existingPass = EventPass::where('eventId', $attendee->eventId)
                ->where('attendeeId', $attendee->attendeeId)
                ->first();

            if ($existingPass) {
                throw ValidationException::withMessages([
                    'attendee' => ['This attendee already has an assigned QR code.'],
                ]);
            }

            // Assign color and subcommunity leader
            $colorAssignment = $this->assignColorAndSubCL($attendee->eventId);
            
            if (!$colorAssignment) {
                throw ValidationException::withMessages([
                    'capacity' => ['No available colors. All color groups are at full capacity.'],
                ]);
            }

            $now = now();
            $userId = Auth::id();

            $pass->update([
                'attendeeId' => $attendee->attendeeId,
                'isAssigned' => true,
                'assignedAt' => $now,
                'assignedBy' => $userId,
                'status' => 'active',
            ]);

            $attendee->update([
                'isRegistered' => true,
                'registeredAt' => $now,
                'registeredBy' => $userId,
                'accommodation' => $accommodation,
                'colorId' => $colorAssignment['colorId'],
                'subClId' => $colorAssignment['subCLId'],
            ]);

            return [
                'attendee' => $attendee->fresh(['event', 'pass', 'color', 'subCommunityLead']),
                'pass' => $pass->fresh(['attendee']),
                'color' => $colorAssignment['color'],
                'subCL' => $colorAssignment['subCL'],
            ];
        });
    }

    /**
     * Assign an available color and subcommunity leader to the attendee
     */
    // private function assignColorAndSubCL(int $eventId): ?array
    // {
        
    //     // Get all colors with their capacity and current participant count
    //     $colors = Color::where('eventId', $eventId)
    //         ->withCount(['attendees as current_count' => function ($query) {
    //             $query->where('isRegistered', true);
    //         }])
    //         ->having('current_count', '<', DB::raw('capacity'))
    //         ->orderBy('current_count', 'asc')
    //         ->get();

    //     if ($colors->isEmpty()) {
    //         return null;
    //     }

    //     // Get the color with the least participants
    //     $selectedColor = $colors->first();

    //     // Get subcommunity leaders for this color with their current participant count
    //     $subCLs = SubCL::where('colorId', $selectedColor->colorId)
    //         ->where('eventId', $eventId)
    //         ->withCount(['attendees as current_count' => function ($query) {
    //             $query->where('isRegistered', true);
    //         }])
    //         ->having('current_count', '<', 13) // Each subCL manages max 13 participants
    //         ->orderBy('current_count', 'asc')
    //         ->get();

    //     if ($subCLs->isEmpty()) {
    //         // No available subCL in this color, try next color
    //         $colors = $colors->skip(1);
    //         if ($colors->isEmpty()) {
    //             return null;
    //         }
            
    //         $selectedColor = $colors->first();
    //         $subCLs = SubCL::where('colorId', $selectedColor->colorId)
    //             ->where('eventId', $eventId)
    //             ->withCount(['attendees as current_count' => function ($query) {
    //                 $query->where('isRegistered', true);
    //             }])
    //             ->having('current_count', '<', 13)
    //             ->orderBy('current_count', 'asc')
    //             ->get();

    //         if ($subCLs->isEmpty()) {
    //             return null;
    //         }
    //     }

    //     // Get the subCL with the least participants
    //     $selectedSubCL = $subCLs->first();

    //     return [
    //         'colorId' => $selectedColor->colorId,
    //         'color' => $selectedColor,
    //         'subCLId' => $selectedSubCL->subClId,
    //         'subCL' => $selectedSubCL,
    //     ];
    // }


private function assignColorAndSubCL()
{
    $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }
        $eventId = $activeEvent->eventId;
    // Get all colors for this event ordered by ID (or any consistent order)
    $colors = Color::where('eventId', $eventId)
        ->withCount(['attendees as current_count' => function ($query) {
            $query->where('isRegistered', true);
        }])
        ->having('current_count', '<', DB::raw('capacity'))
        ->orderBy('colorId', 'asc') // Consistent ordering
        ->get();

    if ($colors->isEmpty()) {
        return null;
    }

    // Try each color in order until we find one with available subCL capacity
    foreach ($colors as $color) {
        // Get available subCLs for this color
        $subCLs = SubCL::where('colorId', $color->colorId)
            ->where('eventId', $eventId)
            ->withCount(['attendees as current_count' => function ($query) {
                $query->where('isRegistered', true);
            }])
            ->having('current_count', '<', DB::raw('maxCapacity')) // Use dynamic maxCapacity from SubCL table
            ->orderBy('subClId', 'asc') // Fill subCLs in order
            ->get();

        if ($subCLs->isNotEmpty()) {
            // Found an available subCL
            $selectedSubCL = $subCLs->first();

            return [
                'colorId' => $color->colorId,
                'color' => $color,
                'subCLId' => $selectedSubCL->subClId,
                'subCL' => $selectedSubCL,
            ];
        }
    }

    // No available capacity found
    return null;
}
    }