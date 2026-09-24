<?php
// app/Http/Controllers/Api/AuthorActivationController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbstractAuthor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

    // use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
// use App\Models\User;


class AuthorActivationController extends Controller
{
    /**
     * GET /api/author/activate/{user}
     * The frontend calls this to display "Hi {name}, set your password".
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'name' => $user->name,
                'email' => $user->email,
                'already_active' => $user->activated_at !== null,
            ],
        ]);
    }

    /**
     * POST /api/author/activate/{user}
     * Sets the password, marks the user (and all their author rows) activated,
     * and returns an API token so the frontend can drop them straight in.
     */
    public function store(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'activated_at' => now(),
        ])->save();

        AbstractAuthor::where('user_id', $user->id)
            ->whereNull('activated_at')
            ->update(['activated_at' => now()]);

        $token = auth('api')->login($user);

        return response()->json([
            'success' => true,
            'message' => 'Account activated.',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $user->fresh(),
        ]);
    }




private function validateActivationLink(
    Request $request,
    int $userId
): void {
    $expires = $request->query('expires');
    $signature = $request->query('signature');

    if (!$expires || !$signature) {
        abort(403, 'Invalid activation link.');
    }

    /*
     * Reconstruct the exact URL that Laravel originally signed.
     */
    $signedUrl = URL::route(
        'author.activate',
        [
            'user' => $userId,
        ],
        true
    );

    $parsed = parse_url($signedUrl);

    $baseUrl =
        ($parsed['scheme'] ?? 'http') .
        '://' .
        ($parsed['host'] ?? 'localhost') .
        (isset($parsed['port']) ? ':' . $parsed['port'] : '') .
        ($parsed['path'] ?? '');

    /*
     * Laravel's signed URL hash is:
     *
     * HMAC-SHA256(
     *     URL + ?expires=...,
     *     APP_KEY
     * )
     *
     * The easiest and safest approach is to let Laravel
     * generate the URL again with the same expiration.
     */

    $expectedUrl = URL::temporarySignedRoute(
        'author.activate',
        \Carbon\Carbon::createFromTimestamp((int) $expires),
        [
            'user' => $userId,
        ]
    );

    $expectedSignature = parse_url(
        $expectedUrl,
        PHP_URL_QUERY
    );

    parse_str($expectedSignature ?? '', $expectedParams);

    if (
        !isset($expectedParams['signature']) ||
        !hash_equals(
            $expectedParams['signature'],
            $signature
        )
    ) {
        abort(403, 'Invalid signature.');
    }

    if ((int) $expires < now()->timestamp) {
        abort(403, 'This activation link has expired.');
    }
}
}