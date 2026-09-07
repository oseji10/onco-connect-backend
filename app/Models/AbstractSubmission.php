<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AbstractSubmission extends Model
{
    use HasFactory;

    // Table is "abstracts" — the class can't be named Abstract because
    // `abstract` is a reserved PHP keyword.
    protected $table = 'abstracts';

    protected $fillable = [
        'reference',
        'title',
        'sub_theme',
        'presentation_type',
        'keywords',
        'body',
        'word_count',
        'status',
        'average_score',
        'overall_rank',
        'sub_theme_rank',
        'classification_group',
        'decision_notified_at',
        'submitted_at',
    ];

    protected $casts = [
        'average_score' => 'decimal:2',
        'submitted_at' => 'datetime',
        'decision_notified_at' => 'datetime',
    ];

    public function authors(): HasMany
    {
        return $this->hasMany(AbstractAuthor::class, 'abstract_id')->orderBy('order');
    }

    public function correspondingAuthor(): HasOne
    {
        return $this->hasOne(AbstractAuthor::class, 'abstract_id')
            ->where('is_corresponding', true);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ReviewAssignment::class, 'abstract_id');
    }

    /**
     * Recompute status + average_score from submitted reviews.
     * Call after any assignment/review change.
     */
    public function refreshScoring(): void
    {
        $this->loadMissing('assignments.review');

        $total = $this->assignments->count();
        $submitted = $this->assignments->where('status', 'submitted');

        if ($total === 0) {
            // no reviewers assigned yet — leave status as submitted
            return;
        }

        $this->average_score = $submitted->isNotEmpty()
            ? round($submitted->pluck('review.average')->avg(), 2)
            : null;

        if (in_array($this->status, ['accepted', 'rejected'], true)) {
            // Don't override a final committee decision.
            $this->save();
            return;
        }

        $this->status = $submitted->count() === $total
            ? 'scored'
            : 'under_review';

        $this->save();
    }

    // NOTE: the old determinePresentationType()/classifyAcceptedAbstracts()
    // helpers that used to live here have been removed — that logic now
    // lives entirely in App\Services\AbstractRankingService, which computes
    // top30 / sub-theme-top5 / poster / pending as one consistent pass
    // instead of re-deriving a rank per abstract independently. See
    // AbstractRankingService::classify() and ::apply().
}