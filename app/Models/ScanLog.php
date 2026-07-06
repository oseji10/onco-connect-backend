<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanLog extends Model
{
    protected $primaryKey = 'scanLogId';

    protected $fillable = [
        'eventId',
        'mealSessionId',
        'passId',
        'token',
        'scanResult',
        'message',
        'scannedBy',
        'deviceName',
        'ipAddress',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }

    public function mealSession()
    {
        return $this->belongsTo(MealSession::class, 'mealSessionId', 'mealSessionId');
    }

    public function pass()
    {
        return $this->belongsTo(EventPass::class, 'passId', 'passId');
    }

    public function scanner()
    {
        return $this->belongsTo(User::class, 'scannedBy');
    }
}