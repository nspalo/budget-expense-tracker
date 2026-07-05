<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_gets_profile_with_correct_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $expiresAt = Carbon::now()->addMinutes(10080);
        $token = $user->createToken('test-token', ['*'], $expiresAt);
        $plainTextToken = $token->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken,
        ])->getJson('/api/auth/user');

        $response->assertStatus(200)
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'expires_at']])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', 'John Doe')
            ->assertJsonPath('user.email', 'john@example.com');

        // Ensure password is never in the response
        $responseData = $response->json();
        $this->assertArrayNotHasKey('password', $responseData['user'] ?? $responseData);
    }

    #[Test]
    public function unauthenticated_request_gets_401(): void
    {
        $response = $this->getJson('/api/auth/user');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    #[Test]
    public function soft_deleted_user_gets_401(): void
    {
        $user = User::factory()->create();

        $expiresAt = Carbon::now()->addMinutes(10080);
        $token = $user->createToken('test-token', ['*'], $expiresAt);
        $plainTextToken = $token->plainTextToken;

        // Soft-delete the user
        $user->delete();

        // Request with token of soft-deleted user should return 401
        // Sanctum cannot resolve a trashed user (SoftDeletes excludes them),
        // so it returns 401 before the controller's trashed() check runs.
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken,
        ])->getJson('/api/auth/user');

        $response->assertStatus(401);
    }
}
