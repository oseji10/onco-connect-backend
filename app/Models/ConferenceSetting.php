<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConferenceSetting extends Model
{
    protected $fillable = [
        'abstract_submission_deadline',
        'abstract_review_deadline',
    ];

    protected $casts = [
        'abstract_submission_deadline' => 'datetime',
        'abstract_review_deadline' => 'datetime',
    ];

    /**
     * There is only ever one row. Fetch it, creating an empty
     * (no deadlines set = never closed) row on first use.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function submissionsClosed(): bool
    {
        return $this->abstract_submission_deadline !== null
            && now()->greaterThan($this->abstract_submission_deadline);
    }

    public function reviewsClosed(): bool
    {
        return $this->abstract_review_deadline !== null
            && now()->greaterThan($this->abstract_review_deadline);
    }
}