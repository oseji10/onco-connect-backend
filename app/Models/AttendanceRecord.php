<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    protected $table = 'attendance_records';
    protected $primaryKey = 'attendanceId';
    protected $fillable = ['attendeeId', 'sessionId', 'method', 'checkedInAt', 'recordedBy'];
    protected $casts = ['checkedInAt' => 'datetime'];
}
