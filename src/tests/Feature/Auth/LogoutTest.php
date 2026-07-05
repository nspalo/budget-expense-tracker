<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LogoutTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_can_logout_and_token_is_revoked(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');
        $plainTextToken = $token->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken,
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);

        // Verify token is deleted from database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);

        // Reset auth guard cache so the next request re-resolves the token
        $this->app['auth']->forgetGuards();

        // Verify revoked token returns 401
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken,
        ])->getJson('/api/auth/user')
            ->assertStatus(401);
    }

    #[Test]
    public function logout_only_revokes_current_token_not_others(): void
    {
        $user = User::factory()->create();

        $token1 = $user->createToken('token-1');
        $plainTextToken1 = $token1->plainTextToken;

        $token2 = $user->createToken('token-2');
        $plainTextToken2 = $token2->plainTextToken;

        // Logout with token1
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken1,
        ])->postJson('/api/auth/logout')
            ->assertStatus(200);

        // Verify token1 is deleted but token2 remains
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token1->accessToken->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token2->accessToken->id,
        ]);

        // Reset auth guard cache
        $this->app['auth']->forgetGuards();

        // Token1 should be revoked
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken1,
        ])->getJson('/api/auth/user')
            ->assertStatus(401);

        // Reset auth guard cache again for fresh resolution
        $this->app['auth']->forgetGuards();

        // Token2 should still work
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken2,
        ])->getJson('/api/auth/user')
            ->assertStatus(200);
    }
}
