<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventPass;
use App\Models\MealRedemption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * VIPs are ordinary attendee records with a flag. No login, no email, no QR needed.
 * They are accredited automatically and served from the scanner's "Find person" tab.
 */
class VipController extends Controller
{
    private function event(): ?Event
    {
        return Event::where('status', 'active')->first();
    }

    private function noEvent(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'No active event found.'], 404);
    }

    public function index(): JsonResponse
    {
        $event = $this->event();
        if (!$event) return $this->noEvent();

        $vips = Attendee::where('eventId', $event->eventId)
            ->where('isVip', true)
            ->with('pass')
            ->orderBy('lastName')->orderBy('firstName')
            ->get()
            ->map(fn (Attendee $a) => [
                'attendeeId'   => $a->attendeeId,
                'fullName'     => trim(implode(' ', array_filter([$a->title, $a->firstName, $a->lastName]))),
                'organization' => $a->organizationName,
                'guests'       => (int) ($a->vipGuests ?? 0),
                'uniqueId'     => $a->uniqueId,
                'serialNumber' => $a->pass?->serialNumber,
            ])
            ->values();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => ['vips' => $vips]]);
    }

    public function store(Request $request): JsonResponse
    {
        $event = $this->event();
        if (!$event) return $this->noEvent();

        $data = $request->validate([
            'title'        => ['nullable', 'string', 'max:50'],
            'firstName'    => ['required', 'string', 'max:100'],
            'lastName'     => ['required', 'string', 'max:100'],
            'organization' => ['nullable', 'string', 'max:255'],
            'guests'       => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);

        $vip = DB::transaction(function () use ($event, $data) {
            $n = Attendee::where('eventId', $event->eventId)->where('isVip', true)->count() + 1;
            $seq = str_pad((string) $n, 3, '0', STR_PAD_LEFT);

            $attendee = new Attendee();
            $attendee->forceFill([
                'eventId'              => $event->eventId,
                'title'                => trim($data['title'] ?? ''),
                'firstName'            => trim($data['firstName']),
                'lastName'             => trim($data['lastName']),
                'organizationName'     => $data['organization'] ?? null,
                'category'             => 'other',
                'participationType'    => 'Physical',
                'physicallyChallenged' => false,
                'uniqueId'             => 'VIP-' . now()->format('Ymd') . '-' . $seq,
                'registeredBy'         => Auth::id(),
                'isAccredited'         => true,
                'accreditedAt'         => now(),
                'accreditedBy'         => Auth::id(),
                'isVip'                => true,
                'vipGuests'            => (int) ($data['guests'] ?? 0),
            ])->save();

            // A pass record lets the normal meal/attendance logic work. No QR is generated or sent.
            EventPass::create([
                'eventId'      => $event->eventId,
                'attendeeId'   => $attendee->getKey(),
                'passCode'     => bin2hex(random_bytes(16)),
                'serialNumber' => 'VIP-' . $seq,
                'status'       => 'active',
            ]);

            return $attendee;
        });

        return response()->json([
            'success' => true,
            'message' => 'VIP added. Serve them from Scanner > Find person.',
            'data'    => ['attendeeId' => $vip->getKey()],
        ], 201);
    }

    public function destroy(int $attendeeId): JsonResponse
    {
        $event = $this->event();
        if (!$event) return $this->noEvent();

        $vip = Attendee::where('eventId', $event->eventId)->where('attendeeId', $attendeeId)->where('isVip', true)->first();

        if (!$vip) {
            return response()->json(['success' => false, 'message' => 'VIP not found.'], 404);
        }

        $passIds = EventPass::where('attendeeId', $attendeeId)->pluck('passId');

        $hasHistory = AttendanceRecord::where('attendeeId', $attendeeId)->exists()
            || MealRedemption::whereIn('passId', $passIds)->exists();

        if ($hasHistory) {
            return response()->json([
                'success' => false,
                'message' => 'This VIP already has meal or attendance records, so they cannot be deleted.',
            ], 422);
        }

        DB::transaction(function () use ($vip, $attendeeId) {
            EventPass::where('attendeeId', $attendeeId)->delete();
            $vip->delete();
        });

        return response()->json(['success' => true, 'message' => 'VIP removed.']);
    }
}
