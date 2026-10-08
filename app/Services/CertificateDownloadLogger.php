<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Certificate;
use App\Models\CertificateDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CertificateDownloadLogger
{
    /**
     * Record one download. Never throws: a logging problem must not stop
     * someone from getting their certificate.
     *
     * @param string $source 'public' (email + phone page) or 'participant' (logged in)
     */
    public function log(Certificate $certificate, Attendee $attendee, string $source, ?Request $request = null): void
    {
        try {
            CertificateDownload::create([
                'certificateId' => $certificate->certificateId,
                'attendeeId'    => $attendee->attendeeId,
                'eventId'       => $certificate->eventId ?? $attendee->eventId,
                'type'          => $certificate->type,
                'source'        => $source,
                'ipAddress'     => $request?->ip(),
                'userAgent'     => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}