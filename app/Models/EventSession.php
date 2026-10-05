<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventSession extends Model
{
    protected $table = 'event_sessions';
    protected $primaryKey = 'sessionId';
    protected $fillable = ['eventId', 'title', 'startsAt', 'endsAt'];
    protected $casts = ['startsAt' => 'datetime', 'endsAt' => 'datetime'];
}
