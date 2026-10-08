<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per time a participant downloads a certificate PDF.
 */
class CertificateDownload extends Model
{
    protected $fillable = [
        'certificateId',
        'attendeeId',
        'eventId',
        'type',
        'source',
        'ipAddress',
        'userAgent',
    ];
}