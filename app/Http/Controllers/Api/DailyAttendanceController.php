<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyAttendance;
use App\Models\Event;
use App\Models\EventPass;
use App\Services\AttendanceWindowService;
use App\Services\DailyAttendanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DailyAttendanceController extends Controller
{
    public function __construct(
        protected DailyAttendanceService $dailyAttendanceService,
        protected AttendanceWindowService $attendanceWindowService
    ) {
    }

    protected function ensureScannerAccess(): void
    {
        $user = Auth::user();

        if (!$user) {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403));
        }
        
    }

    public function scan(Request $request, Event $event): JsonResponse
    {
        $this->ensureScannerAccess();

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'deviceName' => ['nullable', 'string', 'max:255'],
            'scanSource' => ['nullable', 'string', 'max:50'],
            'attendanceDate' => ['nullable', 'date'],
        ]);

        // Enforce attendance time window: 8:00 AM - 8:50 AM WAT
        $now = Carbon::now('Africa/Lagos'); // WAT timezone
        $currentHour = $now->hour;
        $currentMinute = $now->minute;
        
        // Check if current time is between 8:00 AM and 8:50 AM
        // $isWithinWindow = ($currentHour === 8 && $currentMinute <= 50);
        $isWithinWindow = ($currentHour === 8 && $currentMinute >= 45 && $currentMinute <= 59);
        
        if (!$isWithinWindow) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance can only be taken between 8:45 AM and 8:59 AM WAT.',
                'data' => [
                    'status' => 'outside_window',
                    'currentTime' => $now->format('h:i A'),
                    'allowedWindow' => '8:45 AM - 8:59 AM WAT',
                ],
            ], 422);
        }

        $result = $this->dailyAttendanceService->markAttendance(
            event: $event,
            token: $validated['token'],
            deviceName: $validated['deviceName'] ?? null,
            scanSource: $validated['scanSource'] ?? 'barcode',
            attendanceDate: $validated['attendanceDate'] ?? null,
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }



    public function index(Request $request, Event $event): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());
        $search = trim((string) $request->query('search', ''));

        $query = DailyAttendance::with(['attendee', 'pass', 'marker', 'attendee.color', 'attendee.subcl.user'])
            ->where('eventId', $event->eventId)
            ->whereDate('attendanceDate', $date);

        if ($search !== '') {
            $query->whereHas('attendee', function ($q) use ($search) {
                $q->where('phone', $search)
                    ->orWhere('uniqueId', $search)
                    ->orWhere('uniqueId', 'LIKE', "%{$search}")
                    ->orWhereRaw("CONCAT(COALESCE(firstName, ''), ' ', COALESCE(lastName, '')) LIKE ?", ["%{$search}%"]);
            });
        }

        $records = $query->latest('attendanceId')->paginate(50);

        return response()->json([
            'success' => true,
            'message' => 'Attendance records retrieved successfully.',
            'data' => $records,
        ]);
    }

    public function summary(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $attendanceDate = $validated['date'] ?? now()->toDateString();

        $registeredCount = $event->attendees()->count();

        $presentCount = DailyAttendance::where('eventId', $event->eventId)
            ->whereDate('attendanceDate', $attendanceDate)
            ->count();

        $absentCount = max($registeredCount - $presentCount, 0);

        $window = $this->attendanceWindowService->getStatus($event, $attendanceDate);

        return response()->json([
            'success' => true,
            'message' => 'Attendance summary fetched successfully.',
            'data' => [
                'eventId' => $event->eventId,
                'attendanceDate' => $attendanceDate,
                'registeredCount' => $registeredCount,
                'presentCount' => $presentCount,
                'absentCount' => $absentCount,
                'attendanceLock' => $window,
            ],
        ]);
    }


    public function config(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $attendanceDate = $validated['date'] ?? now()->toDateString();
        $window = $this->attendanceWindowService->getStatus($event, $attendanceDate);

        return response()->json([
            'success' => true,
            'message' => 'Attendance config fetched successfully.',
            'data' => [
                'attendanceDate' => $attendanceDate,
                'attendanceLock' => $window,
            ],
        ]);
    }
}