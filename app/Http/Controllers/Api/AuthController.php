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
        $user = Auth::guard('api')->user()?->load('facility');

        return response()->json([
            'user' => $user,
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