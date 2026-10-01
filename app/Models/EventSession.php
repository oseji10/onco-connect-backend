<?php
// Split these into app/Models/EventSession.php, AttendanceRecord.php, EventFeedback.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventSession extends Model
{
    protected $table = 'event_sessions';
    protected $primaryKey = 'sessionId';
    protected $fillable = ['eventId', 'title', 'startsAt', 'endsAt'];
    protected $casts = ['startsAt' => 'datetime', 'endsAt' => 'datetime'];
}

class AttendanceRecord extends Model
{
    protected $table = 'attendance_records';
    protected $primaryKey = 'attendanceId';
    protected $fillable = ['attendeeId', 'sessionId', 'method', 'checkedInAt', 'recordedBy'];
    protected $casts = ['checkedInAt' => 'datetime'];
}

class EventFeedback extends Model
{
    protected $table = 'event_feedback';
    protected $primaryKey = 'feedbackId';
    protected $fillable = ['attendeeId', 'eventId', 'rating', 'takeaway', 'comments'];
}

/*
 * ADD to App\Models\Attendee:
 *
 * protected $casts = [ ...existing..., 'certificateEligible' => 'boolean', 'manualOverride' => 'boolean' ];
 *
 * public function attendanceRecords() { return $this->hasMany(AttendanceRecord::class, 'attendeeId', 'attendeeId'); }
 * public function feedback()          { return $this->hasOne(EventFeedback::class, 'attendeeId', 'attendeeId'); }
 *
 * Make sure the new columns are fillable OR keep using forceFill() as the service does.
 */