<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventSession;
use App\Services\EligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin-facing: eligibility overview + manual accreditation (option 4).
 * Protect these routes with your admin/organiser middleware.
 */
class EligibilityController extends Controller
{
    public function __construct(protected EligibilityService $eligibility) {}

    public function index(int $eventId): JsonResponse
    {
        $event = Event::where('eventId', $eventId)->first();
        $totalSessions = EventSession::where('eventId', $eventId)->count();

        $attendees = Attendee::where('eventId', $eventId)
            ->withCount('attendanceRecords')
            ->with('feedback:feedbackId,attendeeId')
            ->orderBy('firstName')
            ->get()
            ->map(fn (Attendee $a) => [
                'attendeeId'           => $a->attendeeId,
                'fullName'             => trim("{$a->firstName} {$a->lastName} {$a->otherNames}"),
                'uniqueId'             => $a->uniqueId,
                'email'                => $a->email,
                'participationType'    => $a->participationType,
                'isAccredited'         => (bool) $a->isAccredited,
                'sessionsAttended'     => $a->attendance_records_count,
                'feedbackSubmitted'    => (bool) $a->feedback,
                'manualOverride'       => (bool) $a->manualOverride,
                'manualOverrideReason' => $a->manualOverrideReason,
                'certificateEligible'  => (bool) $a->certificateEligible,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Eligibility retrieved.',
            'data'    => [
                'totalSessions'    => $totalSessions,
                'requiredSessions' => $event?->requiredSessions ?? $totalSessions,
                'attendees'        => $attendees,
            ],
        ]);
    }

    public function manualAccredit(Request $request, int $eventId): JsonResponse
    {
        $validated = $request->validate([
            'attendeeId' => ['required', 'integer'],
            'reason'     => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $attendee = Attendee::where('eventId', $eventId)
            ->where('attendeeId', $validated['attendeeId'])
            ->first();

        if (!$attendee) {
            return response()->json(['success' => false, 'message' => 'Attendee not found.'], 404);
        }

        $attendee->forceFill([
            'manualOverride'       => true,
            'manualOverrideReason' => trim($validated['reason']),
            'manualOverrideBy'     => Auth::id(),
            'manualOverrideAt'     => now(),
        ])->save();

        $this->eligibility->recalculate($attendee->fresh());

        return response()->json(['success' => true, 'message' => 'Attendee manually accredited.']);
    }

    public function revokeManual(int $eventId, int $attendeeId): JsonResponse
    {
        $attendee = Attendee::where('eventId', $eventId)->where('attendeeId', $attendeeId)->first();

        if (!$attendee) {
            return response()->json(['success' => false, 'message' => 'Attendee not found.'], 404);
        }

        $attendee->forceFill([
            'manualOverride'       => false,
            'manualOverrideReason' => null,
            'manualOverrideBy'     => null,
            'manualOverrideAt'     => null,
        ])->save();

        $this->eligibility->recalculate($attendee->fresh());

        return response()->json(['success' => true, 'message' => 'Manual accreditation revoked.']);
    }
}