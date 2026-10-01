<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Certificate;
use App\Models\Event;
use Barryvdh\DomPDF\Facade\Pdf;

class CertificateService
{
    /**
     * key => label + Blade view.
     * `key` must match CERTIFICATE_TYPES in the React page.
     */
    public const TYPES = [
        'attendance'       => ['label' => 'Certificate of Attendance',          'view' => 'certificates.attendance'],
        'oral_presenter'   => ['label' => 'Certificate of Oral Presentation',   'view' => 'certificates.oral'],
        'poster_presenter' => ['label' => 'Certificate of Poster Presentation', 'view' => 'certificates.poster'],
    ];

    public static function typeKeys(): array
    {
        return array_keys(self::TYPES);
    }

    public static function label(string $type): string
    {
        return self::TYPES[$type]['label'] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public static function view(string $type): string
    {
        return self::TYPES[$type]['view'] ?? 'certificates.attendance';
    }

    /**
     * Render the certificate PDF and return the raw bytes.
     */
    public function generate(Attendee $attendee, Certificate $certificate, Event $event): string
    {
        $data = [
            'typeLabel' => self::label($certificate->type),
            'fullName'  => $this->fullName($attendee),
        ];

        return Pdf::loadView(self::view($certificate->type), $data)
            ->setPaper('a4', 'landscape')
            ->output();
    }

    protected function fullName(Attendee $attendee): string
    {
        return trim(implode(' ', array_filter([
            $attendee->title ?? null,
            $attendee->firstName ?? null,
            $attendee->lastName ?? null,
            $attendee->otherNames ?? null,
        ])));
    }
}