<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillUserRoles extends Command
{
    protected $signature = 'users:backfill-roles
                            {--dry-run : Report what would happen without writing}
                            {--only= : Limit to a specific user ID}';

    protected $description = 'Backfill the user_roles pivot table from the existing users.role column.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only = $this->option('only');

        $this->info('Starting user_roles backfill...');
        $this->newLine();

        /*
         * Build user query.
         */
        $query = User::query();

        if ($only !== null && $only !== '') {
            $query->where('id', $only);
        }

        $users = $query
            ->orderBy('id')
            ->get();

        $this->info("Found {$users->count()} user(s) to process.");

        if ($dryRun) {
            $this->warn('DRY RUN — no database changes will be made.');
            $this->newLine();
        }

        /*
         * Load all roles once.
         *
         * This avoids repeatedly querying the roles table.
         */
        $roles = Role::query()
            ->get()
            ->keyBy('roleName');

        $this->line('Available roles:');

        foreach ($roles as $role) {
            $this->line(
                "  → {$role->roleName} (roleId {$role->roleId})"
            );
        }

        $this->newLine();

        $added = 0;
        $alreadyPresent = 0;
        $noLegacyRole = 0;
        $unknownRole = 0;
        $failed = 0;

        foreach ($users as $user) {
            try {
                /*
                 * IMPORTANT:
                 *
                 * The existing application uses users.role as the
                 * legacy role column.
                 *
                 * Read it directly from the database rather than
                 * relying on a model accessor/cast.
                 */
                $legacyRole = DB::table('users')
                    ->where('id', $user->id)
                    ->value('role');

                /*
                 * User has no legacy role.
                 */
                if ($legacyRole === null || $legacyRole === '') {
                    $noLegacyRole++;

                    $this->line(
                        "  → User #{$user->id} has no users.role"
                    );

                    continue;
                }

                /*
                 * Convert the legacy role into a role ID.
                 *
                 * The users.role column may contain either:
                 *
                 *   participant
                 *   admin
                 *   reviewer
                 *   author
                 *
                 * or, depending on the existing schema, it may already
                 * contain a numeric role ID.
                 */
                $role = null;

                if (is_numeric($legacyRole)) {
                    $role = $roles->first(
                        fn ($item) => (string) $item->roleId === (string) $legacyRole
                    );
                } else {
                    $role = $roles->get($legacyRole);
                }

                /*
                 * If the role cannot be found, do not guess.
                 */
                if (! $role) {
                    $unknownRole++;

                    $this->warn(
                        "  ⚠ User #{$user->id}: unknown legacy role [{$legacyRole}]"
                    );

                    continue;
                }

                $roleId = (int) $role->roleId;

                /*
                 * Check whether this exact user/role combination
                 * already exists.
                 */
                $exists = DB::table('user_roles')
                    ->where('user_id', $user->id)
                    ->where('role_id', $roleId)
                    ->exists();

                if ($exists) {
                    $alreadyPresent++;

                    $this->line(
                        "  ✓ User #{$user->id} already has role {$role->roleName} (#{$roleId})"
                    );

                    continue;
                }

                /*
                 * DRY RUN:
                 * Report what would happen without inserting.
                 */
                if ($dryRun) {
                    $added++;

                    $this->line(
                        "  + [dry] User #{$user->id} → {$role->roleName} (#{$roleId})"
                    );

                    continue;
                }

                /*
                 * Insert only the missing role.
                 *
                 * We deliberately do NOT delete or sync existing
                 * user_roles rows.
                 */
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $added++;

                $this->line(
                    "  + User #{$user->id} → {$role->roleName} (#{$roleId})"
                );
            } catch (\Throwable $e) {
                $failed++;

                $this->error(
                    "  ✗ User #{$user->id} — {$e->getMessage()}"
                );
            }
        }

        $this->newLine();

        $this->info('Backfill complete.');

        $this->table(
            ['Item', 'Count'],
            [
                ['Users processed', $users->count()],
                ['Roles added', $added],
                ['Roles already present', $alreadyPresent],
                ['Users without legacy role', $noLegacyRole],
                ['Unknown legacy roles', $unknownRole],
                ['Failed', $failed],
            ]
        );

        if ($dryRun) {
            $this->newLine();

            $this->warn(
                'DRY RUN completed. No database changes were made.'
            );

            $this->line(
                'Run without --dry-run to perform the backfill.'
            );
        }

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
