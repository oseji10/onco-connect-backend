<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealRating extends Model
{
    protected $table = 'meal_ratings';
    
    protected $primaryKey = 'ratingId';

    protected $fillable = [
        'mealSessionId',
        'rating',
        'comment',
        'deviceFingerprint',
        'eventId',
    ];

    protected $casts = [
        'rating' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the meal session that owns this rating
     */
    public function mealSession(): BelongsTo
    {
        return $this->belongsTo(MealSession::class, 'mealSessionId', 'mealSessionId');
    }

    /**
     * Scope to get ratings with comments
     */
    public function scopeWithComments($query)
    {
        return $query->whereNotNull('comment')
                    ->where('comment', '!=', '');
    }

    /**
     * Scope to get ratings by star level
     */
    public function scopeByRating($query, int $rating)
    {
        return $query->where('rating', $rating);
    }
}