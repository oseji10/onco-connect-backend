<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptReviewerInviteRequest;
use App\Http\Resources\ReviewerInvitePreviewResource;
use App\Http\Resources\ReviewerResource;
use App\Models\Reviewer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ReviewerInvitationController extends Controller
{
    // How long an invite link stays valid after it's sent.
    private const INVITE_LIFETIME_DAYS = 14;

    /**
     * GET /api/abstracts/reviewers/invite/{token}
     * Public. Lets the accept-invite page show "Hi {name}, confirm your
     * email is {email}" before asking for a password.
     */
    public function show(string $token): JsonResponse
    {
        $reviewer = $this->resolveByToken($token);

        return response()->json([
            'success' => true,
            'message' => 'Invite retrieved.',
            'data' => new ReviewerInvitePreviewResource($reviewer),
        ]);
    }

    /**
     * POST /api/abstracts/reviewers/invite/{token}/accept
     * Public — gated by the token. Sets a password (creating a User record
     * if the email doesn't already have one), links it to the Reviewer,
     * marks the invite used, and logs them straight in via a Sanctum token.
     */
    public function accept(AcceptReviewerInviteRequest $request, string $token): JsonResponse
    {
        $reviewer = $this->resolveByToken($token);

        $user = User::where('email', $reviewer->email)->first();

        if ($user) {
            // Email already has an account on this platform (e.g. they also
            // attend as a delegate) — just link it, don't touch their password.
            $reviewer->update(['user_id' => $user->id]);
        } else {
            $user = User::create([
                'name' => $reviewer->name,
                'email' => $reviewer->email,
                'password' => Hash::make($request->input('password')),
                'email_verified_at' => now(),
            ]);
            $reviewer->update(['user_id' => $user->id]);
        }

        // TODO: wire this up to whatever role/permission system the app
        // already uses elsewhere (e.g. a roles table, Spatie permissions).
        // $user->assignRole('reviewer');

        $reviewer->update([
            'status' => 'active',
            'activated_at' => now(),
            'invite_token' => null, // one-time use
        ]);

        $jwt = auth('api')->login($user);

        return response()->json([
            'success' => true,
            'message' => 'Invite accepted — you can now review assigned abstracts.',
            'data' => [
                'reviewer' => new ReviewerResource($reviewer),
                'token' => $jwt,
            ],
        ]);
    }

    private function resolveByToken(string $token): Reviewer
    {
        $reviewer = Reviewer::where('invite_token', $token)->first();

        if (! $reviewer) {
            throw ValidationException::withMessages([
                'token' => 'This invitation link is invalid or has already been used.',
            ]);
        }

        if ($reviewer->status === 'active') {
            throw ValidationException::withMessages([
                'token' => 'This invitation has already been accepted.',
            ]);
        }

        if (
            $reviewer->invited_at &&
            $reviewer->invited_at->addDays(self::INVITE_LIFETIME_DAYS)->isPast()
        ) {
            throw ValidationException::withMessages([
                'token' => 'This invitation has expired. Ask the Abstract Committee to resend it.',
            ]);
        }

        return $reviewer;
    }
}