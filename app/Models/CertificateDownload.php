<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateDownload extends Model
{
    protected $table = 'certificate_downloads';

    protected $fillable = [
        'attendeeId',
        'eventId',
        'type',
        'source',
        'ipAddress',
        'userAgent',
    ];

    public function attendee()
    {
        return $this->belongsTo(
            Attendee::class,
            'attendeeId',
            'attendeeId'
        );
    }

    public function event()
    {
        return $this->belongsTo(
            Event::class,
            'eventId',
            'eventId'
        );
    }
}