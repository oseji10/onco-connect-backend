<?php
// app/Services/CertificateService.php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Event;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class CertificateService
{
    public function __construct(protected EligibilityService $eligibility) {}

    /** Returns a PDF download response, or a 403 JSON response if still locked. */
    public function download(Attendee $attendee, Event $event)
    {
        // Recompute so a stale flag can never unlock (or keep locked) a certificate.
        $this->eligibility->recalculate($attendee);
        $attendee->refresh();

        if (!$attendee->certificateEligible) {
            return response()->json([
                'success' => false,
                'message' => 'Your certificate is locked. Submit the questionnaire and make sure your attendance is confirmed.',
            ], 403);
        }

        $logoPath = config('certificate.logo_path') ? public_path(config('certificate.logo_path')) : null;
        $logo = null;
        if ($logoPath && is_file($logoPath)) {
            $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
            $mime = $ext === 'jpg' ? 'jpeg' : $ext;
            $logo = "data:image/{$mime};base64," . base64_encode(file_get_contents($logoPath));
        }

        $pdf = Pdf::loadView('pdf.certificate', [
            'name'         => trim(implode(' ', array_filter([$attendee->title, $attendee->firstName, $attendee->lastName, $attendee->otherNames]))),
            'eventName'    => config('certificate.event_name') ?: ($event->name ?? $event->title ?? 'the conference'),
            'dates'        => $this->dateRange($event),
            'location'     => $event->location ?? null,
            'organization' => config('certificate.organization'),
            'signatories'  => config('certificate.signatories', []),
            'logo'         => $logo,
            'number'       => 'CERT-' . $attendee->uniqueId,
            'issuedOn'     => now()->format('j F Y'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('certificate-' . $attendee->uniqueId . '.pdf');
    }

    private function dateRange(Event $event): ?string
    {
        if (!$event->startDate) return null;

        $start = Carbon::parse($event->startDate);
        $end   = $event->endDate ? Carbon::parse($event->endDate) : $start;

        return $start->isSameDay($end)
            ? $start->format('j F Y')
            : $start->format('j M Y') . ' – ' . $end->format('j M Y');
    }
}