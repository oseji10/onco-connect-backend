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
        'submitted_at',
    ];

    protected $casts = [
        'average_score' => 'decimal:2',
        'submitted_at' => 'datetime',
    ];

    public function authors(): HasMany
    {
        return $this->hasMany(AbstractAuthor::class, 'abstract_id')->orderBy('order');
    }

    // public function correspondingAuthor(): ?AbstractAuthor
    // {
    //     return $this->authors()->where('is_corresponding', true)->first()
    //         ?? $this->authors()->first();
    // }

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


    /**
     * Determine presentation type based on ranking
     */
    public function determinePresentationType(array $rankings): string
    {
        // Check if in overall top 30
        $overallRank = collect($rankings['overall'] ?? [])
            ->firstWhere('abstract.id', $this->id);
            
        if ($overallRank && $overallRank['rank'] <= 30) {
            return 'oral';
        }
        
        // Check if in sub-theme top 5
        $subThemeRank = collect($rankings['sub_themes'][$this->sub_theme] ?? [])
            ->firstWhere('abstract.id', $this->id);
            
        if ($subThemeRank && $subThemeRank['rank'] <= 5) {
            return 'oral';
        }
        
        return 'poster';
    }
    
    /**
     * Classify all accepted abstracts
     */
    public static function classifyAcceptedAbstracts(): array
    {
        $rankingService = app(AbstractRankingService::class);
        $rankings = [
            'overall' => $rankingService->getOverallRanking(),
            'sub_themes' => $rankingService->getSubThemeRanking(),
        ];
        
        $acceptedAbstracts = self::where('status', 'accepted')->get();
        $classified = [];
        
        foreach ($acceptedAbstracts as $abstract) {
            $presentationType = $abstract->determinePresentationType($rankings);
            $abstract->update(['presentation_type' => $presentationType]);
            $classified[] = [
                'abstract_id' => $abstract->id,
                'title' => $abstract->title,
                'presentation_type' => $presentationType,
                'score' => $abstract->average_score,
            ];
        }
        
        return $classified;
    }
}