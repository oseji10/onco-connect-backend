<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\QuestionnaireInviteMail;
use App\Models\AttendanceRecord;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\QuestionnaireResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Admin: email personal questionnaire links to people who attended. Protect with your admin middleware. */
class QuestionnaireInvitationController extends Controller
{
    private function event(): ?Event
    {
        return Event::where('status', 'active')->first();
    }

    /** Attended = venue accredited, checked in to a session, or manually accredited. */
    private function audience(int $eventId)
    {
        return Attendee::where('eventId', $eventId)->where(function ($q) {
            $q->where('isAccredited', true)
              ->orWhere('manualOverride', true)
              ->orWhereIn('attendeeId', AttendanceRecord::select('attendeeId'));
        });
    }

    private function pending(int $eventId)
    {
        return $this->audience($eventId)
            ->whereNotIn('attendeeId', QuestionnaireResponse::where('eventId', $eventId)->select('attendeeId'));
    }

    private function withEmail($query)
    {
        return $query->whereNotNull('email')->where('email', '!=', '');
    }

    public function index(): JsonResponse
    {
        $event = $this->event();
        if (!$event) return response()->json(['success' => false, 'message' => 'No active event found.'], 404);

        $id = $event->eventId;

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => [
                'audience'      => $this->audience($id)->count(),
                'submitted'     => $this->audience($id)->whereIn('attendeeId', QuestionnaireResponse::where('eventId', $id)->select('attendeeId'))->count(),
                'pendingInvite' => $this->withEmail($this->pending($id))->whereNull('questionnaireInvitedAt')->count(),
                'pendingRemind' => $this->withEmail($this->pending($id))->whereNotNull('questionnaireInvitedAt')->count(),
                'noEmail'       => $this->pending($id)->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))->count(),
            ],
        ]);
    }

    /** POST { mode: "invite" | "remind" } */
    public function send(Request $request): JsonResponse
    {
        $event = $this->event();
        if (!$event) return response()->json(['success' => false, 'message' => 'No active event found.'], 404);

        $mode = $request->validate(['mode' => ['required', 'in:invite,remind']])['mode'];

        if (($event->questionnaireStatus ?? 'closed') !== 'open') {
            return response()->json(['success' => false, 'message' => 'Open the questionnaire before sending invitations.'], 422);
        }

        $query = $this->withEmail($this->pending($event->eventId));
        $mode === 'invite' ? $query->whereNull('questionnaireInvitedAt') : $query->whereNotNull('questionnaireInvitedAt');

        $eventName = config('certificate.event_name') ?: ($event->name ?? $event->title ?? 'the conference');
        $base = $this->frontendBase();
        if ($base === '') {
            return $this->missingBase();
        }
        $count = 0;

        $query->chunkById(100, function ($attendees) use (&$count, $mode, $eventName, $base) {
            foreach ($attendees as $a) {
                $url = $base . '/q#' . $this->tokenFor($a);

                Mail::to($a->email)->send(new QuestionnaireInviteMail($a, $url, $eventName, $mode === 'remind'));

                $a->forceFill(['questionnaireInvitedAt' => now()])->save();
                $count++;
            }
        }, 'attendeeId');

        return response()->json([
            'success' => true,
            'message' => $count === 1 ? '1 email sent.' : "$count emails sent.",
            'data'    => ['sent' => $count],
        ]);
    }

    /** GET /questionnaire/link/{attendeeId} : for people without email (share by WhatsApp/SMS). */
    public function link(int $attendeeId): JsonResponse
    {
        $event = $this->event();
        $attendee = $event ? Attendee::where('eventId', $event->eventId)->where('attendeeId', $attendeeId)->first() : null;

        if (!$attendee) {
            return response()->json(['success' => false, 'message' => 'Attendee not found.'], 404);
        }

        if ($this->frontendBase() === '') {
            return $this->missingBase();
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => ['url' => $this->frontendBase() . '/q#' . $this->tokenFor($attendee)],
        ]);
    }

    private function frontendBase(): string
    {
        return rtrim((string) config('certificate.frontend_url'), '/');
    }

    private function missingBase(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'FRONTEND_URL is not configured. Add the frontend_url key to config/certificate.php, set FRONTEND_URL in .env, then run: php artisan config:clear',
        ], 422);
    }

    private function tokenFor(Attendee $attendee): string
    {
        if (!$attendee->questionnaireToken) {
            $attendee->forceFill(['questionnaireToken' => Str::random(48)])->save();
        }

        return $attendee->questionnaireToken;
    }
}
