<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentUpdate extends Model
{
    protected $primaryKey = 'updateId';

    protected $fillable = [
        'incidentId',
        'updatedBy',
        'oldStatus',
        'newStatus',
        'note',
    ];

    public function incident()
    {
        return $this->belongsTo(Incident::class, 'incidentId', 'incidentId');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updatedBy');
    }
}