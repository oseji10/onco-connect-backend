<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealTicket extends Model
{
    protected $table = 'meal_tickets';

    protected $primaryKey = 'mealTicketId';
    protected $fillable = [
        'mealId',
        'token',
        'serialNumber',
        'qrPayload',
        'qrPath',
        'qrUrl',
        'status',
        'redeemedAt',
        'redeemedBy',
        'lastScannedAt',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
        'lastScannedAt' => 'datetime',
    ];

    public function meal()
    {
        return $this->belongsTo(Meal::class, 'mealId', 'mealId');
    }

    public function redeemer()
    {
        return $this->belongsTo(User::class, 'redeemedBy', 'id');
    }

    public function scanLogs()
    {
        return $this->hasMany(ScanLog::class);
    }
}