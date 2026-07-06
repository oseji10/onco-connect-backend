<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomAllocation extends Model
{
    protected $primaryKey = 'allocationId';

    protected $fillable = [
        'eventId',
        'attendeeId',
        'roomId',
        'roomNumber',
        'hotel',
        'allocationType',
        'status',
        'reason',
        'allocatedAt',
        'allocatedBy',
        'previousAllocationId',
    ];

    protected $casts = [
        'allocatedAt' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }

    public function attendee()
    {
        return $this->belongsTo(Attendee::class, 'attendeeId', 'attendeeId');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'roomId', 'roomId');
    }

    public function allocator()
    {
        return $this->belongsTo(User::class, 'allocatedBy');
    }

    public function previousAllocation()
    {
        return $this->belongsTo(RoomAllocation::class, 'previousAllocationId', 'allocationId');
    }

    
}