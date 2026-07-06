<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $primaryKey = 'eventId';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'startDate',
        'endDate',
        'location',
        'status',
        'passCount',
        'createdBy',
        'attendanceCloseTime',
        'attendanceLockEnabled',
    ];

     protected $casts = [
        'attendanceLockEnabled' => 'boolean',
    ];

    public function mealSessions()
    {
        return $this->hasMany(MealSession::class, 'eventId', 'eventId');
    }

//     public function mealSessions()
// {
//     return $this->hasMany(MealSession::class, 'eventId', 'eventId')
//         ->whereHas('event', function ($query) {
//             $query->where('status', 'active');
//         });
// }

    public function passes()
    {
        return $this->hasMany(EventPass::class, 'eventId', 'eventId');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'createdBy');
    }


    public function attendees()
{
    return $this->hasMany(Attendee::class, 'eventId', 'eventId');
}

public function dailyAttendances()
{
    return $this->hasMany(DailyAttendance::class, 'eventId', 'eventId');
}

public function rooms()
{
    return $this->hasMany(Room::class, 'eventId', 'eventId');
}

public function roomAllocations()
{
    return $this->hasMany(RoomAllocation::class, 'eventId', 'eventId');
}

public function incidents()
{
    return $this->hasMany(Incident::class, 'eventId', 'eventId');
}

// public function passes()
// {
//     return $this->hasMany(EventPass::class, 'eventId', 'eventId');
// }
}