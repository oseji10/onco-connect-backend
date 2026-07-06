<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackSubmission extends Model
{
    protected $fillable = [
        'attendeeId',
        'overall_rating',
        'organization',
        'communication',
        'respected',
        'ip_address',
        'eventId'
    ];

    protected $casts = [
        'overall_rating' => 'integer',
        'organization'   => 'integer',
        'communication'  => 'integer',
    ];

    public function staffRatings(): HasMany
    {
        return $this->hasMany(FeedbackStaffRating::class, 'feedback_submission_id');
    }
}