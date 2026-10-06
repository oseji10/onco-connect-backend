<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Attendee;
use App\Models\EventPass;
use App\Models\EventSession;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

/** Daily attendance. One EventSession = one conference day. */
class AttendanceService
{
    public function __construct(protected EligibilityService $eligibility) {}

    /** Today's conference day (by date in the app timezone, so the gate works before the official start time). */
    public function todaySession(int $eventId): ?EventSession
    {
        return EventSession::where('eventId', $eventId)
            ->whereDate('startsAt', today())
            ->orderBy('startsAt')
            ->first();
    }

    public function sessionForDate(int $eventId, $date): ?EventSession
    {
        return EventSession::where('eventId', $eventId)
            ->whereDate('startsAt', Carbon::parse($date)->toDateString())
            ->orderBy('startsAt')
            ->first();
    }

    /**
     * Mark an attendee present for a day. Idempotent.
     *
     * @return array{0:AttendanceRecord,1:bool} [record, alreadyMarked]
     */
    public function mark(Attendee $attendee, EventSession $day, string $method, ?int $by = null): array
    {
        $existing = AttendanceRecord::where('attendeeId', $attendee->attendeeId)
            ->where('sessionId', $day->sessionId)
            ->first();

        if ($existing) {
            // A real gate scan outranks a meal-only inference.
            if ($method === 'gate' && $existing->method === 'meal') {
                $existing->update(['method' => 'gate', 'recordedBy' => $by]);
                $this->eligibility->recalculate($attendee->fresh());

                return [$existing, false];
            }

            return [$existing, true];
        }

        try {
            $record = AttendanceRecord::create([
                'attendeeId'  => $attendee->attendeeId,
                'sessionId'   => $day->sessionId,
                'method'      => $method, // gate | meal | self | manual
                'checkedInAt' => now(),
                'recordedBy'  => $by,
            ]);
        } catch (QueryException $e) {
            // Two scanners at the same instant: the unique index decides.
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }

            $record = AttendanceRecord::where('attendeeId', $attendee->attendeeId)
                ->where('sessionId', $day->sessionId)
                ->first();

            return [$record, true];
        }

        $this->eligibility->recalculate($attendee->fresh());

        return [$record, false];
    }

    public function presentCount(int $sessionId): int
    {
        return AttendanceRecord::where('sessionId', $sessionId)->count();
    }

    /** Accepts a bare code or a verify URL like https://app/verify/ABC123. */
    public function normalizeToken(string $raw): string
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

    public function attendeeSummary(Attendee $a, ?EventPass $pass = null): array
    {
        return [
            'fullName'          => trim(implode(' ', array_filter([$a->title, $a->firstName, $a->lastName, $a->otherNames]))),
            'uniqueId'          => $a->uniqueId,
            'category'          => $a->category,
            'participationType' => $a->participationType,
            'photoUrl'          => $a->photoUrl,
            'serialNumber'      => $pass?->serialNumber,
            'isVip'             => (bool) $a->isVip,
            'guests'            => (int) ($a->vipGuests ?? 0),
        ];
    }
}
