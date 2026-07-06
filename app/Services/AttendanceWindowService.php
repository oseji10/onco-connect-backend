<?php

namespace App\Services;

use App\Models\Event;
use Carbon\Carbon;

class AttendanceWindowService
{
    public function getStatus(Event $event, ?string $attendanceDate = null): array
    {
        $date = $attendanceDate
            ? Carbon::parse($attendanceDate)->startOfDay()
            : now()->startOfDay();

        $today = now()->startOfDay();

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

        $closeDateTime = Carbon::parse(
            $date->format('Y-m-d') . ' ' . $closeTime
        );

        // only enforce time lock for current day.
        // past dates can remain readable but not scannable if you choose.
        if (!$date->isSameDay($today)) {
            return [
                'enabled' => true,
                'isClosed' => true,
                'closeTime' => $closeTime,
                'closeDateTime' => $closeDateTime->toDateTimeString(),
                'message' => 'Attendance can only be taken for today before the closing time.',
            ];
        }

        $isClosed = now()->greaterThan($closeDateTime);

        return [
            'enabled' => true,
            'isClosed' => $isClosed,
            'closeTime' => $closeTime,
            'closeDateTime' => $closeDateTime->toDateTimeString(),
            'message' => $isClosed
                ? "Attendance has closed for today. Closing time was {$closeDateTime->format('h:i A')}."
                : "Attendance is open until {$closeDateTime->format('h:i A')}.",
        ];
    }

    public function ensureAttendanceOpen(Event $event, ?string $attendanceDate = null): void
    {
        $status = $this->getStatus($event, $attendanceDate);

        if ($status['isClosed']) {
            abort(response()->json([
                'success' => false,
                'message' => $status['message'],
                'data' => [
                    'status' => 'attendance_closed',
                    'attendanceDate' => $attendanceDate,
                    'closeTime' => $status['closeTime'],
                    'closeDateTime' => $status['closeDateTime'],
                ],
            ], 422));
        }
    }
}