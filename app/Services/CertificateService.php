<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Event;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class CertificateService
{
    public const TYPE_ATTENDANCE = 'attendance';
    public const TYPE_ORAL       = 'oral_presenter';
    public const TYPE_POSTER     = 'poster_presenter';

    public function __construct(
        protected EligibilityService $eligibility
    ) {}

    /**
     * All certificate types supported by the public certificate flow.
     *
     * @return array<int, string>
     */
    public static function typeKeys(): array
    {
        return [
            self::TYPE_ATTENDANCE,
            self::TYPE_ORAL,
            self::TYPE_POSTER,
        ];
    }

    /**
     * Human-readable certificate labels.
     */
    public static function label(string $type): string
    {
        return match ($type) {
            self::TYPE_ATTENDANCE => 'Certificate of Attendance',
            self::TYPE_ORAL       => 'Oral Presentation Certificate',
            self::TYPE_POSTER     => 'Poster Presentation Certificate',
            default               => 'Certificate',
        };
    }

    /**
     * Return the attendee's complete name.
     */
    public function fullName(Attendee $attendee): string
    {
        return trim(implode(' ', array_filter([
            $attendee->title,
            $attendee->firstName,
            $attendee->lastName,
            $attendee->otherNames,
        ], function ($value) {
            return $value !== null && trim((string) $value) !== '';
        })));
    }

    /**
     * Return the background image for the certificate type.
     *
     * Files:
     *
     * storage/app/public/images/certificate-attendance.jpg
     * storage/app/public/images/certificate-oral.jpg
     * storage/app/public/images/certificate-poster.jpg
     */
    private function backgroundPath(string $type): string
    {
        return match ($type) {
            self::TYPE_ORAL =>
                storage_path('app/public/images/certificate-bg-oral.png'),

            self::TYPE_POSTER =>
                storage_path('app/public/images/certificate-bg-poster.png'),

            default =>
                storage_path('app/public/images/certificate-bg-attendance.png'),
        };
    }

    /**
     * Prepare certificate data.
     *
     * This does not persist anything to the database.
     * It creates the certificate payload used by the PDF generator.
     *
     * @return array<string, mixed>
     */
    public function issue(
        Attendee $attendee,
        Event $event,
        string $type
    ): array {
        if (!in_array($type, self::typeKeys(), true)) {
            throw new \InvalidArgumentException(
                "Unsupported certificate type: {$type}"
            );
        }

        return [
            'type' => $type,

            'typeLabel' => self::label($type),

            'fullName' => $this->fullName($attendee),

            'eventName' => config('certificate.event_name')
                ?: ($event->name ?? $event->title ?? 'International Cancer Week'),

            'issuedDate' => now()->format('j F Y'),

            'certificateNumber' => $this->certificateNumber(
                $attendee,
                $type
            ),

            'bodyText' => $this->bodyText($type),

            'dates' => $this->dateRange($event),

            'location' => $event->location ?? null,

            /*
             * The exact background image for this certificate.
             */
            'backgroundPath' => $this->backgroundPath($type),
        ];
    }

    /**
     * Generate the actual certificate PDF.
     *
     * These views should exist:
     *
     * resources/views/certificates/attendance.blade.php
     * resources/views/certificates/oral.blade.php
     * resources/views/certificates/poster.blade.php
     */
    public function generate(
        Attendee $attendee,
        array $certificate,
        Event $event
    ): string {
        $type = $certificate['type'] ?? self::TYPE_ATTENDANCE;

        $view = match ($type) {
            self::TYPE_ORAL =>
                'certificates.oral',

            self::TYPE_POSTER =>
                'certificates.poster',

            default =>
                'certificates.attendance',
        };

        /*
         * Resolve the background.
         *
         * If the certificate payload already contains a background path,
         * use it. Otherwise calculate it from the certificate type.
         */
        $backgroundPath = $certificate['backgroundPath']
            ?? $this->backgroundPath($type);

        /*
         * Fail early if the approved certificate design is missing.
         *
         * This is much better than silently generating a certificate
         * without its background.
         */
        if (!is_file($backgroundPath)) {
            throw new \RuntimeException(
                'Certificate background image not found: ' . $backgroundPath
            );
        }

        $data = [
            'eventName' => $certificate['eventName']
                ?? config('certificate.event_name')
                ?? ($event->name ?? $event->title ?? 'International Cancer Week'),

            'typeLabel' => $certificate['typeLabel']
                ?? self::label($type),

            'fullName' => $certificate['fullName']
                ?? $this->fullName($attendee),

            'bodyText' => $certificate['bodyText']
                ?? $this->bodyText($type),

            'issuedDate' => $certificate['issuedDate']
                ?? now()->format('j F Y'),

            'certificateNumber' => $certificate['certificateNumber']
                ?? $this->certificateNumber($attendee, $type),

            'dates' => $certificate['dates']
                ?? $this->dateRange($event),

            'location' => $certificate['location']
                ?? ($event->location ?? null),

            'organization' => config('certificate.organization'),

            'signatories' => config('certificate.signatories', []),

            'logo' => $this->logoData(),

            'certificateType' => $type,

            /*
             * THIS is what layout.blade.php uses.
             */
            'backgroundPath' => $backgroundPath,
        ];

        $pdf = Pdf::loadView($view, $data)
            ->setPaper('a4', 'landscape');

        return $pdf->output();
    }

    /**
     * Legacy attendance certificate download method.
     *
     * Kept for compatibility with existing code.
     */
    public function download(
        Attendee $attendee,
        Event $event
    ) {
        /*
         * Always recalculate eligibility so a stale database value
         * cannot accidentally unlock or lock a certificate.
         */
        $this->eligibility->recalculate($attendee);

        $attendee->refresh();

        if (!$attendee->certificateEligible) {
            return response()->json([
                'success' => false,
                'message' => 'Your certificate is locked. Submit the questionnaire and make sure your attendance is confirmed.',
            ], 403);
        }

        $certificate = $this->issue(
            $attendee,
            $event,
            self::TYPE_ATTENDANCE
        );

        $pdf = $this->generate(
            $attendee,
            $certificate,
            $event
        );

        $id = $attendee->uniqueId ?: $attendee->attendeeId;

        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header(
                'Content-Disposition',
                'attachment; filename="certificate-' . $id . '.pdf"'
            );
    }

    /**
     * Text used by the certificate body.
     *
     * The layout renders:
     *
     * "in recognition of having {{ $bodyText }} {{ $eventName }}."
     */
    private function bodyText(string $type): string
    {
        return match ($type) {
            self::TYPE_ORAL =>
                'delivering an oral presentation at the',

            self::TYPE_POSTER =>
                'presenting a poster at the',

            default =>
                'participating in the',
        };
    }

    /**
     * Generate the certificate number.
     */
    private function certificateNumber(
        Attendee $attendee,
        string $type
    ): string {
        $id = $attendee->uniqueId ?: $attendee->attendeeId;

        return match ($type) {
            self::TYPE_ORAL =>
                'CERT-' . $id . '-ORAL',

            self::TYPE_POSTER =>
                'CERT-' . $id . '-POSTER',

            default =>
                'CERT-' . $id,
        };
    }

    /**
     * Convert the configured logo to a base64 data URI.
     */
    private function logoData(): ?string
    {
        $configuredPath = config('certificate.logo_path');

        if (!$configuredPath) {
            return null;
        }

        $logoPath = public_path($configuredPath);

        if (!is_file($logoPath)) {
            return null;
        }

        $extension = strtolower(
            pathinfo($logoPath, PATHINFO_EXTENSION)
        );

        $mime = match ($extension) {
            'jpg',
            'jpeg' => 'jpeg',

            'png' => 'png',

            'gif' => 'gif',

            'svg' => 'svg+xml',

            'webp' => 'webp',

            default => null,
        };

        if (!$mime) {
            return null;
        }

        $contents = file_get_contents($logoPath);

        if ($contents === false) {
            return null;
        }

        return "data:image/{$mime};base64," . base64_encode($contents);
    }

    /**
     * Format the event date range.
     */
    private function dateRange(Event $event): ?string
    {
        if (!$event->startDate) {
            return null;
        }

        $start = Carbon::parse($event->startDate);

        $end = $event->endDate
            ? Carbon::parse($event->endDate)
            : $start;

        return $start->isSameDay($end)
            ? $start->format('j F Y')
            : $start->format('j M Y') . ' – ' . $end->format('j M Y');
    }
}
