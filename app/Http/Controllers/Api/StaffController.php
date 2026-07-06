<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    /**
     * GET /api/staff
     *
     * Returns all ACTIVE users whose role links to a role-specific profile table
     * (cl, subcl, etc.) AND who are assigned to the currently active event.
     * "Active" is determined by users.status = 'active'.
     *
     * Assumed schema
     * ──────────────
     * users  : id, firstName, lastName, profilePhoto, role (FK→roles.roleId), status
     * roles  : roleId, name, slug, table_name
     * cl     : clId, userId, eventId, state, lga, ...
     * subcl  : subclId, userId, eventId, state, lga, ...
     * events : id/eventId, status, created_at
     */

    private const USER_FK_COLUMN = 'userId';
    private const ACTIVE_STATUS  = 'active';
    // private const INCLUDED_ROLE_SLUGS = ['cls', 'sub_cls'];

    public function index(): JsonResponse
    {
        // ── Get active event ───────────────────────────────────────────────
        $activeEvent = $this->getActiveEvent();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
                'data'    => []
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        // ── Get roles with profile tables ──────────────────────────────────
        $roles = DB::table('roles')
            ->whereNotNull('table_name')
            ->where('table_name', '!=', '')
            // ->whereIn('slug', self::INCLUDED_ROLE_SLUGS)
            ->get();

        if ($roles->isEmpty()) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $staff = collect();

        foreach ($roles as $role) {
            $tableName = $role->table_name;

            if (!$this->tableExists($tableName)) {
                continue;
            }

            // Check if the table has an eventId column
            $hasEventIdColumn = $this->columnExists($tableName, 'eventId');

            $query = DB::table("{$tableName} as rp")
                ->join('users as u', 'u.id', '=', 'rp.' . self::USER_FK_COLUMN)
                // ── Only active users ──────────────────────────────────────
                ->where('u.status', self::ACTIVE_STATUS);

            // ── Only filter by eventId if the column exists ────────────────
            if ($hasEventIdColumn) {
                $query->where('rp.eventId', $eventId);
            }

            $rows = $query->select(
                    'u.id',
                    DB::raw("TRIM(CONCAT(COALESCE(u.firstName,''), ' ', COALESCE(u.lastName,''))) as name"),
                    DB::raw("'{$role->roleName}' as role"),
                    // DB::raw("'{$role->slug}' as roleSlug"),
                    'u.photo as image',
                    'rp.state',
                    'rp.lga',
                )
                ->get();

            $staff = $staff->merge($rows);
        }

        $staff = $staff->sortBy('name')->values()->map(fn ($s) => [
            'id'       => $s->id,
            'name'     => $s->name,
            'role'     => $s->role,
            // 'roleSlug' => $s->roleSlug,
            'image'    => $s->image,
            'state'    => $s->state ?? null,
            'lga'      => $s->lga   ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $staff,
            'event'   => [
                'id'   => $eventId,
                'name' => $activeEvent->name ?? null,
            ]
        ]);
    }

    /**
     * Get the currently active event
     */
    private function getActiveEvent(): ?object
    {
        return DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Check if a database table exists
     */
    private function tableExists(string $table): bool
    {
        try {
            DB::select("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if a column exists in a table
     */
    private function columnExists(string $table, string $column): bool
    {
        try {
            $columns = DB::select("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
            return !empty($columns);
        } catch (\Throwable) {
            return false;
        }
    }
}