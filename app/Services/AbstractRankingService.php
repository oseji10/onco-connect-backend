<?php

namespace App\Services;

use App\Models\AbstractSubmission;
use App\Notifications\AbstractDecisionNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class AbstractRankingService
{
    public const TOP_OVERALL_COUNT = 30;
    public const TOP_SUB_THEME_COUNT = 5;
    public const POSTER_THRESHOLD = 2.5;

    /**
     * Abstracts eligible for automatic ranking: scored, and not already
     * manually rejected (a manual rejection is a final decision this
     * pass must not overturn).
     */
    private function eligiblePool(): Collection
    {
        return AbstractSubmission::query()
            ->with(['authors', 'assignments.reviewer', 'assignments.review'])
            ->whereNotNull('average_score')
            ->where('status', '!=', 'rejected')
            ->get()
            ->sort(function (AbstractSubmission $a, AbstractSubmission $b) {
                $cmp = (float) $b->average_score <=> (float) $a->average_score;
                if ($cmp !== 0) {
                    return $cmp;
                }
                // Tie-break: earlier submission ranks higher.
                return $a->submitted_at <=> $b->submitted_at;
            })
            ->values();
    }

    /**
     * Compute (without persisting) the four buckets:
     *   - top30: overall top 30 by score -> oral
     *   - sub_theme_top5: top 5 per sub-theme, from everyone outside the top 30 -> oral
     *   - posters: everyone left over with score >= 2.5 -> poster
     *   - pending: everyone left over with score < 2.5 -> held, no auto action
     */
    public function classify(): array
    {
        $sorted = $this->eligiblePool();

        $top30 = $sorted->take(self::TOP_OVERALL_COUNT)->values();
        $top30Ids = $top30->pluck('id')->all();

        $rest = $sorted
            ->reject(fn (AbstractSubmission $a) => in_array($a->id, $top30Ids, true))
            ->values();

        $subThemeTop5 = collect();
        foreach ($rest->groupBy('sub_theme') as $subTheme => $group) {
            foreach ($group->take(self::TOP_SUB_THEME_COUNT)->values() as $index => $abstract) {
                $subThemeTop5->push([
                    'abstract' => $abstract,
                    'sub_theme' => $subTheme,
                    'sub_theme_rank' => $index + 1,
                ]);
            }
        }
        $subThemeTop5Ids = $subThemeTop5->pluck('abstract.id')->all();

        $remaining = $rest
            ->reject(fn (AbstractSubmission $a) => in_array($a->id, $subThemeTop5Ids, true))
            ->values();

        $posters = $remaining
            ->filter(fn (AbstractSubmission $a) => (float) $a->average_score >= self::POSTER_THRESHOLD)
            ->values();

        $pending = $remaining
            ->filter(fn (AbstractSubmission $a) => (float) $a->average_score < self::POSTER_THRESHOLD)
            ->values();

        return [
            'top30' => $top30->map(fn ($abstract, $i) => ['abstract' => $abstract, 'rank' => $i + 1])->values(),
            'sub_theme_top5' => $subThemeTop5,
            'posters' => $posters,
            'pending' => $pending,
        ];
    }

    /**
     * Persist a classification computed by classify(): sets status,
     * presentation_type, rank fields and classification_group for every
     * abstract in top30 / sub_theme_top5 / posters (all become
     * "accepted"), and tags pending abstracts without deciding them.
     *
     * @return array{accepted:int, pending:int, notified:int, skipped:int}
     */
    public function apply(array $classification, bool $sendEmails = true, bool $resend = false): array
    {
        $result = ['accepted' => 0, 'pending' => 0, 'notified' => 0, 'skipped' => 0];

        foreach ($classification['top30'] as $row) {
            /** @var AbstractSubmission $abstract */
            $abstract = $row['abstract'];
            $abstract->forceFill([
                'status' => 'accepted',
                'presentation_type' => 'oral',
                'overall_rank' => $row['rank'],
                'sub_theme_rank' => null,
                'classification_group' => 'top30',
            ])->save();
            $result['accepted']++;

            $this->maybeNotify($abstract, 'accepted', 'oral', $row['rank'], null, $sendEmails, $resend, $result);
        }

        foreach ($classification['sub_theme_top5'] as $row) {
            /** @var AbstractSubmission $abstract */
            $abstract = $row['abstract'];
            $abstract->forceFill([
                'status' => 'accepted',
                'presentation_type' => 'oral',
                'overall_rank' => null,
                'sub_theme_rank' => $row['sub_theme_rank'],
                'classification_group' => 'sub_theme_top5',
            ])->save();
            $result['accepted']++;

            $this->maybeNotify($abstract, 'accepted', 'oral', null, $row['sub_theme_rank'], $sendEmails, $resend, $result);
        }

        foreach ($classification['posters'] as $abstract) {
            /** @var AbstractSubmission $abstract */
            $abstract->forceFill([
                'status' => 'accepted',
                'presentation_type' => 'poster',
                'overall_rank' => null,
                'sub_theme_rank' => null,
                'classification_group' => 'poster',
            ])->save();
            $result['accepted']++;

            $this->maybeNotify($abstract, 'accepted', 'poster', null, null, $sendEmails, $resend, $result);
        }

        foreach ($classification['pending'] as $abstract) {
            /** @var AbstractSubmission $abstract */
            $abstract->forceFill(['classification_group' => 'pending'])->save();
            $result['pending']++;
            // Deliberately no status change and no email — these are
            // held aside for a manual call via classify().
        }

        return $result;
    }

    private function maybeNotify(
        AbstractSubmission $abstract,
        string $status,
        ?string $presentationType,
        ?int $rank,
        ?int $subThemeRank,
        bool $sendEmails,
        bool $resend,
        array &$result
    ): void {
        if (! $sendEmails) {
            return;
        }

        if ($abstract->decision_notified_at && ! $resend) {
            $result['skipped']++;
            return;
        }

        $this->sendNotification($abstract, $status, $presentationType, $rank, $abstract->sub_theme, $subThemeRank);
        $result['notified']++;
    }

    /**
     * Send (or resend) a single decision email and stamp decision_notified_at.
     * Used both by the bulk pass above and by the "send individual
     * notification" action in the UI.
     */
    public function sendNotification(
        AbstractSubmission $abstract,
        string $status,
        ?string $presentationType,
        ?int $rank,
        ?string $subTheme,
        ?int $subThemeRank
    ): void {
        $author = $abstract->correspondingAuthor()->first() ?? $abstract->authors()->first();

        if (! $author || ! $author->email) {
            return;
        }

        // Notification::route('mail', ['address' => $author->email, 'name' => $author->name])->notify(
        //     new AbstractDecisionNotification(
        //         $abstract,
        //         $status,
        //         $presentationType,
        //         $rank,
        //         $subTheme,
        //         $subThemeRank,
        //         $author->name
        //     )
        // );

        Notification::route('mail', [$author->email => $author->name])->notify(
    new AbstractDecisionNotification(
        $abstract,
        $status,
        $presentationType,
        $rank,
        $subTheme,
        $subThemeRank,
        $author->name
    )
);
        $abstract->forceFill(['decision_notified_at' => now()])->save();
    }
}