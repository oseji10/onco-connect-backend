<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\CertificateMail;
use App\Models\Attendee;
use App\Models\Certificate;
use App\Models\Event;
use App\Services\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class CertificateController extends Controller
{
    public function __construct(
        protected CertificateService $certificateService,
    ) {}

    /**
     * GET /events/{eventId}/certificates/recipients
     *
     * Accredited attendees only, each with the certificates already issued.
     */
    public function recipients(int $eventId): JsonResponse
    {
        $recipients = Attendee::with(['certificates' => fn ($q) => $q->orderBy('issuedAt')])
            ->where('eventId', $eventId)
            ->where('isAccredited', true)
            ->orderByDesc('accreditedAt')
            ->get()
            ->map(function (Attendee $attendee) {
                return [
                    'attendeeId'       => $attendee->attendeeId,
                    'fullName'         => $this->fullName($attendee),
                    'uniqueId'         => $attendee->uniqueId,
                    'title'            => $attendee->title,
                    'email'            => $attendee->email,
                    'phoneNumber'      => $attendee->phoneNumber,
                    'gender'           => $attendee->gender,
                    'category'         => $attendee->category,
                    'organizationName' => $attendee->organizationName,
                    // Relative path — the front end prefixes NEXT_PUBLIC_API_FILE_URL.
                    'photoUrl'         => $attendee->photoUrl,
                    'accreditedAt'     => optional($attendee->accreditedAt)->toIso8601String(),
                    'certificates'     => $attendee->certificates->map(fn (Certificate $c) => [
                        'certificateId' => $c->certificateId,
                        'type'          => $c->type,
                        'issuedAt'      => optional($c->issuedAt)->toIso8601String(),
                        'sentAt'        => optional($c->sentAt)->toIso8601String(),
                    ])->values(),
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Certificate recipients retrieved successfully.',
            'data'    => ['recipients' => $recipients],
        ]);
    }

    /**
     * POST /events/{eventId}/certificates/generate
     *
     * Issues (or re-issues) a certificate and returns the raw PDF bytes. The
     * front end requests this as a blob for preview and download.
     */
    public function generate(Request $request, int $eventId)
    {
        $validated = $this->validateIssue($request);

        $event = $this->findEvent($eventId);
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Event not found.'], 404);
        }

        $attendee = $this->findAccreditedAttendee($validated['attendeeId'], $eventId);
        if (!$attendee) {
            return response()->json(['success' => false, 'message' => 'Accredited attendee not found.'], 404);
        }

        $certificate = $this->issueCertificate($attendee, $event, $validated['type']);
        $pdf         = $this->certificateService->generate($attendee, $certificate, $event);

        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $certificate->certificateNumber . '.pdf"');
    }

    /**
     * POST /events/{eventId}/certificates/send
     *
     * Generates (if needed) and emails the certificate to the attendee.
     */
    public function send(Request $request, int $eventId): JsonResponse
    {
        $validated = $this->validateIssue($request);

        $event = $this->findEvent($eventId);
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Event not found.'], 404);
        }

        $attendee = $this->findAccreditedAttendee($validated['attendeeId'], $eventId);
        if (!$attendee) {
            return response()->json(['success' => false, 'message' => 'Accredited attendee not found.'], 404);
        }

        if (empty($attendee->email)) {
            return response()->json([
                'success' => false,
                'message' => 'This participant has no email on record.',
            ], 422);
        }

        $certificate = $this->issueCertificate($attendee, $event, $validated['type']);
        $pdf         = $this->certificateService->generate($attendee, $certificate, $event);

        Mail::to($attendee->email)->send(
            new CertificateMail($attendee, $certificate, $pdf, CertificateService::label($certificate->type))
        );

        $certificate->update(['sentAt' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Certificate emailed to ' . $attendee->email . '.',
        ]);
    }

    /**
     * POST /events/{eventId}/certificates/bulk-send
     *
     * Generates and emails certificates of one type to many attendees.
     */
    public function bulkSend(Request $request, int $eventId): JsonResponse
    {
        $validated = $request->validate([
            'attendeeIds'   => ['required', 'array', 'min:1'],
            'attendeeIds.*' => ['integer'],
            'type'          => ['required', Rule::in(CertificateService::typeKeys())],
        ]);

        $event = $this->findEvent($eventId);
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Event not found.'], 404);
        }

        $attendees = Attendee::where('eventId', $eventId)
            ->where('isAccredited', true)
            ->whereIn('attendeeId', $validated['attendeeIds'])
            ->get();

        $generated = 0;
        $sent      = 0;
        $skipped   = 0;
        $failed    = [];

        foreach ($attendees as $attendee) {
            if (empty($attendee->email)) {
                $skipped++;
                continue;
            }

            $certificate = $this->issueCertificate($attendee, $event, $validated['type']);
            $generated++;

            try {
                $pdf = $this->certificateService->generate($attendee, $certificate, $event);

                Mail::to($attendee->email)->send(
                    new CertificateMail($attendee, $certificate, $pdf, CertificateService::label($certificate->type))
                );

                $certificate->update(['sentAt' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                $failed[] = $attendee->attendeeId;
                report($e);
            }
        }

        // Requested ids that weren't accredited / not found in this event.
        $skipped += max(0, count($validated['attendeeIds']) - $attendees->count());

        $message = "Sent {$sent} certificate(s).";
        if ($skipped) {
            $message .= " Skipped {$skipped} (no email or not accredited).";
        }
        if (count($failed)) {
            $message .= ' ' . count($failed) . ' failed to send.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => [
                'generated' => $generated,
                'sent'      => $sent,
                'skipped'   => $skipped,
                'failed'    => $failed,
            ],
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function validateIssue(Request $request): array
    {
        return $request->validate([
            'attendeeId' => ['required', 'integer'],
            'type'       => ['required', Rule::in(CertificateService::typeKeys())],
        ]);
    }

    private function findEvent(int $eventId): ?Event
    {
        return Event::where('eventId', $eventId)->first();
    }

    private function findAccreditedAttendee(int $attendeeId, int $eventId): ?Attendee
    {
        return Attendee::where('attendeeId', $attendeeId)
            ->where('eventId', $eventId)
            ->where('isAccredited', true)
            ->first();
    }

    /**
     * One certificate per (event, attendee, type). Re-issuing keeps the same
     * record and number; only the first issue stamps issuedBy / issuedAt.
     */
    private function issueCertificate(Attendee $attendee, Event $event, string $type): Certificate
    {
        $certificate = Certificate::firstOrNew([
            'eventId'    => $event->eventId,
            'attendeeId' => $attendee->attendeeId,
            'type'       => $type,
        ]);

        if (!$certificate->exists) {
            $certificate->certificateNumber = $this->generateCertificateNumber($event);
            $certificate->issuedBy          = Auth::id();
            $certificate->issuedAt          = now();
        }

        $certificate->save();

        return $certificate;
    }

    private function generateCertificateNumber(Event $event): string
    {
        $count = Certificate::where('eventId', $event->eventId)->count();

        return strtoupper('CERT-' . now()->format('Y') . '-' . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT));
    }

    private function fullName(Attendee $attendee): string
    {
        return trim(
            ($attendee->firstName ?? '') . ' ' .
            ($attendee->lastName ?? '') . ' ' .
            ($attendee->otherNames ?? '')
        );
    }
}