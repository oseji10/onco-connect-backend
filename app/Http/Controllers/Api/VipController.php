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
 * VIPs are ordinary attendee records with a flag. No login, no email needed.
 * Each "+n guest" is its own attendee (isVip = true, vipHostId = host) with its own
 * EventPass, so every guest has an individual printable pass and individual meal/attendance tracking.
 * The VIP list shows hosts only (vipHostId IS NULL); guests are nested under them.
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

    private function fullName(Attendee $a): string
    {
        return trim(implode(' ', array_filter([$a->title, $a->firstName, $a->lastName])));
    }

    private function person(Attendee $a): array
    {
        return [
            'attendeeId'   => $a->attendeeId,
            'fullName'     => $this->fullName($a),
            'organization' => $a->organizationName,
            'uniqueId'     => $a->uniqueId,
            'serialNumber' => $a->pass?->serialNumber,
        ];
    }

    private function createPerson(Event $event, array $attrs, string $serial): Attendee
    {
        $attendee = new Attendee();
        $attendee->forceFill(array_merge([
            'eventId'              => $event->eventId,
            'title'                => '',
            'category'             => 'other',
            'participationType'    => 'Physical',
            'physicallyChallenged' => false,
            'registeredBy'         => Auth::id(),
            'isAccredited'         => true,
            'accreditedAt'         => now(),
            'accreditedBy'         => Auth::id(),
            'isVip'                => true,
        ], $attrs))->save();

        // A pass record lets the normal meal/attendance logic work.
        EventPass::create([
            'eventId'      => $event->eventId,
            'attendeeId'   => $attendee->getKey(),
            'passCode'     => bin2hex(random_bytes(16)),
            'serialNumber' => $serial,
            'status'       => 'active',
        ]);

        return $attendee;
    }

    public function index(): JsonResponse
    {
        $event = $this->event();
        if (!$event) return $this->noEvent();

        $vips = Attendee::where('eventId', $event->eventId)
            ->where('isVip', true)
            ->whereNull('vipHostId')
            ->with(['pass', 'guests.pass'])
            ->orderBy('lastName')->orderBy('firstName')
            ->get()
            ->map(fn (Attendee $a) => $this->person($a) + [
                'guests'    => $a->guests->count(),
                'guestList' => $a->guests->map(fn (Attendee $g) => $this->person($g))->values(),
            ])
            ->values();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => ['vips' => $vips]]);
    }

    public function store(Request $request): JsonResponse
    {
        $event = $this->event();
        if (!$event) return $this->noEvent();

        $data = $request->validate([
            'title'         => ['nullable', 'string', 'max:50'],
            'firstName'     => ['required', 'string', 'max:100'],
            'lastName'      => ['required', 'string', 'max:100'],
            'organization'  => ['nullable', 'string', 'max:255'],
            'guests'        => ['nullable', 'integer', 'min:0', 'max:20'],
            'guestNames'    => ['nullable', 'array', 'max:20'],
            'guestNames.*'  => ['nullable', 'string', 'max:150'],
        ]);

        $guestCount = (int) ($data['guests'] ?? 0);
        $guestNames = $data['guestNames'] ?? [];

        $vip = DB::transaction(function () use ($event, $data, $guestCount, $guestNames) {
            $n   = Attendee::where('eventId', $event->eventId)->where('isVip', true)->whereNull('vipHostId')->count() + 1;
            $seq = str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            $day = now()->format('Ymd');

            $host = $this->createPerson($event, [
                'title'            => trim($data['title'] ?? ''),
                'firstName'        => trim($data['firstName']),
                'lastName'         => trim($data['lastName']),
                'organizationName' => $data['organization'] ?? null,
                'uniqueId'         => "VIP-{$day}-{$seq}",
                'vipGuests'        => $guestCount,
            ], "VIP-{$seq}");

            for ($i = 1; $i <= $guestCount; $i++) {
                $name  = trim($guestNames[$i - 1] ?? '');
                $parts = $name !== '' ? preg_split('/\s+/', $name, 2) : [];

                $this->createPerson($event, [
                    // Unnamed guests print as "Guest 1 of <host last name>"
                    'firstName'        => $parts[0] ?? "Guest {$i}",
                    'lastName'         => $parts[1] ?? ($name !== '' ? '' : 'of ' . trim($data['lastName'])),
                    'organizationName' => $data['organization'] ?? null,
                    'uniqueId'         => "VIP-{$day}-{$seq}-G{$i}",
                    'vipGuests'        => 0,
                    'vipHostId'        => $host->getKey(),
                ], "VIP-{$seq}-G{$i}");
            }

            return $host;
        });

        return response()->json([
            'success' => true,
            'message' => $guestCount > 0
                ? "VIP added with {$guestCount} guest pass(es). Serve them from Scanner > Find person."
                : 'VIP added. Serve them from Scanner > Find person.',
            'data'    => ['attendeeId' => $vip->getKey()],
        ], 201);
    }

    public function destroy(int $attendeeId): JsonResponse
    {
        $event = $this->event();
        if (!$event) return $this->noEvent();

        $vip = Attendee::where('eventId', $event->eventId)
            ->where('attendeeId', $attendeeId)
            ->where('isVip', true)
            ->whereNull('vipHostId')
            ->first();

        if (!$vip) {
            return response()->json(['success' => false, 'message' => 'VIP not found.'], 404);
        }

        $ids     = Attendee::where('vipHostId', $attendeeId)->pluck('attendeeId')->push($attendeeId)->all();
        $passIds = EventPass::whereIn('attendeeId', $ids)->pluck('passId');

        $hasHistory = AttendanceRecord::whereIn('attendeeId', $ids)->exists()
            || MealRedemption::whereIn('passId', $passIds)->exists();

        if ($hasHistory) {
            return response()->json([
                'success' => false,
                'message' => 'This VIP or one of their guests already has meal or attendance records, so they cannot be deleted.',
            ], 422);
        }

        DB::transaction(function () use ($ids) {
            EventPass::whereIn('attendeeId', $ids)->delete();
            Attendee::whereIn('attendeeId', $ids)->delete();
        });

        return response()->json(['success' => true, 'message' => 'VIP and guest passes removed.']);
    }
}