<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'password123';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => self::PASSWORD,
        ]);
    }

    #[Test]
    public function successful_login_returns_200_with_user_data_and_token(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => self::PASSWORD,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'created_at'],
                'token',
                'expires_at',
            ])
            ->assertJsonPath('user.id', $this->user->id)
            ->assertJsonPath('user.name', $this->user->name)
            ->assertJsonPath('user.email', $this->user->email);
    }

    #[Test]
    public function wrong_password_returns_401_with_generic_message(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertExactJson(['message' => 'Invalid credentials']);
    }

    #[Test]
    public function non_existent_email_returns_401_with_generic_message(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => self::PASSWORD,
        ]);

        $response->assertStatus(401)
            ->assertExactJson(['message' => 'Invalid credentials']);
    }

    #[Test]
    public function response_body_is_identical_for_wrong_password_and_non_existent_email(): void
    {
        $wrongPasswordResponse = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $nonExistentEmailResponse = $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => self::PASSWORD,
        ]);

        $this->assertSame(
            $wrongPasswordResponse->getContent(),
            $nonExistentEmailResponse->getContent(),
        );
        $this->assertSame(
            $wrongPasswordResponse->getStatusCode(),
            $nonExistentEmailResponse->getStatusCode(),
        );
    }

    #[Test]
    public function missing_email_field_returns_422(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'password' => self::PASSWORD,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function missing_password_field_returns_422(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function invalid_email_format_returns_422(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'not-an-email',
            'password' => self::PASSWORD,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function token_from_login_can_authenticate_protected_requests(): void
    {
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => self::PASSWORD,
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('token');

        $protectedResponse = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->getJson('/api/auth/user');

        $protectedResponse->assertStatus(200)
            ->assertJsonFragment(['id' => $this->user->id])
            ->assertJsonFragment(['email' => $this->user->email]);
    }
}
