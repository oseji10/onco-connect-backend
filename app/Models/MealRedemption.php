<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MealRedemption extends Model
{
    protected $primaryKey = 'redemptionId';

    protected $fillable = [
        'mealSessionId',
        'passId',
        'redeemedBy',
        'deviceName',
        'redeemedAt',
    ];

    protected $casts = [
        'redeemedAt' => 'datetime',
    ];

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
        return $this->belongsTo(User::class, 'redeemedBy');
    }
}