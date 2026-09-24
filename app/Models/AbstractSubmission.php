<?php
// app/Models/AbstractSubmission.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AbstractSubmission extends Model
{
    use HasFactory;

    protected $table = 'abstracts';

    protected $fillable = [
        'parent_id',
        'version',
        'is_current',
        'resubmission_note',
        'resubmitted_at',
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
        'resubmitted_at' => 'datetime',
        'decision_notified_at' => 'datetime',
        'is_current' => 'boolean',
        'version' => 'integer',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AbstractSubmission::class, 'parent_id');
    }

    public function resubmissions(): HasMany
    {
        return $this->hasMany(AbstractSubmission::class, 'parent_id')->orderBy('version');
    }

    /** The original abstract this version ultimately descends from */
    public function root(): AbstractSubmission
    {
        return $this->parent ? $this->parent->root() : $this;
    }

    public function isResubmission(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Return every version in the chain (oldest → newest), with reviews.
     */
    public function versionChain()
    {
        $rootId = $this->root()->id;

        return AbstractSubmission::where('id', $rootId)
            ->orWhere('parent_id', $rootId)
            ->orWhereIn('parent_id', function ($q) use ($rootId) {
                // catch grandchildren if chains ever grow past 2 levels
                $q->select('id')->from('abstracts')->where('parent_id', $rootId);
            })
            ->with(['authors', 'assignments.review', 'assignments.reviewer'])
            ->orderBy('version')
            ->get();
    }

    public function refreshScoring(): void
    {
        $this->loadMissing('assignments.review');

        $total = $this->assignments->count();
        $submitted = $this->assignments->where('status', 'submitted');

        if ($total === 0) {
            return;
        }

        $this->average_score = $submitted->isNotEmpty()
            ? round($submitted->pluck('review.average')->avg(), 2)
            : null;

        if (in_array($this->status, ['accepted', 'rejected'], true)) {
            $this->save();
            return;
        }

        $this->status = $submitted->count() === $total ? 'scored' : 'under_review';
        $this->save();
    }
}