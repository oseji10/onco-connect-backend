<?php

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
    /**
     * Create a new version of an abstract and create fresh
     * review assignments for the reviewers who reviewed
     * the previous version.
     */
    public function resubmit(
        AbstractSubmission $original,
        array $data,
        User $author,
    ): AbstractSubmission {
        return DB::transaction(function () use (
            $original,
            $data,
            $author
        ) {
            /*
             * We need the previous version's authors and
             * reviewer assignments before freezing it.
             */
            $original->loadMissing([
                'assignments',
                'authors',
            ]);

            /*
             * Preserve the reviewer relationship between
             * the old assignment and the new assignment.
             *
             * Example:
             *
             * old assignment #10
             * reviewer_id = 5
             *
             * becomes:
             *
             * new assignment #20
             * reviewer_id = 5
             * source_assignment_id = 10
             */
            $oldAssignments = $original->assignments
                ->map(function (ReviewAssignment $assignment) {
                    return [
                        'reviewer_id' => $assignment->reviewer_id,
                        'source_assignment_id' => $assignment->id,
                    ];
                })
                ->values()
                ->all();

            /*
             * 1. Freeze the previous version.
             */
            $original->forceFill([
                'is_current' => false,
            ])->save();

            /*
             * 2. Create the new version.
             */
            $wordCount = str_word_count(
                strip_tags($data['body'])
            );

            $new = AbstractSubmission::create([
                'parent_id' => $original->id,

                'version' => ((int) $original->version) + 1,

                'is_current' => true,

                'resubmission_note' =>
                    $data['resubmissionNote'],

                'resubmitted_at' => now(),

                'reference' =>
                    $this->nextReference(
                        $original->reference
                    ),

                'title' =>
                    $data['title'],

                'sub_theme' =>
                    $data['subTheme'],

                'presentation_type' =>
                    $data['presentationType'],

                'keywords' =>
                    $data['keywords'] ?? null,

                'body' =>
                    $data['body'],

                'word_count' =>
                    $wordCount,

                'status' =>
                    'submitted',

                'submitted_at' =>
                    now(),
            ]);

            /*
             * 3. Copy/reconcile authors.
             */
            $this->copyAuthors(
                $original,
                $new,
                $data['authors'] ?? null
            );

            /*
             * 4. Create a NEW review assignment for every
             * reviewer assigned to the previous version.
             *
             * IMPORTANT:
             *
             * We do NOT copy the old review.
             *
             * Version 2 gets a completely new Review record.
             *
             * source_assignment_id simply lets us find the
             * review from Version 1 for context.
             */
            foreach ($oldAssignments as $row) {
                ReviewAssignment::create([
                    'abstract_id' =>
                        $new->id,

                    'reviewer_id' =>
                        $row['reviewer_id'],

                    /*
                     * The reviewer has not started the new review.
                     */
                    'status' =>
                        'in_progress',

                    'assigned_at' =>
                        now(),

                    'is_resubmission_review' =>
                        true,

                    /*
                     * Links Version 2 assignment back to the
                     * review assignment from Version 1.
                     */
                    'source_assignment_id' =>
                        $row['source_assignment_id'],
                ]);
            }

            /*
             * 5. Notify the reviewers.
             */
            $this->notifyReviewers($new);

            return $new;
        });
    }

    /**
     * ICW2026-0042
     *      → ICW2026-0042-R1
     *      → ICW2026-0042-R2
     *      → ...
     */
    private function nextReference(
        string $originalReference
    ): string {
        /*
         * Remove an existing -R1, -R2, etc.
         */
        $base = preg_replace(
            '/-R\d+$/',
            '',
            $originalReference
        );

        /*
         * Find existing resubmission references.
         */
        $existingCount = AbstractSubmission::where(
            'reference',
            'like',
            $base . '-R%'
        )->count();

        return $base . '-R' . ($existingCount + 1);
    }

    /**
     * Copy authors.
     *
     * If no incoming author list is supplied,
     * duplicate the previous version's authors exactly.
     *
     * If a new author list is supplied,
     * reconcile it with the existing users.
     */
    private function copyAuthors(
        AbstractSubmission $original,
        AbstractSubmission $new,
        ?array $incoming,
    ): void {
        /*
         * No new author list supplied.
         *
         * Copy the previous authors exactly.
         */
        if ($incoming === null) {
            foreach ($original->authors as $author) {
                AbstractAuthor::create([
                    'abstract_id' =>
                        $new->id,

                    'user_id' =>
                        $author->user_id,

                    'name' =>
                        $author->name,

                    'affiliation' =>
                        $author->affiliation,

                    'email' =>
                        $author->email,

                    'phone' =>
                        $author->phone,

                    'is_corresponding' =>
                        $author->is_corresponding,

                    'order' =>
                        $author->order,

                    'invited_at' =>
                        $author->invited_at,

                    'activated_at' =>
                        $author->activated_at,
                ]);
            }

            return;
        }

        /*
         * New author list supplied.
         */
        foreach (
            array_values($incoming)
            as $index => $row
        ) {
            $userId = null;

            /*
             * Existing abstract author ID.
             */
            if (! empty($row['id'])) {
                $userId = $original->authors
                    ->firstWhere(
                        'id',
                        $row['id']
                    )
                    ?->user_id;
            }

            /*
             * Fall back to the user's email.
             */
            if (
                ! $userId &&
                ! empty($row['email'])
            ) {
                $userId = User::where(
                    'email',
                    $row['email']
                )->value('id');
            }

            AbstractAuthor::create([
                'abstract_id' =>
                    $new->id,

                'user_id' =>
                    $userId,

                'name' =>
                    $row['name'],

                'affiliation' =>
                    $row['affiliation'],

                'email' =>
                    $row['email'] ?? null,

                'phone' =>
                    $row['phone'] ?? null,

                'is_corresponding' =>
                    (bool) (
                        $row['isCorresponding']
                        ?? ($index === 0)
                    ),

                'order' =>
                    $index,
            ]);
        }
    }

    /**
     * Notify reviewers that a new version has been submitted.
     */
    private function notifyReviewers(
        AbstractSubmission $new
    ): void {
        $new->loadMissing(
            'assignments.reviewer'
        );

        foreach ($new->assignments as $assignment) {
            $reviewer =
                $assignment->reviewer;

            if (
                ! $reviewer ||
                ! $reviewer->email
            ) {
                continue;
            }

            Notification::route(
                'mail',
                $reviewer->email
            )->notify(
                new AbstractResubmittedNotification(
                    $new,
                    $reviewer->name ?? 'Reviewer',
                    $assignment
                )
            );
        }
    }
}
