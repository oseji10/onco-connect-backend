<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPass extends Model
{
    protected $primaryKey = 'passId';

    protected $fillable = [
        'eventId',
        'passCode',
        'attendeeId',
        'serialNumber',
        'qrPayload',
        'qrPath',
        'qrUrl',
        'status',
        'isAssigned',
        'assignedAt',
        'assignedBy',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }

     public function attendee(){
        return $this->belongsTo(Attendee::class, 'attendeeId', 'attendeeId');
    }

    public function redemptions()
    {
        return $this->hasMany(MealRedemption::class, 'passId', 'passId');
    }

    public function scanLogs()
    {
        return $this->hasMany(ScanLog::class, 'passId', 'passId');
    }

    public function dailyAttendances()
{
    return $this->hasMany(DailyAttendance::class, 'attendeeId', 'attendeeId');
}
}