<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MealScanAttempt extends Model
{
    protected $table = 'meal_scan_attempts';
    protected $primaryKey = 'attemptId';

    protected $fillable = [
        'mealSessionId', 'passId', 'token', 'result', 'message',
        'scannedBy', 'deviceName', 'ipAddress',
    ];
}
