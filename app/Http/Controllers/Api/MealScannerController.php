<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventPass;
use App\Models\MealRedemption;
use App\Models\MealScanAttempt;
use App\Models\MealSession;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Buffet entrance scanner.
 * One scan per event pass per meal session. Only venue-accredited attendees
 * with an active pass are let in.
 */
class MealScannerController extends Controller
{
    /** GET /scanner/current : the open session + live counters. */
    public function current(): JsonResponse
    {
        $event = Event::where('status', 'active')->first();

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'No active event found.'], 404);
        }

        $session = MealSession::where('eventId', $event->eventId)->where('status', 'active')->first();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => [
                'session'         => $session ? $this->sessionSummary($session) : null,
                'servedCount'     => $session ? MealRedemption::where('mealSessionId', $session->mealSessionId)->count() : 0,
                'accreditedCount' => Attendee::where('eventId', $event->eventId)->where('isAccredited', true)->count(),
            ],
        ]);
    }

    /** POST /scanner/redeem  { token, deviceName } */
    public function redeem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'      => ['required', 'string', 'max:500'],
            'deviceName' => ['nullable', 'string', 'max:100'],
        ]);

        $token  = $this->normalizeToken($validated['token']);
        $device = $validated['deviceName'] ?? null;

        $event = Event::where('status', 'active')->first();
        if (!$event) {
            return $this->deny($request, $token, 'no_event', 'No active event found.');
        }

        $session = MealSession::where('eventId', $event->eventId)->where('status', 'active')->first();
        if (!$session) {
            return $this->deny($request, $token, 'no_session', 'No meal session is open right now.');
        }

        $pass = EventPass::where('eventId', $event->eventId)
            ->where(function ($q) use ($token) {
                $q->where('passCode', $token)->orWhere('serialNumber', $token);
            })
            ->first();

        if (!$pass) {
            return $this->deny($request, $token, 'invalid_pass', 'This QR code is not a valid event pass.', 422, [
                'session' => $session,
            ]);
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

        return response()->json([
            'success' => true,
            'message' => 'Allowed in.',
            'data'    => [
                'mealSession' => $this->sessionSummary($session),
                'attendee'    => $this->attendeeSummary($attendee, $pass),
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
                'attendee'    => isset($ctx['attendee']) ? $this->attendeeSummary($ctx['attendee'], $ctx['pass'] ?? null) : null,
                'redeemedAt'  => $ctx['redeemedAt'] ?? null,
            ],
        ], $status);
    }

    /** Accepts a bare code or a verify URL like https://app/verify/ABC123. */
    private function normalizeToken(string $raw): string
    {
        $text = trim($raw);

        if (filter_var($text, FILTER_VALIDATE_URL)) {
            $path     = parse_url($text, PHP_URL_PATH) ?: '';
            $segments = array_values(array_filter(explode('/', $path)));

            if ($segments) {
                return end($segments);
            }

            parse_str(parse_url($text, PHP_URL_QUERY) ?? '', $query);

            return $query['code'] ?? $query['pass'] ?? $query['q'] ?? $text;
        }

        return $text;
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

    private function attendeeSummary(Attendee $a, ?EventPass $pass): array
    {
        return [
            'fullName'          => trim(implode(' ', array_filter([$a->title, $a->firstName, $a->lastName, $a->otherNames]))),
            'uniqueId'          => $a->uniqueId,
            'category'          => $a->category,
            'participationType' => $a->participationType,
            'photoUrl'          => $a->photoUrl,
            'serialNumber'      => $pass?->serialNumber,
        ];
    }
}