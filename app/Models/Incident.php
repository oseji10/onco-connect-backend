<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $primaryKey = 'incidentId';

    protected $fillable = [
        'eventId',
        'incidentCode',
        'title',
        'description',
        'category',
        'severity',
        'status',
        'reportedBy',
        'assignedTo',
        'attendeeId',
        'roomId',
        'location',
        'occurredAt',
        'reportedAt',
        'resolvedAt',
        'resolutionNote',
        'isAnonymous',
    ];

    protected $casts = [
        'occurredAt' => 'datetime',
        'reportedAt' => 'datetime',
        'resolvedAt' => 'datetime',
        'isAnonymous' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reportedBy');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignedTo');
    }

    public function attendee()
    {
        return $this->belongsTo(Attendee::class, 'attendeeId', 'attendeeId');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'roomId', 'roomId');
    }

    public function updates()
    {
        return $this->hasMany(IncidentUpdate::class, 'incidentId', 'incidentId');
    }
}