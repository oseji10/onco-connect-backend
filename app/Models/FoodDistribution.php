<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodDistribution extends Model
{
    protected $table = 'food_distributions';
    protected $primaryKey = 'distributionId';

    protected $fillable = [
        'eventId',
        'mealSessionId',
        'attendeeId',
        'foodSupplyId',
        'ticketId',
        'distributedBy',
        'deviceName',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }

    public function mealSession(): BelongsTo
    {
        return $this->belongsTo(MealSession::class, 'mealSessionId', 'mealSessionId');
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class, 'attendeeId', 'attendeeId');
    }

    public function foodSupply(): BelongsTo
    {
        return $this->belongsTo(FoodSupply::class, 'foodSupplyId', 'supplyId');
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'distributedBy');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(MealTicket::class, 'ticketId', 'ticketId');
    }

    public function rating(): HasMany
    {
        return $this->hasMany(MealRating::class, 'mealSessionId', 'mealSessionId')
            ->where('attendeeId', $this->attendeeId);
    }
}