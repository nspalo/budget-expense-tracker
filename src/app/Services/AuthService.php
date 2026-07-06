<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class AuthService
{
    /**
     * Pre-computed bcrypt dummy hash for timing attack prevention.
     * Used when a login attempt targets a non-existent email so that
     * the response time is indistinguishable from a real hash check.
     */
    private const DUMMY_HASH = '$2y$12$K4G/4v2LG0.LmLpEaT7MOeJBJdDWJlJzLlJdLlGmS5Lp5G4v2LG0a';

    /**
     * Register a new user and issue a Sanctum token.
     *
     * Wraps user creation and token generation in a database transaction
     * to ensure atomicity. Returns user profile data and plain-text token.
     *
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: array{id: int, name: string, email: string, created_at: string}, token: string, expires_at: string|null}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $expiresAt = $this->resolveTokenExpiration();

            $token = $user->createToken('auth-token', ['*'], $expiresAt);

            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at->toIso8601String(),
                ],
                'token' => $token->plainTextToken,
                'expires_at' => $expiresAt?->toIso8601String(),
            ];
        });
    }

    /**
     * Revoke the current access token for the authenticated user.
     *
     * Deletes only the token used in the current request,
     * leaving any other active tokens for the same user valid.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Authenticate a user by email and password, returning profile data and token.
     *
     * Performs a constant-time hash comparison even when the user is not found
     * to prevent timing-based user enumeration attacks.
     *
     * @throws AuthenticationException When credentials are invalid.
     * @return array{user: array{id: int, name: string, email: string, created_at: string}, token: string, expires_at: string|null}
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if ($user === null) {
            Hash::check($password, self::DUMMY_HASH);

            throw new AuthenticationException('Invalid credentials');
        }

        if (! Hash::check($password, $user->password)) {
            throw new AuthenticationException('Invalid credentials');
        }

        $expiresAt = $this->resolveTokenExpiration();

        $token = $user->createToken('auth-token', ['*'], $expiresAt);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at->toIso8601String(),
            ],
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt?->toIso8601String(),
        ];
    }

    /**
     * Resolve the token expiration timestamp from Sanctum config.
     *
     * Returns null if the configured TTL is null, zero, or negative,
     * indicating tokens should not expire.
     */
    private function resolveTokenExpiration(): ?Carbon
    {
        $ttlMinutes = config('sanctum.expiration');

        if ($ttlMinutes === null || $ttlMinutes <= 0) {
            return null;
        }

        return Carbon::now()->addMinutes((int) $ttlMinutes);
    }
}
