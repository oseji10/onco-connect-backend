<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sits alongside 'auth:api' on any route group that should be locked out
 * until the user has set a real password. Exempts only the handful of
 * routes needed to actually get unstuck — everything else gets a 423 with
 * a machine-readable `code` the frontend can key off of.
 *
 * Usage: ->middleware(['auth:api', 'password.changed', 'role:super_admin'])
 *
 * Requires the exempted routes to be named (see routes/api.php):
 *   ->name('auth.me'), ->name('auth.change-password'), ->name('auth.logout')
 */
class EnsurePasswordChanged
{
    protected array $exemptRouteNames = [
        'auth.me',
        'auth.change-password',
        'auth.logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->must_change_password) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if ($routeName && in_array($routeName, $this->exemptRouteNames, true)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'You must change your password before continuing.',
            'code' => 'PASSWORD_CHANGE_REQUIRED',
        ], 423);
    }
}