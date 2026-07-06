<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    protected $primaryKey = 'certificateId';

    protected $fillable = [
        'eventId',
        'attendeeId',
        'type',
        'certificateNumber',
        'issuedBy',
        'issuedAt',
        'sentAt',
    ];

    protected $casts = [
        'issuedAt' => 'datetime',
        'sentAt'   => 'datetime',
    ];

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class, 'attendeeId', 'attendeeId');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuedBy', 'id');
    }
}