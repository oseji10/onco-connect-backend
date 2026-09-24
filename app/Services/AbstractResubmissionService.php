<?php
// app/Services/AbstractResubmissionService.php

namespace App\Services;

use App\Models\AbstractAuthor;
use App\Models\AbstractSubmission;
use App\Models\ReviewAssignment;
use App\Models\User;
use App\Notifications\AbstractResubmittedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AbstractResubmissionService
{
    public function resubmit(
        AbstractSubmission $original,
        array $data,
        User $author,
    ): AbstractSubmission {
        return DB::transaction(function () use ($original, $data, $author) {
            $original->loadMissing('assignments', 'authors');

            $oldAssignments = $original->assignments->map(fn ($a) => [
                'reviewer_id' => $a->reviewer_id,
                'source_assignment_id' => $a->id,
            ])->all();

            // 1. Freeze the previous version
            $original->forceFill(['is_current' => false])->save();

            // 2. Create the new version
            $wordCount = str_word_count(strip_tags($data['body']));

            $new = AbstractSubmission::create([
                'parent_id' => $original->id,
                'version' => $original->version + 1,
                'is_current' => true,
                'resubmission_note' => $data['resubmissionNote'],
                'resubmitted_at' => now(),
                'reference' => $this->nextReference($original->reference),
                'title' => $data['title'],
                'sub_theme' => $data['subTheme'],
                'presentation_type' => $data['presentationType'],
                'keywords' => $data['keywords'] ?? null,
                'body' => $data['body'],
                'word_count' => $wordCount,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            // 3. Copy / reconcile authors (preserve user_id links)
            $this->copyAuthors($original, $new, $data['authors'] ?? null);

            // 4. Clone reviewer assignments as "documentation only" reviews
            foreach ($oldAssignments as $row) {
                ReviewAssignment::create([
                    'abstract_id' => $new->id,
                    'reviewer_id' => $row['reviewer_id'],
                    'status' => 'in_progress',
                    'assigned_at' => now(),
                    'is_resubmission_review' => true,
                    'source_assignment_id' => $row['source_assignment_id'],
                ]);
            }

            // 5. Notify reviewers
            $this->notifyReviewers($new);

            return $new;
        });
    }

    /**
     * ICW2026-0042 → ICW2026-0042-R1, -R2, …
     */
    private function nextReference(string $originalReference): string
    {
        $base = preg_replace('/-R\d+$/', '', $originalReference);

        $existingCount = AbstractSubmission::where('reference', 'like', $base . '-R%')->count();

        return $base . '-R' . ($existingCount + 1);
    }

    /**
     * Copy authors. If a new author list is provided, reconcile it;
     * otherwise duplicate exactly.
     */
    private function copyAuthors(
        AbstractSubmission $original,
        AbstractSubmission $new,
        ?array $incoming,
    ): void {
        if ($incoming === null) {
            foreach ($original->authors as $author) {
                AbstractAuthor::create([
                    'abstract_id' => $new->id,
                    'user_id' => $author->user_id,
                    'name' => $author->name,
                    'affiliation' => $author->affiliation,
                    'email' => $author->email,
                    'phone' => $author->phone,
                    'is_corresponding' => $author->is_corresponding,
                    'order' => $author->order,
                    'invited_at' => $author->invited_at,
                    'activated_at' => $author->activated_at,
                ]);
            }
            return;
        }

        foreach (array_values($incoming) as $index => $row) {
            $userId = null;

            if (! empty($row['id'])) {
                $userId = $original->authors->firstWhere('id', $row['id'])?->user_id;
            }

            if (! $userId && ! empty($row['email'])) {
                $userId = User::where('email', $row['email'])->value('id');
            }

            AbstractAuthor::create([
                'abstract_id' => $new->id,
                'user_id' => $userId,
                'name' => $row['name'],
                'affiliation' => $row['affiliation'],
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null,
                'is_corresponding' => (bool) ($row['isCorresponding'] ?? ($index === 0)),
                'order' => $index,
            ]);
        }
    }

    private function notifyReviewers(AbstractSubmission $new): void
    {
        $new->loadMissing('assignments.reviewer');

        foreach ($new->assignments as $assignment) {
            $reviewer = $assignment->reviewer;
            if (! $reviewer || ! $reviewer->email) {
                continue;
            }

            Notification::route('mail', $reviewer->email)
                ->notify(new AbstractResubmittedNotification(
                    $new,
                    $reviewer->name ?? 'Reviewer',
                    $assignment,
                ));
        }
    }
}