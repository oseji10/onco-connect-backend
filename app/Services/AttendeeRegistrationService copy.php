<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\EventPass;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendeeRegistrationService
{
    public function assignPassToAttendee(
        Attendee $attendee,
        EventPass $pass,
        ?string $accomodation = null,
        ?string $color = null
    ): array {
        return DB::transaction(function () use ($attendee, $pass, $accomodation, $color) {
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
                'accomodation' => $accomodation,
                'color' => $color,
            ]);

            return [
                'attendee' => $attendee->fresh(['event', 'pass']),
                'pass' => $pass->fresh(['attendee']),
            ];
        });
    }
}




<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\EventPass;
use App\Models\Color;
use App\Models\SubCommunityLeader;
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
                'subCLId' => $colorAssignment['subCLId'],
            ]);

            return [
                'attendee' => $attendee->fresh(['event', 'pass', 'color', 'subCommunityLeader']),
                'pass' => $pass->fresh(['attendee']),
                'color' => $colorAssignment['color'],
                'subCL' => $colorAssignment['subCL'],
            ];
        });
    }

    /**
     * Assign an available color and subcommunity leader to the attendee
     */
    private function assignColorAndSubCL(int $eventId): ?array
    {
        // Get all colors with their capacity and current participant count
        $colors = Color::where('eventId', $eventId)
            ->withCount(['attendees as current_count' => function ($query) {
                $query->where('isRegistered', true);
            }])
            ->having('current_count', '<', DB::raw('capacity'))
            ->orderBy('current_count', 'asc')
            ->get();

        if ($colors->isEmpty()) {
            return null;
        }

        // Get the color with the least participants
        $selectedColor = $colors->first();

        // Get subcommunity leaders for this color with their current participant count
        $subCLs = SubCommunityLeader::where('colorId', $selectedColor->id)
            ->where('eventId', $eventId)
            ->withCount(['attendees as current_count' => function ($query) {
                $query->where('isRegistered', true);
            }])
            ->having('current_count', '<', 13) // Each subCL manages max 13 participants
            ->orderBy('current_count', 'asc')
            ->get();

        if ($subCLs->isEmpty()) {
            // No available subCL in this color, try next color
            $colors = $colors->skip(1);
            if ($colors->isEmpty()) {
                return null;
            }
            
            $selectedColor = $colors->first();
            $subCLs = SubCommunityLeader::where('colorId', $selectedColor->id)
                ->where('eventId', $eventId)
                ->withCount(['attendees as current_count' => function ($query) {
                    $query->where('isRegistered', true);
                }])
                ->having('current_count', '<', 13)
                ->orderBy('current_count', 'asc')
                ->get();

            if ($subCLs->isEmpty()) {
                return null;
            }
        }

        // Get the subCL with the least participants
        $selectedSubCL = $subCLs->first();

        return [
            'colorId' => $selectedColor->id,
            'color' => $selectedColor,
            'subCLId' => $selectedSubCL->id,
            'subCL' => $selectedSubCL,
        ];
    }
}