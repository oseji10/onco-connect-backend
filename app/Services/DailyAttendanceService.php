<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\DailyAttendance;
use App\Models\Event;
use App\Models\EventPass;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DailyAttendanceService
{
    public function markAttendance(
        Event $event,
        string $token,
        ?string $deviceName = null,
        string $scanSource = 'barcode',
        ?string $attendanceDate = null,
    ): array {
        $attendanceDateValue = $attendanceDate
            ? Carbon::parse($attendanceDate)->toDateString()
            : now()->toDateString();

        $lockStatus = $this->getAttendanceLockStatus($event, $attendanceDateValue);

        if ($lockStatus['isClosed']) {
            return [
                'success' => false,
                'message' => $lockStatus['message'],
                'data' => [
                    'status' => 'attendance_closed',
                    'attendanceDate' => $attendanceDateValue,
                    'closeTime' => $lockStatus['closeTime'],
                    'closeDateTime' => $lockStatus['closeDateTime'],
                ],
            ];
        }

        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $pass = EventPass::with('attendee')
            ->where('eventId', $activeEvent->eventId)
            ->where(function ($query) use ($token) {
                $query->where('passCode', $token)
                    ->orWhere('serialNumber', $token);
            })
            ->first();

        if (!$pass || !$pass->attendee) {
            return [
                'success' => false,
                'message' => 'Invalid pass code.',
                'data' => [
                    'status' => 'invalid_pass',
                    'attendanceDate' => $attendanceDateValue,
                ],
            ];
        }

        $existingAttendance = DailyAttendance::with(['attendee', 'pass'])
            ->where('eventId', $event->eventId)
            ->where('attendeeId', $pass->attendeeId)
            ->whereDate('attendanceDate', $attendanceDateValue)
            ->first();

        if ($existingAttendance) {
            return [
                'success' => false,
                'message' => 'Attendance has already been marked for this attendee today.',
                'data' => [
                    'status' => 'already_marked',
                    'attendanceId' => $existingAttendance->attendanceId,
                    'attendanceDate' => $existingAttendance->attendanceDate,
                    'markedAt' => optional($existingAttendance->created_at)?->toDateTimeString(),
                    'attendee' => [
                        'attendeeId' => $pass->attendee->attendeeId,
                        'name' => $this->resolveAttendeeName($pass->attendee),
                        'uniqueId' => $pass->attendee->uniqueId,
                        'phone' => $pass->attendee->phone,
                        'gender' => $pass->attendee->gender,
                        'passportUrl' => $pass->attendee->passportUrl ?? null,
                    ],
                    'pass' => [
                        'passId' => $pass->passId,
                        'serialNumber' => $pass->serialNumber,
                    ],
                ],
            ];
        }

        $attendance = DB::transaction(function () use (
            $event,
            $pass,
            $attendanceDateValue,
            $deviceName,
            $scanSource
            ) {
            $activeEvent = Event::where('status', 'active')->first();
            return DailyAttendance::create([
                'eventId' => $activeEvent->eventId,
                'attendeeId' => $pass->attendeeId,
                'eventPassId' => $pass->passId,
                'attendanceDate' => $attendanceDateValue,
                'deviceName' => $deviceName,
                'scanSource' => $scanSource,
                'markedBy' => Auth::id(),
                'markedAt' => now(),
            ]);
        });

        return [
            'success' => true,
            'message' => 'Attendance marked successfully.',
            'data' => [
                'status' => 'marked',
                'attendanceId' => $attendance->attendanceId,
                'attendanceDate' => $attendance->attendanceDate,
                'markedAt' => optional($attendance->created_at)?->toDateTimeString(),
                'attendee' => [
                    'attendeeId' => $pass->attendee->attendeeId,
                    'name' => $this->resolveAttendeeName($pass->attendee),
                    'uniqueId' => $pass->attendee->uniqueId,
                    'phone' => $pass->attendee->phone,
                    'gender' => $pass->attendee->gender,
                    'passportUrl' => $pass->attendee->passportUrl ?? null,
                ],
                'pass' => [
                    'passId' => $pass->passId,
                    'serialNumber' => $pass->serialNumber,
                ],
            ],
        ];
    }

    public function getAttendanceLockStatus(Event $event, ?string $attendanceDate = null): array
    {
        $attendanceDateValue = $attendanceDate
            ? Carbon::parse($attendanceDate)->toDateString()
            : now()->toDateString();

        $lockEnabled = (bool) ($event->attendanceLockEnabled ?? true);
        $closeTime = $event->attendanceCloseTime;

        if (!$lockEnabled || !$closeTime) {
            return [
                'enabled' => false,
                'isClosed' => false,
                'closeTime' => null,
                'closeDateTime' => null,
                'message' => null,
            ];
        }

        $today = now()->toDateString();

        if ($attendanceDateValue !== $today) {
            return [
                'enabled' => true,
                'isClosed' => true,
                'closeTime' => $closeTime,
                'closeDateTime' => null,
                'message' => 'Attendance can only be marked for today before the closing time.',
            ];
        }

        $closeDateTime = Carbon::parse($attendanceDateValue . ' ' . $closeTime);
        $isClosed = now()->greaterThan($closeDateTime);

        return [
            'enabled' => true,
            'isClosed' => $isClosed,
            'closeTime' => $closeTime,
            'closeDateTime' => $closeDateTime->toDateTimeString(),
            'message' => $isClosed
                ? 'Attendance has closed for today.'
                : 'Attendance is open.',
        ];
    }

    protected function resolveAttendeeName($attendee): string
    {
        $fullName = trim(
            ($attendee->fullName ?? '')
        );

        return $attendee->name
            ?? ($fullName !== '' ? $fullName : 'Unknown attendee');
    }
}