<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meal extends Model
{
    protected $table = 'meals';

    protected $primaryKey = 'mealId';
    protected $fillable = [
        'title',
        'slug',
        'description',
        'mealDate',
        'startTime',
        'endTime',
        'location',
        'status',
        'ticketCount',
        'redeemedCount',
        'createdBy',
    ];

    public function tickets()
    {
        return $this->hasMany(MealTicket::class, 'mealId', 'mealId');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'createdBy', 'id');
    }

    public function scanLogs()
{
    return $this->hasMany(\App\Models\ScanLog::class);
}
}