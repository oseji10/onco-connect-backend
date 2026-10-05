<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OralScore extends Model
{
    protected $fillable = [
        'abstract_id',
        'panelist_id',
        'presentation_of_results',
        'visual_aids',
        'clarity_organization',
        'presenter_performance',
        'impact',
        'response_to_questions',
        'total',
        'percentage',
        'comment',
    ];

    protected $casts = [
        'percentage' => 'float',
    ];

    public function abstract(): BelongsTo
    {
        return $this->belongsTo(AbstractSubmission::class, 'abstract_id');
    }

    public function panelist(): BelongsTo
    {
        return $this->belongsTo(OralPanelist::class, 'panelist_id');
    }
}