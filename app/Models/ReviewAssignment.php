<?php
// app/Models/ReviewAssignment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReviewAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'abstract_id',
        'reviewer_id',
        'status',
        'assigned_at',
        'is_resubmission_review',
        'source_assignment_id',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'is_resubmission_review' => 'boolean',
    ];

    public function abstract(): BelongsTo
    {
        return $this->belongsTo(AbstractSubmission::class, 'abstract_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Reviewer::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function sourceAssignment(): BelongsTo
    {
        return $this->belongsTo(ReviewAssignment::class, 'source_assignment_id');
    }
}