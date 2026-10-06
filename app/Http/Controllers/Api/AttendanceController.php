<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventPass;
use App\Models\EventSession;
use App\Services\AttendanceService;
use App\Services\EligibilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Daily attendance: gate scan (scanner staff) + per-day dashboard and manual marks (admins).
 * One EventSession = one conference day.
 */
class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendance,
        protected EligibilityService $eligibility,
    ) {}

    private function event(): ?Event
    {
        return Event::where('status', 'active')->first();
    }

    private function noEvent(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'No active event found.'], 404);
    }

    // ── Gate scanning (scanner staff) ────────────────────────────────────

    /** POST /scanner/attendance  { token | attendeeId, deviceName } */
    public function scan(Request $request): JsonResponse
    {
        $v = $request->validate([
            'token'      => ['nullable', 'string', 'max:500'],
            'attendeeId' => ['nullable', 'integer'],
            'deviceName' => ['nullable', 'string', 'max:100'],
        ]);

        if (empty($v['token']) && empty($v['attendeeId'])) {
            return $this->deny('missing', 'Scan a pass or choose a person.');
        }

        $event = $this->event();
        if (!$event) return $this->noEvent();

        $day = $this->attendance->todaySession($event->eventId);
        if (!$day) {
            return $this->deny('no_day', 'No conference day is scheduled for today. Ask an admin to add it under Attendance.');
        }

        $pass = null;

        if (!empty($v['attendeeId'])) {
            $attendee = Attendee::where('eventId', $event->eventId)->where('attendeeId', $v['attendeeId'])->first();
            $pass = $attendee ? EventPass::where('attendeeId', $attendee->attendeeId)->first() : null;
        } else {
            $token = $this->attendance->normalizeToken($v['token']);

            $pass = EventPass::where('eventId', $event->eventId)
                ->where(function ($q) use ($token) {
                    $q->where('passCode', $token)->orWhere('serialNumber', $token);
                })
                ->first();

            $attendee = $pass ? Attendee::where('attendeeId', $pass->attendeeId)->first() : null;

            if ($pass && $pass->status !== 'active') {
                return $this->deny('pass_inactive', 'This pass is no longer active.', $attendee, $pass);
            }
        }

        if (!$attendee) {
            return $this->deny('invalid_pass', 'This QR code is not a valid event pass.');
        }

        [$record, $already] = $this->attendance->mark($attendee, $day, 'gate', Auth::id());

        return response()->json([
            'success' => true,
            'message' => $already ? 'Already counted today.' : 'Checked in.',
            'data'    => [
                'day'           => ['sessionId' => $day->sessionId, 'title' => $day->title],
                'attendee'      => $this->attendance->attendeeSummary($attendee, $pass),
                'alreadyMarked' => $already,
                'accredited'    => (bool) $attendee->isAccredited,
                'markedAt'      => $record->checkedInAt->toIso8601String(),
                'presentCount'  => $this->attendance->presentCount($day->sessionId),
            ],
        ]);
    }

    private function deny(string $code, string $message, ?Attendee $attendee = null, ?EventPass $pass = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code'    => $code,
            'data'    => ['attendee' => $attendee ? $this->attendance->attendeeSummary($attendee, $pass) : null],
        ], 422);
    }

    // ── Admin: days ──────────────────────────────────────────────────────

    /** GET /attendance/days */
    public function days(): JsonResponse
    {
        $event = $this->event();
        if (!$event) return $this->noEvent();

        $days = EventSession::where('eventId', $event->eventId)->orderBy('startsAt')->get();

        $counts = AttendanceRecord::whereIn('sessionId', $days->pluck('sessionId'))
            ->select('sessionId', 'method', DB::raw('count(*) as c'))
            ->groupBy('sessionId', 'method')
            ->get()
            ->groupBy('sessionId');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => [
                'accredited'   => Attendee::where('eventId', $event->eventId)->where('isAccredited', true)->count(),
                'totalDays'    => $days->count(),
                'requiredDays' => $event->requiredSessions,
                'days'         => $days->map(function (EventSession $d) use ($counts) {
                    $m = $counts->get($d->sessionId, collect())->pluck('c', 'method');

                    return [
                        'sessionId' => $d->sessionId,
                        'title'     => $d->title,
                        'date'      => $d->startsAt->toDateString(),
                        'startsAt'  => $d->startsAt->toIso8601String(),
                        'endsAt'    => $d->endsAt->toIso8601String(),
                        'gate'      => (int) $m->get('gate', 0),
                        'meal'      => (int) $m->get('meal', 0),
                        'self'      => (int) $m->get('self', 0),
                        'manual'    => (int) $m->get('manual', 0),
                        'venue'     => (int) $m->get('venue', 0),
                        'total'     => (int) $m->sum(),
                    ];
                })->values(),
            ],
        ]);
    }

    /** POST /attendance/days { title, date, startTime, endTime } */
    public function storeDay(Request $request): JsonResponse
    {
        $event = $this->event();
        if (!$event) return $this->noEvent();

        $data = $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'date'      => ['required', 'date'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime'   => ['required', 'date_format:H:i', 'after:startTime'],
        ]);

        EventSession::create([
            'eventId'  => $event->eventId,
            'title'    => trim($data['title']),
            'startsAt' => Carbon::parse($data['date'] . ' ' . $data['startTime']),
            'endsAt'   => Carbon::parse($data['date'] . ' ' . $data['endTime']),
        ]);

        $this->recalculateAll($event->eventId);

        return response()->json(['success' => true, 'message' => 'Day added.'], 201);
    }

    public function destroyDay(EventSession $session): JsonResponse
    {
        if (AttendanceRecord::where('sessionId', $session->sessionId)->exists()) {
            return response()->json(['success' => false, 'message' => 'This day already has attendance records and cannot be deleted.'], 422);
        }

        $eventId = $session->eventId;
        $session->delete();
        $this->recalculateAll($eventId);

        return response()->json(['success' => true, 'message' => 'Day deleted.']);
    }

    /** PATCH /attendance/required { requiredDays: number|null }  (null = every day) */
    public function setRequired(Request $request): JsonResponse
    {
        $event = $this->event();
        if (!$event) return $this->noEvent();

        $data = $request->validate(['requiredDays' => ['nullable', 'integer', 'min:1', 'max:60']]);

        Event::where('eventId', $event->eventId)->update(['requiredSessions' => $data['requiredDays'] ?? null]);
        $this->recalculateAll($event->eventId);

        return response()->json(['success' => true, 'message' => 'Certificate requirement saved.']);
    }

    // ── Admin: records for one day ───────────────────────────────────────

    /** GET /attendance/days/{session}/records?search=&page= */
    public function records(Request $request, EventSession $session): JsonResponse
    {
        $query = AttendanceRecord::where('attendance_records.sessionId', $session->sessionId)
            ->join('attendees', 'attendees.attendeeId', '=', 'attendance_records.attendeeId')
            ->select([
                'attendance_records.attendanceId',
                'attendance_records.method',
                'attendance_records.checkedInAt',
                'attendees.attendeeId',
                'attendees.title',
                'attendees.firstName',
                'attendees.lastName',
                'attendees.otherNames',
                'attendees.uniqueId',
                'attendees.isVip',
                'attendees.participationType',
            ])
            ->orderByDesc('attendance_records.checkedInAt');

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            foreach (preg_split('/\s+/', $search) as $word) {
                $query->where(function ($w) use ($word) {
                    $w->where('attendees.firstName', 'like', "%{$word}%")
                      ->orWhere('attendees.lastName', 'like', "%{$word}%")
                      ->orWhere('attendees.uniqueId', 'like', "%{$word}%");
                });
            }
        }

        $page = $query->paginate($request->integer('per_page', 25));

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => [
                'total'   => $page->total(),
                'records' => $page->getCollection()->map(fn ($r) => [
                    'attendanceId'      => $r->attendanceId,
                    'attendeeId'        => $r->attendeeId,
                    'fullName'          => trim(implode(' ', array_filter([$r->title, $r->firstName, $r->lastName, $r->otherNames]))),
                    'uniqueId'          => $r->uniqueId,
                    'isVip'             => (bool) $r->isVip,
                    'participationType' => $r->participationType,
                    'method'            => $r->method,
                    'checkedInAt'       => Carbon::parse($r->checkedInAt)->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    /** POST /attendance/days/{session}/records { attendeeId } : manual mark */
    public function addRecord(Request $request, EventSession $session): JsonResponse
    {
        $data = $request->validate(['attendeeId' => ['required', 'integer']]);

        $attendee = Attendee::where('eventId', $session->eventId)->where('attendeeId', $data['attendeeId'])->first();

        if (!$attendee) {
            return response()->json(['success' => false, 'message' => 'Attendee not found.'], 404);
        }

        [, $already] = $this->attendance->mark($attendee, $session, 'manual', Auth::id());

        return response()->json([
            'success' => true,
            'message' => $already ? 'Already marked present for this day.' : 'Marked present.',
            'data'    => ['alreadyMarked' => $already],
        ]);
    }

    /** DELETE /attendance/days/{session}/records/{attendanceId} : undo a mistaken mark */
    public function removeRecord(EventSession $session, int $attendanceId): JsonResponse
    {
        $record = AttendanceRecord::where('sessionId', $session->sessionId)->where('attendanceId', $attendanceId)->first();

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
        }

        $attendeeId = $record->attendeeId;
        $record->delete();

        if ($attendee = Attendee::where('attendeeId', $attendeeId)->first()) {
            $this->eligibility->recalculate($attendee);
        }

        return response()->json(['success' => true, 'message' => 'Attendance removed.']);
    }

    private function recalculateAll(int $eventId): void
    {
        Attendee::where('eventId', $eventId)->chunkById(200, function ($chunk) {
            foreach ($chunk as $a) {
                $this->eligibility->recalculate($a);
            }
        }, 'attendeeId');
    }
}
