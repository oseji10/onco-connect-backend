<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodSupply extends Model
{
    protected $table = 'food_supplies';
    protected $primaryKey = 'supplyId';

    protected $fillable = [
        'eventId',
        'mealSessionId',
        'foodItem',
        'vendorName',
        'quantitySupplied',
        'quantityDistributed',
        'quantityRemaining',
        'supplyDate',
        'notes',
        'recordedBy',
    ];

    protected $casts = [
        'supplyDate' => 'date',
        'quantitySupplied' => 'integer',
        'quantityDistributed' => 'integer',
        'quantityRemaining' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }

    public function mealSession(): BelongsTo
    {
        return $this->belongsTo(MealSession::class, 'mealSessionId', 'mealSessionId');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recordedBy');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(FoodDistribution::class, 'foodSupplyId', 'supplyId');
    }
}
