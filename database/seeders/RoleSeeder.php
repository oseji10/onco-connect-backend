<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * The 5 roles used across the ICW admin dashboard + participant portal.
     *
     * - super_admin              → all menus, including "Add User"
     * - admin                    → all menus EXCEPT "Add User"
     * - reviewer                 → "Review Abstract" only
     * - registration_desk_officer→ "Registration" + "Accreditation" only
     * - participant              → not a dashboard role at all; created only
     *                               via the public registration flow
     *                               (AttendeeController::store). Kept here so
     *                               the FK in `users.role` always resolves.
     *
     * Uses updateOrCreate so re-running this seeder on an existing DB is safe
     * and won't duplicate rows or clobber roleId values already referenced
     * by existing users.
     */
    public function run(): void
    {
        $roles = [
            'super_admin',
            'admin',
            'reviewer',
            'registration_desk_officer',
            'participant',
            'abstract_committee_member',
        ];

        foreach ($roles as $roleName) {
            Role::updateOrCreate(['roleName' => $roleName]);
        }
    }
}