<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFacilityScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->isSuperAdmin() || $user->partner()) {
            return $next($request);
        }

        $request->attributes->set('scoped_facility_id', $user->facilityId);

        return $next($request);
    }
}