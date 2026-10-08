<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\CertificateDownload;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CertificateDownloadLogger
{
    /**
     * Record one certificate download.
     *
     * Logging must never prevent the certificate from being downloaded.
     */
    public function log(
        Attendee $attendee,
        Event $event,
        string $type,
        string $source = 'public',
        ?Request $request = null
    ): void {
        try {
            CertificateDownload::create([
                'attendeeId' => $attendee->attendeeId,
                'eventId'    => $event->eventId,
                'type'       => $type,
                'source'     => $source,

                'ipAddress' => $request?->ip(),

                'userAgent' => $request
                    ? Str::limit(
                        (string) $request->userAgent(),
                        1000,
                        ''
                    )
                    : null,
            ]);
        } catch (\Throwable $e) {
            /*
             * A tracking failure must NEVER prevent certificate delivery.
             */
            report($e);
        }
    }
}