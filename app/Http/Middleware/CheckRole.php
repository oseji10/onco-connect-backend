<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage in routes:
 *   Route::middleware(['auth:api', 'role:super_admin'])->group(...);
 *   Route::middleware(['auth:api', 'role:super_admin,admin'])->group(...);
 *
 * This is the REAL security boundary. The frontend menu-hiding
 * (see lib/permissions.ts) only improves UX — it never replaces this.
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $roleName = $user->user_role?->roleName;

        if (!$roleName || !in_array($roleName, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}