<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffFeedback extends Model
{
    protected $fillable = [
        'feedback_id',
        'staff_id',
        'performance',
        'approachability',
        'effectiveness',
        'strength',
        'improvement',
    ];

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}