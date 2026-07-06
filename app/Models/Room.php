<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $primaryKey = 'roomId';

    protected $fillable = [
        'eventId',
        'name',
        'code',
        'building',
        'capacity',
        'gender',
        'status',
        'description',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }

    public function allocations()
    {
        return $this->hasMany(RoomAllocation::class, 'roomId', 'roomId');
    }

    public function activeAllocations()
    {
        return $this->hasMany(RoomAllocation::class, 'roomId', 'roomId')
            ->where('status', 'active');
    }

    public function incidents()
{
    return $this->hasMany(Incident::class, 'roomId', 'roomId');
}
}