<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyAttendance extends Model
{
    protected $primaryKey = 'attendanceId';

    protected $fillable = [
        'eventId',
        'attendeeId',
        'eventPassId',
        'attendanceDate',
        'markedAt',
        'markedBy',
        'deviceName',
        'scanSource',
        'notes',
    ];

    protected $casts = [
        'attendanceDate' => 'date',
        'markedAt' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }

    public function attendee()
    {
        return $this->belongsTo(Attendee::class, 'attendeeId', 'attendeeId');
    }

    public function pass()
    {
        return $this->belongsTo(EventPass::class, 'eventPassId', 'passId');
    }

    public function marker()
    {
        return $this->belongsTo(User::class, 'markedBy');
    }
}