<?php

namespace App\Services;

use App\Models\AbstractSubmission;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Works out which presentation certificates someone is entitled to
 * (in addition to their attendance certificate).
 *
 *   oral_presenter   - presenter of a scored oral presentation (see OralScoringService)
 *   poster_presenter - presenter of an accepted poster
 *
 * "Presenter" = the corresponding author. Set ALL_AUTHORS to true to give every
 * listed author a certificate instead. Authors are matched by user account or by
 * the email on the abstract, so this works for logged-in users and for the public
 * email + phone flow alike.
 */
class PresenterService
{
    public const ALL_AUTHORS = false;

    public function __construct(protected OralScoringService $oral) {}

    /** Logged-in user. @return array<int,string> any of 'oral_presenter', 'poster_presenter' */
    public function presentationTypes(User $user): array
    {
        return $this->typesFor((int) $user->id, (string) $user->email);
    }

    /** Public flow (no account). @return array<int,string> */
    public function presentationTypesForEmail(string $email): array
    {
        return $this->typesFor(null, $email);
    }

    // ── internals ────────────────────────────────────────────────────────

    private function typesFor(?int $userId, string $email): array
    {
        $types = [];

        if ($this->oral->presentations()->contains(fn (AbstractSubmission $a) => $this->isPresenter($a, $userId, $email))) {
            $types[] = 'oral_presenter';
        }

        if ($this->posterPresentations()->contains(fn (AbstractSubmission $a) => $this->isPresenter($a, $userId, $email))) {
            $types[] = 'poster_presenter';
        }

        return $types;
    }

    private function isPresenter(AbstractSubmission $abstract, ?int $userId, string $email): bool
    {
        $authors = self::ALL_AUTHORS
            ? $abstract->authors
            : collect([$abstract->authors->firstWhere('is_corresponding', true) ?? $abstract->authors->first()])->filter();

        $email = strtolower(trim($email));

        return $authors->contains(function ($author) use ($userId, $email) {
            if ($userId && $author->user_id && (int) $author->user_id === $userId) {
                return true;
            }

            return $email !== '' && strtolower(trim((string) $author->email)) === $email;
        });
    }

    /** One entry per abstract: accepted as a poster on its newest decided version. */
    private function posterPresentations(): Collection
    {
        return AbstractSubmission::query()
            ->with('authors')
            ->get()
            ->groupBy(fn (AbstractSubmission $a) => $this->versionKey($a))
            ->map(function (Collection $versions) {
                $decided = $versions
                    ->sort(fn (AbstractSubmission $a, AbstractSubmission $b) =>
                        [$this->revisionNumber($b), $b->id] <=> [$this->revisionNumber($a), $a->id])
                    ->first(fn (AbstractSubmission $v) => in_array($this->norm($v->status), ['accepted', 'rejected'], true));

                if (! $decided
                    || $this->norm($decided->status) !== 'accepted'
                    || $this->norm($decided->presentation_type) !== 'poster') {
                    return null;
                }

                return $decided;
            })
            ->filter()
            ->values();
    }

    private function norm(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    // Revisions: ICW2026-0112, ICW2026-0112-R1, ICW2026-0112-R2 ...
    private function versionKey(AbstractSubmission $a): string
    {
        return strtoupper(preg_replace('/-R\d+$/i', '', trim((string) $a->reference)));
    }

    private function revisionNumber(AbstractSubmission $a): int
    {
        return preg_match('/-R(\d+)$/i', trim((string) $a->reference), $m) ? (int) $m[1] : 0;
    }
}