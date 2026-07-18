<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Two endpoints that together implement "first login via OTP, then forced
 * password change":
 *
 *   1. loginWithOtp()   — public. Exchanges email + the emailed OTP for a
 *                          real JWT (same as a normal login), but the token
 *                          still carries mustChangePassword: true.
 *   2. changePassword() — authenticated. Sets a real password, flips
 *                          must_change_password off, and returns a FRESH
 *                          token so the frontend doesn't have to force a
 *                          full re-login to pick up the new claim.
 *
 * EnsurePasswordChanged (see Http/Middleware) is what actually blocks every
 * other endpoint until step 2 happens — this controller only handles the
 * two exempted routes themselves.
 */
class PasswordSetupController extends Controller
{
    public function loginWithOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !$user->otp || !$user->otp_expires_at) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired code.',
            ], 422);
        }

        if ($user->otp_expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'This code has expired. Ask an admin to resend your invite.',
            ], 422);
        }

        if (!Hash::check($validated['otp'], $user->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired code.',
            ], 422);
        }

        // OTP is single-use — clear it now regardless of what happens next.
        $user->forceFill(['otp' => null, 'otp_expires_at' => null])->save();

        $token = auth('api')->login($user);

        return response()->json([
            'success' => true,
            'message' => 'Logged in. Please set a new password to continue.',
            'token' => $token,
            'mustChangePassword' => $user->must_change_password,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $validated = $request->validate([
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'otp' => null,
            'otp_expires_at' => null,
        ])->save();

        // Re-issue the token so its mustChangePassword claim is up to date
        // immediately — otherwise the frontend guard would keep bouncing
        // the user back here until their old token expired.
        $token = auth('api')->login($user);

        return response()->json([
            'success' => true,
            'message' => 'Password updated.',
            'token' => $token,
        ]);
    }
}