<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackStaffRating extends Model
{
    protected $fillable = [
        'feedback_submission_id',
        'staff_id',          // FK → users.id
        'performance',
        'approachability',
        'effectiveness',
        'strength',
        'improvement',
    ];

    protected $casts = [
        'performance'     => 'integer',
        'approachability' => 'integer',
        'effectiveness'   => 'integer',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FeedbackSubmission::class, 'feedback_submission_id');
    }

    /**
     * The rated staff member is a User.
     * Alias: ->staffUser to avoid confusion with a Staff model.
     */
    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'staff_id');
    }
}