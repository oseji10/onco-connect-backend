<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Certificate;
use App\Models\Event;
use Barryvdh\DomPDF\Facade\Pdf; // If your PassPdfService imports PDF differently, match it.

class CertificateService
{
    /**
     * Certificate types. Keys must match the front end's CERTIFICATE_TYPES.
     * `body` is the predicate dropped into the certificate sentence:
     * "...in recognition of having {body} {Event}."
     */
    public const TYPES = [
        'attendance'    => ['label' => 'Certificate of Attendance',    'body' => 'attended'],
        'participation' => ['label' => 'Certificate of Participation', 'body' => 'actively participated in'],
        'speaker'       => ['label' => 'Speaker Certificate',          'body' => 'served as a speaker at'],
        'facilitator'   => ['label' => 'Facilitator Certificate',      'body' => 'served as a facilitator at'],
        'exhibitor'     => ['label' => 'Exhibitor Certificate',        'body' => 'participated as an exhibitor at'],
    ];

    public static function typeKeys(): array
    {
        return array_keys(self::TYPES);
    }

    public static function label(string $type): string
    {
        return self::TYPES[$type]['label'] ?? (ucfirst($type) . ' Certificate');
    }

    public static function body(string $type): string
    {
        return self::TYPES[$type]['body'] ?? 'participated in';
    }

    /**
     * Render the certificate to PDF and return the raw bytes.
     */
    public function generate(Attendee $attendee, Certificate $certificate, Event $event): string
    {
        $pdf = Pdf::loadView('certificates.template', [
            'fullName'          => $this->fullName($attendee),
            'typeLabel'         => self::label($certificate->type),
            'bodyText'          => self::body($certificate->type),
            'eventName'         => $event->name ?? $event->title ?? 'the Conference',
            'certificateNumber' => $certificate->certificateNumber,
            'issuedDate'        => optional($certificate->issuedAt)->format('F j, Y') ?? now()->format('F j, Y'),
        ])->setPaper('a4', 'landscape');

        return $pdf->output();
    }

    private function fullName(Attendee $attendee): string
    {
        return trim(
            ($attendee->title ? $attendee->title . ' ' : '') .
            ($attendee->firstName ?? '') . ' ' .
            ($attendee->lastName ?? '') . ' ' .
            ($attendee->otherNames ?? '')
        );
    }
}