<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; 

class MealSession extends Model
{
    protected $primaryKey = 'mealSessionId';

    protected $fillable = [
        'eventId',
        'title',
        'slug',
        'description',
        'mealDate',
        'startTime',
        'endTime',
        'location',
        'status',
        'sortOrder',
        'redeemedCount',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }

    public function redemptions()
    {
        return $this->hasMany(MealRedemption::class, 'mealSessionId', 'mealSessionId');
    }

    public function scanLogs()
    {
        return $this->hasMany(ScanLog::class, 'mealSessionId', 'mealSessionId');
    }

    public function foodSupplies(): HasMany
{
    return $this->hasMany(FoodSupply::class, 'mealSessionId', 'mealSessionId');
}

public function foodDistributions(): HasMany
{
    return $this->hasMany(FoodDistribution::class, 'mealSessionId', 'mealSessionId');
}

public function ratings(): HasMany
{
    return $this->hasMany(MealRating::class, 'mealSessionId', 'mealSessionId');
}



    public function food_supplies(): HasMany
    {
        return $this->hasMany(FoodSupply::class, 'mealSessionId', 'mealSessionId');
    }

    /**
     * Get the average rating for this meal session
     */
    public function getAverageRatingAttribute(): float
    {
        return $this->ratings()->avg('rating') ?? 0;
    }

    /**
     * Get the total number of ratings for this meal session
     */
    public function getTotalRatingsAttribute(): int
    {
        return $this->ratings()->count();
    }

    /**
     * Scope to get only active meal sessions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get rateable meals (completed meals from the last 7 days)
     */
    public function scopeRateable($query)
    {
        return $query->where('mealDate', '>=', now()->subDays(7))
                    ->where('mealDate', '<=', now())
                    ->orderByDesc('mealDate');
    }


// Helper to get inventory summary
public function getInventorySummary(): array
{
    $supplies = $this->foodSupplies;
    
    return [
        'totalSupplied' => $supplies->sum('quantitySupplied'),
        'totalDistributed' => $supplies->sum('quantityDistributed'),
        'totalRemaining' => $supplies->sum('quantityRemaining'),
    ];
}

// Helper to get average rating
public function getAverageRating(): ?float
{
    $avg = $this->ratings()->avg('rating');
    return $avg ? round($avg, 2) : null;
}


}



