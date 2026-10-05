<?php

namespace App\Services;

use App\Models\AbstractSubmission;
use App\Models\OralScore;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class OralScoringService
{
    /** Keys must match the oral_scores columns and the frontend CRITERIA list. */
    public const CRITERIA = [
        'presentation_of_results',
        'visual_aids',
        'clarity_organization',
        'presenter_performance',
        'impact',
        'response_to_questions',
    ];

    public const MAX_PER_CRITERION = 5;

    // ── Versions ─────────────────────────────────────────────────────────
    // Revisions share the original reference plus a suffix:
    //   ICW2026-0112, ICW2026-0112-R1, ICW2026-0112-R2 ...

    private function versionKey(AbstractSubmission $a): string
    {
        return strtoupper(preg_replace('/-R\d+$/i', '', trim((string) $a->reference)));
    }

    private function revisionNumber(AbstractSubmission $a): int
    {
        return preg_match('/-R(\d+)$/i', trim((string) $a->reference), $m) ? (int) $m[1] : 0;
    }

    /** Lower-cased, trimmed text, so "Oral" / "oral " / "ORAL" all compare equal. */
    private function norm(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    /** The reference as printed in the programme: no "-R1" suffix. */
    public function displayReference(AbstractSubmission $a): string
    {
        return preg_replace('/-R\d+$/i', '', trim((string) $a->reference));
    }

    /**
     * The presentations that get scored: ONE entry per abstract.
     *
     *  1. An abstract qualifies when its newest decided version (accepted or
     *     rejected) is accepted as an ORAL presentation.
     *  2. Of all its versions we list the LATEST one that has a score; if
     *     none has been scored, the latest version.
     *
     * (Run Classification first so abstracts are accepted.)
     */
    public function presentations(): Collection
    {
        return AbstractSubmission::query()
            ->with('authors')
            ->get()
            ->groupBy(fn (AbstractSubmission $a) => $this->versionKey($a))
            ->map(function (Collection $versions) {
                $newestFirst = $versions
                    ->sort(fn (AbstractSubmission $a, AbstractSubmission $b) =>
                        [$this->revisionNumber($b), $b->id] <=> [$this->revisionNumber($a), $a->id])
                    ->values();

                /** @var AbstractSubmission|null $decided */
                $decided = $newestFirst->first(
                    fn (AbstractSubmission $v) => in_array($this->norm($v->status), ['accepted', 'rejected'], true)
                );

                if (
                    ! $decided
                    || $this->norm($decided->status) !== 'accepted'
                    || $this->norm($decided->presentation_type) !== 'oral'
                ) {
                    return null;
                }

                return $newestFirst->first(fn (AbstractSubmission $v) => $v->average_score !== null)
                    ?? $newestFirst->first();
            })
            ->filter()
            ->sortBy(fn (AbstractSubmission $a) => $this->versionKey($a))
            ->values();
    }

    public function isPresentation(int $abstractId): bool
    {
        return $this->presentations()->contains('id', $abstractId);
    }

    public function presenterName(AbstractSubmission $abstract): ?string
    {
        $author = $abstract->authors->firstWhere('is_corresponding', true) ?? $abstract->authors->first();

        return $author?->name;
    }

    /** @return array{0:int,1:float} [total out of 30, percentage] */
    public function summarise(array $scores): array
    {
        $total = (int) array_sum(Arr::only($scores, self::CRITERIA));
        $max   = count(self::CRITERIA) * self::MAX_PER_CRITERION;

        return [$total, round($total / $max * 100, 2)];
    }

    public function formatScore(OralScore $score): array
    {
        return [
            'scores'     => Arr::only($score->toArray(), self::CRITERIA),
            'total'      => (int) $score->total,
            'percentage' => (float) $score->percentage,
            'comment'    => $score->comment,
            'updatedAt'  => optional($score->updated_at)->toIso8601String(),
        ];
    }
}