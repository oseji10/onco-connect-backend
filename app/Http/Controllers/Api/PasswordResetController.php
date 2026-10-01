<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

/**
 * Forgot-password via a 6-digit email code (same style as the invitation OTP).
 *
 *   POST /auth/forgot-password   { email }
 *   POST /auth/reset-password    { email, otp, password, password_confirmation }
 *
 * The code is stored hashed in the cache (no migration needed, and it never
 * touches the users.otp column used for new-account invitations). Requires a
 * persistent cache driver (file, database, redis) — not "array".
 */
class PasswordResetController extends Controller
{
    private const OTP_TTL_MINUTES = 15;
    private const MAX_ATTEMPTS    = 5;

    /**
     * Always answers with the same message so the endpoint can't be used to
     * discover which emails have accounts.
     */
    public function forgot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = $this->normalise($validated['email']);
        $user  = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user && $user->status !== 'inactive') {
            $otp = (string) random_int(100000, 999999);

            Cache::put($this->cacheKey($email), [
                'hash'       => Hash::make($otp),
                'attempts'   => 0,
                'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES)->timestamp,
            ], now()->addMinutes(self::OTP_TTL_MINUTES));

            try {
                Mail::to($user->email)->send(
                    new PasswordResetOtpMail($user, $otp, self::OTP_TTL_MINUTES)
                );
            } catch (\Throwable $e) {
                report($e);
                Cache::forget($this->cacheKey($email));
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'If an account exists for that email, a 6-digit reset code has been sent.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => ['required', 'email', 'max:255'],
            'otp'      => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $email = $this->normalise($validated['email']);
        $key   = $this->cacheKey($email);
        $data  = Cache::get($key);

        if (!$data) {
            return $this->invalid();
        }

        if (($data['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return response()->json([
                'success' => false,
                'message' => 'Too many incorrect attempts. Please request a new code.',
            ], 422);
        }

        if (!Hash::check($validated['otp'], $data['hash'])) {
            $data['attempts'] = ($data['attempts'] ?? 0) + 1;
            Cache::put($key, $data, Carbon::createFromTimestamp($data['expires_at']));

            return $this->invalid();
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (!$user || $user->status === 'inactive') {
            Cache::forget($key);

            return $this->invalid();
        }

        $user->update([
            'password'             => Hash::make($validated['password']),
            'must_change_password' => false,
            // They now have a real password, so any old invitation code is void.
            'otp'                  => null,
            'otp_expires_at'       => null,
        ]);

        Cache::forget($key);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. You can now sign in.',
        ]);
    }

    private function invalid(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'The code is invalid or has expired.',
        ], 422);
    }

    private function normalise(string $email): string
    {
        return strtolower(trim($email));
    }

    private function cacheKey(string $email): string
    {
        return 'password-reset:' . sha1($email);
    }
}