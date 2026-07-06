<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'roleId';

    protected $fillable = [
        'roleName',
        'isStaff'
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