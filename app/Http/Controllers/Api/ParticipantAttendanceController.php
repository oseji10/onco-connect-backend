<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventSession;
use App\Services\EligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Participant-facing: self check-in (option 2) and feedback gate (option 3).
 * The logged-in User is linked to their Attendee via attendees.userId
 * (already set in AttendeeController::store).
 */
class ParticipantAttendanceController extends Controller
{
    private const CHECKIN_GRACE_MINUTES = 10; // allow check-in slightly before start

    public function __construct(protected EligibilityService $eligibility) {}

    private function currentAttendee(): ?Attendee
    {
        $event = Event::where('status', 'active')->first();
        if (!$event) return null;

        return Attendee::where('userId', Auth::id())
            ->where('eventId', $event->eventId)
            ->first();
    }

    public function show(): JsonResponse
    {
        $attendee = $this->currentAttendee();
        if (!$attendee) {
            return response()->json(['success' => false, 'message' => 'No registration found for the active event.'], 404);
        }

        $checkedIn = AttendanceRecord::where('attendeeId', $attendee->attendeeId)
            ->pluck('checkedInAt', 'sessionId');

        $sessions = EventSession::where('eventId', $attendee->eventId)
            ->orderBy('startsAt')
            ->get()
            ->map(function (EventSession $s) use ($checkedIn) {
                $now = now();
                $opens = $s->startsAt->copy()->subMinutes(self::CHECKIN_GRACE_MINUTES);

                $status = $checkedIn->has($s->sessionId) ? 'checked_in'
                    : ($now->lt($opens) ? 'upcoming'
                    : ($now->gt($s->endsAt) ? 'closed' : 'open'));

                return [
                    'sessionId'   => $s->sessionId,
                    'title'       => $s->title,
                    'startsAt'    => $s->startsAt->toIso8601String(),
                    'endsAt'      => $s->endsAt->toIso8601String(),
                    'status'      => $status,
                    'checkedInAt' => optional($checkedIn->get($s->sessionId))->toIso8601String(),
                ];
            });

        $event = Event::where('eventId', $attendee->eventId)->first();

        return response()->json([
            'success' => true,
            'message' => 'Attendance retrieved.',
            'data'    => [
                'fullName'            => trim("{$attendee->firstName} {$attendee->lastName}"),
                'isAccredited'        => (bool) $attendee->isAccredited,
                'feedbackSubmitted'   => \App\Models\QuestionnaireResponse::where('attendeeId', $attendee->attendeeId)->exists(),
                'questionnaireOpen'   => ($event?->questionnaireStatus ?? 'closed') === 'open',
                'certificateEligible' => (bool) $attendee->certificateEligible,
                'requiredSessions'    => $event?->requiredSessions ?? $sessions->count(),
                'sessions'            => $sessions,
            ],
        ]);
    }

    public function checkIn(EventSession $session): JsonResponse
    {
        $attendee = $this->currentAttendee();
        if (!$attendee || (int) $attendee->eventId !== (int) $session->eventId) {
            return response()->json(['success' => false, 'message' => 'Session not available for your registration.'], 403);
        }

        $now = now();
        if ($now->lt($session->startsAt->copy()->subMinutes(self::CHECKIN_GRACE_MINUTES))) {
            return response()->json(['success' => false, 'message' => 'Check-in has not opened for this session yet.'], 422);
        }
        if ($now->gt($session->endsAt)) {
            return response()->json(['success' => false, 'message' => 'This session has ended. Contact the organisers if you attended.'], 422);
        }

        AttendanceRecord::firstOrCreate(
            ['attendeeId' => $attendee->attendeeId, 'sessionId' => $session->sessionId],
            ['method' => 'self', 'checkedInAt' => $now, 'recordedBy' => Auth::id()]
        );

        $this->eligibility->recalculate($attendee->fresh());

        return response()->json(['success' => true, 'message' => 'Checked in to "' . $session->title . '".']);
    }
}
