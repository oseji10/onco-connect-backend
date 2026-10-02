<?php
// Split into app/Models/MealSession.php, MealRedemption.php, MealScanAttempt.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MealSession extends Model
{
    protected $primaryKey = 'mealSessionId';

    protected $fillable = [
        'eventId', 'title', 'slug', 'description', 'mealDate',
        'startTime', 'endTime', 'location', 'status', 'sortOrder', 'redeemedCount',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }

    public function redemptions()
    {
        return $this->hasMany(MealRedemption::class, 'mealSessionId', 'mealSessionId');
    }
}



