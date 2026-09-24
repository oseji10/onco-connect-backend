<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        return $this->respondWithToken($token);
    }

    public function me(): JsonResponse
    {
        // $user = Auth::guard('api')->user()?->load('facility');

        // return response()->json([
        //     'user' => $user,
        // ]);

        $user = auth()->user()->load('roles');

return response()->json([
    'user' => [
        'id' => $user->id,
        'firstName' => $user->firstName,
        'lastName' => $user->lastName,
        'otherNames' => $user->otherNames,
        'email' => $user->email,
        'phoneNumber' => $user->phoneNumber,
        'alternatePhoneNumber' => $user->alternatePhoneNumber,
        'email_verified_at' => $user->email_verified_at,
        'activated_at' => $user->activated_at,
        'roles' => $user->roles->pluck('roleName')->values()->all(),
        'facilityId' => $user->facilityId,
        'status' => $user->status,
        'must_change_password' => $user->must_change_password,
        'otp_expires_at' => $user->otp_expires_at,
        'created_at' => $user->created_at,
        'updated_at' => $user->updated_at,
        'photo' => $user->photo,
        'portfolio' => $user->portfolio,
        'facility' => $user->facility,
    ],
]);
    }

    public function refresh(): JsonResponse
    {
        $token = Auth::guard('api')->refresh();

        return $this->respondWithToken($token);
    }

    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    protected function respondWithToken(string $token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
            'user' => Auth::guard('api')->user()?->load('facility'),
        ]);
    }
}