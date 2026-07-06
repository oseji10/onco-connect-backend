<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feedback extends Model
{
    protected $fillable = [
        'overall_rating',
        'organization',
        'communication',
        'respected',
    ];

    public function staffFeedbacks()
    {
        return $this->hasMany(StaffFeedback::class);
    }
}