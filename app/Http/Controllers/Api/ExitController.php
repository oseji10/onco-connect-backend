<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\ExitLog;
use App\Models\Attendee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ExitController extends Controller
{
    /**
     * Get attendee info by scanning QR code
     */
    public function scanQRCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qrData' => ['required', 'string'],
        ]);

        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        // Find attendee by QR data (could be attendeeId or unique identifier)
        $pass = DB::table('event_passes')
    ->where('passCode', $validated['qrData'])
    ->first();

if (!$pass) {
    return response()->json(['message' => 'Invalid QR code'], 404);
}

$attendee = Attendee::where('eventId', $eventId)
    ->where('attendeeId', $pass->attendeeId)
    ->first();

        if (!$attendee) {
            return response()->json([
                'success' => false,
                'message' => 'Participant not found for this event.',
            ], 404);
        }

        // Check current exit status
        $currentExit = ExitLog::where('attendeeId', $attendee->attendeeId)
            ->where('eventId', $eventId)
            ->where('status', 'out')
            ->whereNull('returnTime')
            ->latest('exitTime')
            ->first();

        return response()->json([
            'success' => true,
            'attendee' => [
                // 'id' => $attendee->attendeeId,
                'attendeeId' => $attendee->attendeeId,
                'fullName' => $attendee->fullName,
                'photo' => $attendee->photoUrl,
                'phoneNumber' => $attendee->phoneNumber,
                'state' => $attendee->state,
                'lga' => $attendee->lga,
            ],
            'currentStatus' => $currentExit ? 'out' : 'in',
            'currentExit' => $currentExit ? [
                'exitLogId' => $currentExit->exitLogId,
                'reason' => $currentExit->reason,
                'exitTime' => $currentExit->exitTime->format('Y-m-d H:i:s'),
                'timeAway' => $currentExit->time_away,
            ] : null,
        ]);
    }

    /**
     * Record participant exit
     */
    public function recordExit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attendeeId' => ['required', 'integer', 'exists:attendees,attendeeId'],
            'reason' => ['required', 'string', 'max:255'],
            'additionalNotes' => ['nullable', 'string'],
        ]);

        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        // Check if participant is already out
        $existingExit = ExitLog::where('attendeeId', $validated['attendeeId'])
            ->where('eventId', $eventId)
            ->where('status', 'out')
            ->whereNull('returnTime')
            ->exists();

        if ($existingExit) {
            return response()->json([
                'success' => false,
                'message' => 'Participant is already marked as out.',
            ], 422);
        }

        // Create exit log
        $exitLog = ExitLog::create([
            'eventId' => $eventId,
            'attendeeId' => $validated['attendeeId'],
            'reason' => $validated['reason'],
            'additionalNotes' => $validated['additionalNotes'] ?? null,
            'exitTime' => now(),
            'status' => 'out',
            'recordedBy' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Exit recorded successfully.',
            'exitLog' => [
                'exitLogId' => $exitLog->exitLogId,
                'reason' => $exitLog->reason,
                'exitTime' => $exitLog->exitTime->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Record participant return
     */
    public function recordReturn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exitLogId' => ['required', 'integer', 'exists:exit_logs,exitLogId'],
        ]);

        $exitLog = ExitLog::findOrFail($validated['exitLogId']);

        if ($exitLog->status === 'returned') {
            return response()->json([
                'success' => false,
                'message' => 'Return already recorded for this exit.',
            ], 422);
        }

        // Calculate duration
        $returnTime = now();
        $durationMinutes = $returnTime->diffInMinutes($exitLog->exitTime);

        // Update exit log
        $exitLog->update([
            'returnTime' => $returnTime,
            'durationMinutes' => $durationMinutes,
            'status' => 'returned',
            'returnRecordedBy' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Return recorded successfully.',
            'exitLog' => [
                'exitLogId' => $exitLog->exitLogId,
                'exitTime' => $exitLog->exitTime->format('Y-m-d H:i:s'),
                'returnTime' => $exitLog->returnTime->format('Y-m-d H:i:s'),
                'durationMinutes' => $exitLog->durationMinutes,
            ],
        ]);
    }

    /**
     * Get all participants currently out
     */
    public function getCurrentlyOut(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        $currentlyOut = ExitLog::where('eventId', $eventId)
            ->where('status', 'out')
            ->whereNull('returnTime')
            ->with(['attendee', 'recorder', 'attendee.subcl.user', 'attendee.colors'])
            ->orderBy('exitTime', 'asc') // Oldest exits first
            ->get()
            ->map(function ($log) {
                $minutesAway = now()->diffInMinutes($log->exitTime);
                
                return [
                    'exitLogId' => $log->exitLogId,
                    'attendee' => [
                        // 'id' => $log->attendee->attendeeId,
                        'attendeeId' => $log->attendee->uniqueId,
                        'fullName' => $log->attendee->fullName,
                        'photo' => $log->attendee->photoUrl,
                        'phoneNumber' => $log->attendee->phone,
                        'subclName' => $log->attendee->subcl->user->firstName . ' ' . $log->attendee->subcl->user->lastName,
                        'color' => $log->attendee->colors?->colorName,
                    ],
                    'reason' => $log->reason,
                    'additionalNotes' => $log->additionalNotes,
                    'exitTime' => $log->exitTime->format('Y-m-d H:i:s'),
                    'minutesAway' => $minutesAway,
                    'timeAway' => $log->time_away,
                    'recordedBy' => $log->recorder->name ?? 'N/A',
                ];
            });

        return response()->json([
            'success' => true,
            'count' => $currentlyOut->count(),
            'participants' => $currentlyOut,
        ]);
    }

    /**
     * Get exit history (all exits including returned)
     */
    public function getExitHistory(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        $history = ExitLog::where('eventId', $eventId)
            ->with(['attendee', 'recorder', 'returnRecorder'])
            ->orderBy('exitTime', 'desc')
            ->take(50)
            ->get()
            ->map(function ($log) {
                return [
                    'exitLogId' => $log->exitLogId,
                    'attendee' => [
                        'fullName' => $log->attendee->fullName,
                        'attendeeId' => $log->attendee->attendeeId,
                        'photo' => $log->attendee->photoUrl,
                    ],
                    'reason' => $log->reason,
                    'additionalNotes' => $log->additionalNotes,
                    'exitTime' => $log->exitTime->format('Y-m-d H:i:s'),
                    'returnTime' => $log->returnTime ? $log->returnTime->format('Y-m-d H:i:s') : null,
                    'durationMinutes' => $log->durationMinutes,
                    'status' => $log->status,
                    'recordedBy' => $log->recorder->name ?? 'N/A',
                    'returnRecordedBy' => $log->returnRecorder->name ?? null,
                ];
            });

        return response()->json([
            'success' => true,
            'history' => $history,
        ]);
    }

    /**
     * Get statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        $stats = [
            'currentlyOut' => ExitLog::where('eventId', $eventId)
                ->where('status', 'out')
                ->whereNull('returnTime')
                ->count(),
            'totalExits' => ExitLog::where('eventId', $eventId)->count(),
            'totalReturned' => ExitLog::where('eventId', $eventId)
                ->where('status', 'returned')
                ->count(),
            'averageDurationMinutes' => ExitLog::where('eventId', $eventId)
                ->where('status', 'returned')
                ->avg('durationMinutes'),
        ];

        return response()->json([
            'success' => true,
            'statistics' => $stats,
        ]);
    }
}