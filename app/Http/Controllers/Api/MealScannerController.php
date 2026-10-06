<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventPass;
use App\Models\MealRedemption;
use App\Models\MealScanAttempt;
use App\Models\MealSession;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Buffet entrance scanner.
 * One scan per event pass per meal session. Only venue-accredited attendees with an active pass get in.
 * Works from a scanned QR OR by picking a person by name (VIPs, dead phones, forgotten passes).
 * A successful meal scan also marks that day's attendance if the gate has not already done so.
 */
class MealScannerController extends Controller
{
    public function __construct(protected AttendanceService $attendance) {}

    /** GET /scanner/current : open meal session + today's conference day, with live counters. */
    public function current(): JsonResponse
    {
        $event = Event::where('status', 'active')->first();

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'No active event found.'], 404);
        }

        $session = MealSession::where('eventId', $event->eventId)->where('status', 'active')->first();
        $day = $this->attendance->todaySession($event->eventId);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => [
                'session'         => $session ? $this->sessionSummary($session) : null,
                'servedCount'     => $session ? MealRedemption::where('mealSessionId', $session->mealSessionId)->count() : 0,
                'accreditedCount' => Attendee::where('eventId', $event->eventId)->where('isAccredited', true)->count(),
                'day'             => $day ? [
                    'sessionId' => $day->sessionId,
                    'title'     => $day->title,
                    'startsAt'  => $day->startsAt->toIso8601String(),
                    'endsAt'    => $day->endsAt->toIso8601String(),
                ] : null,
                'presentToday'    => $day ? $this->attendance->presentCount($day->sessionId) : 0,
            ],
        ]);
    }

    /**
     * GET /scanner/people?search=
     * Empty search = the VIP list. Otherwise matches name / unique ID / phone / organisation.
     */
    public function people(Request $request): JsonResponse
    {
        $event = Event::where('status', 'active')->first();

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'No active event found.'], 404);
        }

        $search = trim((string) $request->query('search', ''));
        $query = Attendee::where('eventId', $event->eventId);

        if ($search === '') {
            $query->where('isVip', true);
        } else {
            foreach (preg_split('/\s+/', $search) as $word) {
                $query->where(function ($w) use ($word) {
                    foreach (['firstName', 'lastName', 'uniqueId', 'phoneNumber', 'organizationName'] as $col) {
                        $w->orWhere($col, 'like', "%{$word}%");
                    }
                });
            }
        }

        $people = $query->orderByDesc('isVip')->orderBy('firstName')->limit(25)->get();
        $ids = $people->pluck('attendeeId')->all();

        $session = MealSession::where('eventId', $event->eventId)->where('status', 'active')->first();
        $served = $session
            ? MealRedemption::where('meal_redemptions.mealSessionId', $session->mealSessionId)
                ->join('event_passes', 'event_passes.passId', '=', 'meal_redemptions.passId')
                ->whereIn('event_passes.attendeeId', $ids)
                ->pluck('meal_redemptions.redeemedAt', 'event_passes.attendeeId')
            : collect();

        $day = $this->attendance->todaySession($event->eventId);
        $presentIds = $day
            ? AttendanceRecord::where('sessionId', $day->sessionId)->whereIn('attendeeId', $ids)->pluck('attendeeId')->all()
            : [];

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => [
                'people' => $people->map(fn (Attendee $a) => [
                    'attendeeId'   => $a->attendeeId,
                    'fullName'     => trim(implode(' ', array_filter([$a->title, $a->firstName, $a->lastName, $a->otherNames]))),
                    'organization' => $a->organizationName,
                    'uniqueId'     => $a->uniqueId,
                    'photoUrl'     => $a->photoUrl,
                    'isVip'        => (bool) $a->isVip,
                    'guests'       => (int) ($a->vipGuests ?? 0),
                    'isAccredited' => (bool) $a->isAccredited,
                    'served'       => $served->has($a->attendeeId),
                    'servedAt'     => $served->has($a->attendeeId) ? Carbon::parse($served->get($a->attendeeId))->toIso8601String() : null,
                    'presentToday' => in_array($a->attendeeId, $presentIds, true),
                ])->values(),
            ],
        ]);
    }

    /** POST /scanner/redeem  { token | attendeeId, deviceName } */
    public function redeem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'      => ['nullable', 'string', 'max:500'],
            'attendeeId' => ['nullable', 'integer'],
            'deviceName' => ['nullable', 'string', 'max:100'],
        ]);

        if (empty($validated['token']) && empty($validated['attendeeId'])) {
            return response()->json(['success' => false, 'message' => 'Scan a pass or choose a person.', 'code' => 'missing'], 422);
        }

        $device   = $validated['deviceName'] ?? null;
        $byPerson = !empty($validated['attendeeId']);
        $token    = $byPerson ? 'attendee:' . $validated['attendeeId'] : $this->attendance->normalizeToken($validated['token']);

        $event = Event::where('status', 'active')->first();
        if (!$event) {
            return $this->deny($request, $token, 'no_event', 'No active event found.');
        }

        $session = MealSession::where('eventId', $event->eventId)->where('status', 'active')->first();
        if (!$session) {
            return $this->deny($request, $token, 'no_session', 'No meal session is open right now.');
        }

        $pass = $byPerson
            ? EventPass::where('eventId', $event->eventId)->where('attendeeId', $validated['attendeeId'])->first()
            : EventPass::where('eventId', $event->eventId)
                ->where(function ($q) use ($token) {
                    $q->where('passCode', $token)->orWhere('serialNumber', $token);
                })
                ->first();

        if (!$pass) {
            return $this->deny(
                $request, $token, 'invalid_pass',
                $byPerson ? 'This person has no event pass on record.' : 'This QR code is not a valid event pass.',
                422, ['session' => $session]
            );
        }

        $attendee = Attendee::where('attendeeId', $pass->attendeeId)->first();
        $ctx = ['session' => $session, 'pass' => $pass, 'attendee' => $attendee];

        if (!$attendee || $pass->status !== 'active') {
            return $this->deny($request, $token, 'pass_inactive', 'This pass is no longer active.', 422, $ctx);
        }

        if (!$attendee->isAccredited) {
            return $this->deny(
                $request, $token, 'not_accredited',
                'Not accredited at the venue. Send them to the accreditation desk first.',
                422, $ctx
            );
        }

        $existing = MealRedemption::where('mealSessionId', $session->mealSessionId)
            ->where('passId', $pass->passId)
            ->first();

        if ($existing) {
            return $this->alreadyRedeemed($request, $token, $session, $existing, $ctx);
        }

        try {
            $redemption = MealRedemption::create([
                'mealSessionId' => $session->mealSessionId,
                'passId'        => $pass->passId,
                'redeemedBy'    => Auth::id(),
                'deviceName'    => $device,
                'redeemedAt'    => now(),
            ]);
        } catch (QueryException $e) {
            // Two scanners hit the same pass at the same instant: the unique index decides.
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }

            $existing = MealRedemption::where('mealSessionId', $session->mealSessionId)
                ->where('passId', $pass->passId)
                ->first();

            return $this->alreadyRedeemed($request, $token, $session, $existing, $ctx);
        }

        // Backstop: someone eating today was clearly here today. Never let this block a meal.
        try {
            $day = $this->attendance->sessionForDate($event->eventId, $session->mealDate);
            if ($day) {
                $this->attendance->mark($attendee, $day, 'meal', Auth::id());
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'success' => true,
            'message' => 'Allowed in.',
            'data'    => [
                'mealSession' => $this->sessionSummary($session),
                'attendee'    => $this->attendance->attendeeSummary($attendee, $pass),
                'redeemedAt'  => $redemption->redeemedAt->toIso8601String(),
                'servedCount' => MealRedemption::where('mealSessionId', $session->mealSessionId)->count(),
            ],
        ], 201);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function alreadyRedeemed(Request $request, string $token, MealSession $session, ?MealRedemption $existing, array $ctx): JsonResponse
    {
        $when = $existing?->redeemedAt;

        return $this->deny(
            $request, $token, 'already_redeemed',
            'Already collected for ' . $session->title . ($when ? ' at ' . $when->format('g:i A') : '') . '.',
            409,
            $ctx + ['redeemedAt' => $when?->toIso8601String()]
        );
    }

    private function deny(Request $request, string $token, string $code, string $message, int $status = 422, array $ctx = []): JsonResponse
    {
        try {
            MealScanAttempt::create([
                'mealSessionId' => ($ctx['session'] ?? null)?->mealSessionId,
                'passId'        => ($ctx['pass'] ?? null)?->passId,
                'token'         => mb_substr($token, 0, 255),
                'result'        => $code,
                'message'       => mb_substr($message, 0, 255),
                'scannedBy'     => Auth::id(),
                'deviceName'    => $request->input('deviceName'),
                'ipAddress'     => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e); // logging must never block the scanner
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'code'    => $code,
            'data'    => [
                'mealSession' => isset($ctx['session']) ? $this->sessionSummary($ctx['session']) : null,
                'attendee'    => isset($ctx['attendee']) ? $this->attendance->attendeeSummary($ctx['attendee'], $ctx['pass'] ?? null) : null,
                'redeemedAt'  => $ctx['redeemedAt'] ?? null,
            ],
        ], $status);
    }

    private function sessionSummary(MealSession $s): array
    {
        return [
            'mealSessionId' => $s->mealSessionId,
            'title'         => $s->title,
            'mealDate'      => Carbon::parse($s->mealDate)->toDateString(),
            'startTime'     => substr((string) $s->startTime, 0, 5),
            'endTime'       => substr((string) $s->endTime, 0, 5),
        ];
    }
}
