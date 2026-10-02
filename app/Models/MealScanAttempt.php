<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealScanAttempt extends Model
{
    protected $table = 'meal_scan_attempts';
    protected $primaryKey = 'attemptId';

    protected $fillable = [
        'mealSessionId', 'passId', 'token', 'result', 'message',
        'scannedBy', 'deviceName', 'ipAddress',
    ];
}