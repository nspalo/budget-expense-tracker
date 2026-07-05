<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json($result, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                $request->validated('email'),
                $request->validated('password'),
            );

            return response()->json($result);
        } catch (AuthenticationException) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->authService->logout($user);

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->trashed()) {
            $user->currentAccessToken()->delete();

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'expires_at' => $user->currentAccessToken()->expires_at?->toIso8601String(),
            ],
        ]);
    }
}
