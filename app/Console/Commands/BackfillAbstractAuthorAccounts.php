<?php

namespace App\Console\Commands;

use App\Models\AbstractAuthor;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AuthorAccountInvitationNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BackfillAbstractAuthorAccounts extends Command
{
    protected $signature = 'abstracts:backfill-author-accounts
                            {--dry-run : Report what would happen without writing}
                            {--send : Send activation emails to linked-but-not-activated users}
                            {--resend : Alias for --send}
                            {--only= : Limit to a specific author email}';

    protected $description = 'Create/link author accounts and migrate existing user roles into user_roles.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $send = (bool) $this->option('send') || (bool) $this->option('resend');
        $only = $this->option('only');

        /*
         * User::ROLE_AUTHOR is the role ID.
         *
         * Example:
         *
         * participant = 1
         * author      = 7
         */
        $authorRole = Role::where(
            'roleId',
            User::ROLE_AUTHOR
        )->first();

        if (! $authorRole) {
            $this->error(
                'The author role was not found in the roles table. '
                . 'Expected roleId: ' . User::ROLE_AUTHOR
            );

            return self::FAILURE;
        }

        $this->info(
            "Author role found: {$authorRole->roleName} "
            . "(roleId {$authorRole->roleId})"
        );

        /*
         * Find corresponding authors who have an email address.
         */
        $query = AbstractAuthor::query()
            ->where('is_corresponding', true)
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if ($only) {
            $query->where('email', $only);
        }

        $authors = $query->get();

        $this->info(
            "Found {$authors->count()} corresponding author(s) to process."
        );

        if ($dryRun) {
            $this->warn('DRY RUN — no database changes or emails will be sent.');
            $this->newLine();
        }

        $created = 0;
        $linked = 0;
        $existingRolesAdded = 0;
        $authorRolesAdded = 0;
        $rolesAlreadyPresent = 0;
        $emailed = 0;
        $failed = 0;

        foreach ($authors as $author) {
            $email = strtolower(trim($author->email));

            try {
                /*
                 * ---------------------------------------------------------
                 * FIND USER
                 * ---------------------------------------------------------
                 */
                $user = User::whereRaw(
                    'LOWER(email) = ?',
                    [$email]
                )->first();

                /*
                 * If no email match but abstract_author already has a
                 * user_id, try that.
                 */
                if (! $user && $author->user_id) {
                    $user = User::find($author->user_id);
                }

                /*
                 * ---------------------------------------------------------
                 * CREATE USER IF NEEDED
                 * ---------------------------------------------------------
                 */
                if (! $user) {
                    if ($dryRun) {
                        $this->line(
                            "  • [dry] {$email} — would create user"
                        );

                        $this->line(
                            "      → would add author role #{$authorRole->roleId}"
                        );

                        continue;
                    }

                    DB::transaction(function () use (
                        $author,
                        $email,
                        &$user,
                        &$created
                    ) {
                        $user = User::create([
                            'name' => $author->name
                                ?: $this->nameFromEmail($email),

                            'email' => $email,

                            'password' => Hash::make(
                                Str::random(48)
                            ),

                            /*
                             * Keep the old single-role column populated
                             * for newly created accounts.
                             */
                            'role' => User::ROLE_AUTHOR,

                            'email_verified_at' => now(),
                        ]);

                        $created++;

                        $this->line(
                            "  ✓ Created user #{$user->id} — {$email}"
                        );
                    });
                }

                /*
                 * ---------------------------------------------------------
                 * THE IMPORTANT PART
                 * ---------------------------------------------------------
                 *
                 * Your existing users table uses:
                 *
                 *     users.role
                 *
                 * as the old single-role field.
                 *
                 * Therefore we MUST read:
                 *
                 *     $user->role
                 *
                 * NOT:
                 *
                 *     $user->roleId
                 */
                $legacyRoleId = $user->role;

                /*
                 * ---------------------------------------------------------
                 * DRY RUN
                 * ---------------------------------------------------------
                 */
                if ($dryRun) {
                    $this->line(
                        "  • [dry] User #{$user->id} — {$email}"
                    );

                    /*
                     * 1. Existing role from users.role
                     */
                    if ($legacyRoleId !== null && $legacyRoleId !== '') {
                        $legacyRoleExists = DB::table('user_roles')
                            ->where('user_id', $user->id)
                            ->where('role_id', $legacyRoleId)
                            ->exists();

                        if ($legacyRoleExists) {
                            $this->line(
                                "      → existing role #{$legacyRoleId} already exists in user_roles"
                            );
                        } else {
                            $this->line(
                                "      → would add existing role #{$legacyRoleId} from users.role to user_roles"
                            );
                        }
                    } else {
                        $this->warn(
                            "      → User #{$user->id} has no legacy users.role value"
                        );
                    }

                    /*
                     * 2. Author role
                     */
                    $authorRoleExists = DB::table('user_roles')
                        ->where('user_id', $user->id)
                        ->where('role_id', $authorRole->roleId)
                        ->exists();

                    if ($authorRoleExists) {
                        $this->line(
                            "      → author role #{$authorRole->roleId} already exists"
                        );
                    } else {
                        $this->line(
                            "      → would add author role #{$authorRole->roleId}"
                        );
                    }

                    /*
                     * 3. Abstract author link
                     */
                    if ((int) $author->user_id !== (int) $user->id) {
                        $this->line(
                            "      → would link abstract author to user #{$user->id}"
                        );
                    }

                    continue;
                }

                /*
                 * ---------------------------------------------------------
                 * REAL DATABASE OPERATION
                 * ---------------------------------------------------------
                 */
                DB::transaction(function () use (
                    $author,
                    $user,
                    $legacyRoleId,
                    $authorRole,
                    &$linked,
                    &$existingRolesAdded,
                    &$authorRolesAdded,
                    &$rolesAlreadyPresent
                ) {
                    /*
                     * =====================================================
                     * 1. MIGRATE EXISTING users.role
                     * =====================================================
                     *
                     * Example:
                     *
                     * users:
                     *
                     * id = 270
                     * role = 1
                     *
                     * becomes:
                     *
                     * user_roles:
                     *
                     * 270 | 1
                     */
                    if (
                        $legacyRoleId !== null &&
                        $legacyRoleId !== ''
                    ) {
                        $legacyRoleExists = DB::table('user_roles')
                            ->where('user_id', $user->id)
                            ->where('role_id', $legacyRoleId)
                            ->exists();

                        if (! $legacyRoleExists) {
                            DB::table('user_roles')->insert([
                                'user_id' => $user->id,
                                'role_id' => $legacyRoleId,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            $existingRolesAdded++;

                            $this->line(
                                "      → Migrated users.role #{$legacyRoleId} to user_roles"
                            );
                        } else {
                            $rolesAlreadyPresent++;

                            $this->line(
                                "      → Existing role #{$legacyRoleId} already present"
                            );
                        }
                    } else {
                        $this->warn(
                            "      → User #{$user->id} has no users.role value"
                        );
                    }

                    /*
                     * =====================================================
                     * 2. ADD AUTHOR ROLE
                     * =====================================================
                     *
                     * Example:
                     *
                     * 270 | 1  ← participant
                     * 270 | 7  ← author
                     */
                    $authorRoleExists = DB::table('user_roles')
                        ->where('user_id', $user->id)
                        ->where('role_id', $authorRole->roleId)
                        ->exists();

                    if (! $authorRoleExists) {
                        DB::table('user_roles')->insert([
                            'user_id' => $user->id,
                            'role_id' => $authorRole->roleId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $authorRolesAdded++;

                        $this->line(
                            "      → Added author role #{$authorRole->roleId}"
                        );
                    } else {
                        $rolesAlreadyPresent++;

                        $this->line(
                            "      → Author role #{$authorRole->roleId} already present"
                        );
                    }

                    /*
                     * =====================================================
                     * 3. LINK ABSTRACT AUTHOR TO USER
                     * =====================================================
                     */
                    if ((int) $author->user_id !== (int) $user->id) {
                        $author->forceFill([
                            'user_id' => $user->id,
                            'invited_at' => $author->invited_at ?? now(),
                        ])->save();

                        $linked++;

                        $this->line(
                            "      → Linked abstract author to user #{$user->id}"
                        );
                    }
                });

                /*
                 * ---------------------------------------------------------
                 * SEND INVITATION
                 * ---------------------------------------------------------
                 */
                if (
                    $send &&
                    $user &&
                    $user->activated_at === null
                ) {
                    $user->notify(
                        new AuthorAccountInvitationNotification(
                            $author->abstract,
                            $author
                        )
                    );

                    $author->forceFill([
                        'invited_at' => now(),
                    ])->save();

                    $emailed++;

                    $this->line(
                        "      ✉ Activation email queued for {$email}"
                    );
                }

            } catch (\Throwable $e) {
                $this->error(
                    "  ✗ {$email} — {$e->getMessage()}"
                );

                $failed++;
            }
        }

        /*
         * ---------------------------------------------------------
         * SUMMARY
         * ---------------------------------------------------------
         */
        $this->newLine();

        $this->info('Backfill complete.');

        $this->table(
            ['Item', 'Count'],
            [
                ['Users created', $created],
                ['Abstract authors linked', $linked],
                [
                    'Existing users.role migrated',
                    $existingRolesAdded
                ],
                [
                    'Author roles added',
                    $authorRolesAdded
                ],
                [
                    'Roles already present',
                    $rolesAlreadyPresent
                ],
                [
                    'Activation emails queued',
                    $emailed
                ],
                [
                    'Failed',
                    $failed
                ],
            ]
        );

        if (! $send) {
            $this->warn(
                'No activation emails were sent. '
                . 'Run with --send when you are ready.'
            );
        }

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function nameFromEmail(string $email): string
    {
        $local = explode('@', $email)[0];

        return ucwords(
            str_replace(
                ['.', '_', '-'],
                ' ',
                $local
            )
        );
    }
}
