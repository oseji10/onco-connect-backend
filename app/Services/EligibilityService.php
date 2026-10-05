<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\QuestionnaireResponse;

/**
 * Single source of truth for "can this person get a certificate?".
 * Call recalculate() after ANY change: venue accreditation, self check-in,
 * questionnaire submission, manual override or revoke.
 */
class EligibilityService
{
    public function recalculate(Attendee $attendee): bool
    {
        if ($attendee->manualOverride) {
            $eligible = true;
        } else {
            $event = Event::where('eventId', $attendee->eventId)->first();
            $totalSessions = EventSession::where('eventId', $attendee->eventId)->count();
            $required = $event?->requiredSessions ?? $totalSessions;

            $attended = AttendanceRecord::where('attendeeId', $attendee->attendeeId)->count();

            $present = (bool) $attendee->isAccredited
                || ($required > 0 && $attended >= $required);

            $submitted = QuestionnaireResponse::where('attendeeId', $attendee->attendeeId)->exists();

            $eligible = $present && $submitted;
        }

        $attendee->forceFill([
            'certificateEligible'   => $eligible,
            'certificateEligibleAt' => $eligible ? ($attendee->certificateEligibleAt ?? now()) : null,
        ])->save();

        return $eligible;
    }
}