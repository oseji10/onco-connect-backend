<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitLog extends Model
{
    protected $table = 'exit_logs';
    protected $primaryKey = 'exitLogId';

    protected $fillable = [
        'eventId',
        'attendeeId',
        'reason',
        'additionalNotes',
        'exitTime',
        'returnTime',
        'durationMinutes',
        'status',
        'recordedBy',
        'returnRecordedBy',
    ];

    protected $casts = [
        'exitTime' => 'datetime',
        'returnTime' => 'datetime',
        'durationMinutes' => 'integer',
    ];

    /**
     * Get the event this exit log belongs to
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }

    /**
     * Get the attendee who exited
     */
    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class, 'attendeeId', 'attendeeId');
    }

    /**
     * Get the user who recorded the exit
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recordedBy', 'id');
    }

    /**
     * Get the user who recorded the return
     */
    public function returnRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returnRecordedBy', 'id');
    }

    /**
     * Check if participant is currently out
     */
    public function isOut(): bool
    {
        return $this->status === 'out' && is_null($this->returnTime);
    }

    /**
     * Get time away in human readable format
     */
    public function getTimeAwayAttribute(): string
    {
        if ($this->returnTime) {
            return $this->durationMinutes . ' minutes';
        }

        $minutes = now()->diffInMinutes($this->exitTime);
        
        if ($minutes < 60) {
            return $minutes . ' min';
        } elseif ($minutes < 1440) { // Less than a day
            $hours = floor($minutes / 60);
            $mins = $minutes % 60;
            return $hours . 'h ' . $mins . 'm';
        } else {
            $days = floor($minutes / 1440);
            $hours = floor(($minutes % 1440) / 60);
            return $days . 'd ' . $hours . 'h';
        }
    }
}