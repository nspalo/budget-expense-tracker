<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const REGISTER_URI = '/api/auth/register';

    private function validRegistrationData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ], $overrides);
    }

    #[Test]
    public function successful_registration_returns_201_with_user_data_and_token(): void
    {
        $data = $this->validRegistrationData();

        $response = $this->postJson(self::REGISTER_URI, $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'created_at'],
                'token',
                'expires_at',
            ])
            ->assertJson([
                'user' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    #[Test]
    public function duplicate_email_returns_422(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $data = $this->validRegistrationData(['email' => 'taken@example.com']);

        $response = $this->postJson(self::REGISTER_URI, $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function password_too_short_returns_422(): void
    {
        $data = $this->validRegistrationData([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response = $this->postJson(self::REGISTER_URI, $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function password_too_long_returns_422(): void
    {
        $longPassword = str_repeat('a', 129);

        $data = $this->validRegistrationData([
            'password' => $longPassword,
            'password_confirmation' => $longPassword,
        ]);

        $response = $this->postJson(self::REGISTER_URI, $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function password_confirmation_mismatch_returns_422(): void
    {
        $data = $this->validRegistrationData([
            'password' => 'SecurePass123',
            'password_confirmation' => 'DifferentPass456',
        ]);

        $response = $this->postJson(self::REGISTER_URI, $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function missing_name_returns_422(): void
    {
        $data = $this->validRegistrationData();
        unset($data['name']);

        $response = $this->postJson(self::REGISTER_URI, $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function missing_email_returns_422(): void
    {
        $data = $this->validRegistrationData();
        unset($data['email']);

        $response = $this->postJson(self::REGISTER_URI, $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function invalid_email_format_returns_422(): void
    {
        $data = $this->validRegistrationData(['email' => 'not-an-email']);

        $response = $this->postJson(self::REGISTER_URI, $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function token_from_registration_can_authenticate_protected_requests(): void
    {
        $data = $this->validRegistrationData();

        $response = $this->postJson(self::REGISTER_URI, $data);

        $response->assertStatus(201);

        $token = $response->json('token');

        $authResponse = $this->getJson('/api/auth/user', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $authResponse->assertStatus(200)
            ->assertJson([
                'user' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ]);
    }
}
