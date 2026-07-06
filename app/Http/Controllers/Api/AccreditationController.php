<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventPass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AccreditationController extends Controller
{
    /**
     * POST /search
     *
     * Resolve an attendee from a free-form token: a typed Attendee ID / unique
     * ID, or a value scanned from the QR pass (passCode or serialNumber). The
     * front end already strips verify URLs down to their last path segment, so
     * we only need to match the bare token here.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q'       => ['required', 'string'],
            'eventId' => ['nullable', 'integer'],
        ]);

        $q = trim($validated['q']);

        $eventId = $validated['eventId']
            ?? optional(Event::where('status', 'active')->first())->eventId;

        if (!$eventId) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        // 1) Try the pass first — QR codes usually encode passCode or serial.
        $pass = EventPass::where('eventId', $eventId)
            ->where(function ($query) use ($q) {
                $query->where('passCode', $q)
                      ->orWhere('serialNumber', $q);
            })
            ->first();

        $attendee = $pass ? $this->loadAttendee($pass->attendeeId) : null;

        // 2) Fall back to a typed Attendee ID or unique ID.
        if (!$attendee) {
            $attendee = $this->baseAttendeeQuery()
                ->where('eventId', $eventId)
                ->where(function ($query) use ($q) {
                    $query->where('uniqueId', $q);
                    if (ctype_digit($q)) {
                        $query->orWhere('attendeeId', (int) $q);
                    }
                })
                ->first();
        }

        if (!$attendee) {
            return response()->json([
                'success' => false,
                'message' => 'No attendee matches that ID or QR code.',
            ], 404);
        }

        $pass = $pass ?: $attendee->pass;

        return response()->json([
            'success' => true,
            'message' => 'Attendee found.',
            'data'    => [
                'attendee'     => $this->transformAttendee($attendee),
                'assignedPass' => $this->transformPass($pass),
            ],
        ]);
    }

    /**
     * POST /events/{eventId}/accreditations
     *
     * Mark an attendee as accredited (checked in) at the venue.
     */
    public function store(Request $request, int $eventId): JsonResponse
    {
        $validated = $request->validate([
            'attendeeId' => ['required', 'integer'],
        ]);

        $attendee = $this->loadAttendee($validated['attendeeId'], $eventId);

        if (!$attendee) {
            return response()->json([
                'success' => false,
                'message' => 'Attendee not found for this event.',
            ], 404);
        }

        if ($attendee->isAccredited) {
            return response()->json([
                'success' => false,
                'message' => 'This attendee has already been accredited.',
            ], 409);
        }

        $pass = $attendee->pass;

        if (!$pass) {
            return response()->json([
                'success' => false,
                'message' => 'No event pass found for this attendee.',
            ], 422);
        }

        return DB::transaction(function () use ($attendee, $pass) {
            $attendee->update([
                'isAccredited' => true,
                'accreditedAt' => now(),
                'accreditedBy' => Auth::id(),
            ]);

            $attendee->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Attendee accredited successfully.',
                'data'    => [
                    'attendee' => [
                        'attendeeId'   => $attendee->attendeeId,
                        'fullName'     => $this->fullName($attendee),
                        'phone'        => $attendee->phoneNumber,
                        'uniqueId'     => $attendee->uniqueId,
                        'isAccredited' => (bool) $attendee->isAccredited,
                        'accreditedAt' => optional($attendee->accreditedAt)->toIso8601String(),
                    ],
                    'pass' => $this->transformPass($pass),
                ],
            ]);
        });
    }

    /**
     * GET /events/{eventId}/accredited-attendees
     */
    public function index(int $eventId): JsonResponse
    {
        $attendees = $this->baseAttendeeQuery()
            ->where('eventId', $eventId)
            ->where('isAccredited', true)
            ->orderByDesc('accreditedAt')
            ->get()
            ->map(function (Attendee $attendee) {
                return [
                    'attendeeId'   => $attendee->attendeeId,
                    'fullName'     => $this->fullName($attendee),
                    'uniqueId'     => $attendee->uniqueId,
                    'phone'        => $attendee->phoneNumber,
                    'gender'       => $attendee->gender,
                    'serialNumber' => optional($attendee->pass)->serialNumber,
                    'accreditedAt' => optional($attendee->accreditedAt)->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Accredited attendees retrieved successfully.',
            'data'    => [
                'attendees' => $attendees,
            ],
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function baseAttendeeQuery()
    {
        return Attendee::with(['pass']);
    }

    private function loadAttendee(int $attendeeId, ?int $eventId = null): ?Attendee
    {
        $query = $this->baseAttendeeQuery()->where('attendeeId', $attendeeId);

        if ($eventId !== null) {
            $query->where('eventId', $eventId);
        }

        return $query->first();
    }

    private function fullName(Attendee $attendee): string
    {
        return trim(
            ($attendee->firstName ?? '') . ' ' .
            ($attendee->lastName ?? '') . ' ' .
            ($attendee->otherNames ?? '')
        );
    }

    private function transformAttendee(Attendee $attendee): array
    {
        return [
            'attendeeId'   => $attendee->attendeeId,
            'eventId'      => $attendee->eventId,
            'uniqueId'     => $attendee->uniqueId,
            'fullName'     => $this->fullName($attendee),
            'phone'        => $attendee->phoneNumber,
            'email'        => $attendee->email,
            'organization' => $attendee->organizationName,
            'gender'       => $attendee->gender,
            'category'     => $attendee->category,
            'age'          => $attendee->age ?? null,
            'state'        => $attendee->stateOfResidence,
            'lga'          => $attendee->lga ?? null,
            'ward'         => $attendee->ward ?? null,
            'photoUrl'     => $attendee->photoUrl,
            // 'photoUrl'     => $this->photoUrl($attendee->photoUrl),
            // Presence in the table means they registered online.
            'isRegistered' => true,
            'registeredAt' => optional($attendee->created_at)->toIso8601String(),
            'isAccredited' => (bool) $attendee->isAccredited,
            'accreditedAt' => optional($attendee->accreditedAt)->toIso8601String(),
            'accreditedBy' => $attendee->accreditedBy,
            'createdAt'    => optional($attendee->created_at)->toIso8601String(),
            'updatedAt'    => optional($attendee->updated_at)->toIso8601String(),
        ];
    }

    private function transformPass($pass): ?array
    {
        if (!$pass) {
            return null;
        }

        return [
            'passId'       => $pass->passId,
            'serialNumber' => $pass->serialNumber,
            'status'       => $pass->status,
            'assignedAt'   => optional($pass->created_at)->toIso8601String(),
        ];
    }

    private function photoUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}