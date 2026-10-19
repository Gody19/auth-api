<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/auth/register';

    private array $validPayload = [
        'name' => 'Test Developer',
        'email' => 'developer@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ];

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->validPayload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Registration successful.',
                'data' => [
                    'user' => [
                        'name' => 'Test Developer',
                        'email' => 'developer@example.com',
                    ],
                    'token_type' => 'Bearer',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'email_verified_at', 'created_at'],
                    'access_token',
                    'token_type',
                    'expires_at',
                ],
            ]);

        $this->assertDatabaseHas('users', ['email' => 'developer@example.com']);
    }

    public function test_registration_response_contains_usable_access_token(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->validPayload);
        $token = $response->json('data.access_token');

        $this->assertNotEmpty($token);

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['user' => ['email' => 'developer@example.com']],
            ]);
    }

    public function test_password_is_stored_hashed(): void
    {
        $this->postJson(self::ENDPOINT, $this->validPayload);

        $user = User::where('email', 'developer@example.com')->firstOrFail();

        $this->assertNotSame('Password123', $user->password);
        $this->assertTrue(Hash::check('Password123', $user->password));
    }

    public function test_registration_never_exposes_sensitive_fields(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->validPayload);

        $response->assertStatus(201);
        $response->assertJsonMissingPath('data.user.password');
        $response->assertJsonMissingPath('data.user.remember_token');
        $this->assertStringNotContainsString('password', $response->json('data.user')['id'] ?? '');
        $this->assertArrayNotHasKey('password', $response->json('data.user'));
        $this->assertArrayNotHasKey('remember_token', $response->json('data.user'));
    }

    public function test_registration_accepts_confirmed_as_password_confirmation_alias(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'name' => 'Test Developer',
            'email' => 'alias@example.com',
            'password' => 'Password123',
            'confirmed' => 'Password123',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'alias@example.com']);
    }

    public function test_registration_with_duplicate_email_fails(): void
    {
        User::factory()->create(['email' => 'developer@example.com']);

        $response = $this->postJson(self::ENDPOINT, $this->validPayload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonValidationErrors(['email']);

        $this->assertSame(1, User::where('email', 'developer@example.com')->count());
    }

    public function test_registration_with_missing_fields_fails(): void
    {
        $response = $this->postJson(self::ENDPOINT, []);

        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_with_invalid_email_fails(): void
    {
        $payload = array_merge($this->validPayload, ['email' => 'not-an-email']);

        $this->postJson(self::ENDPOINT, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_with_password_confirmation_mismatch_fails(): void
    {
        $payload = array_merge($this->validPayload, ['password_confirmation' => 'Different123']);

        $this->postJson(self::ENDPOINT, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_with_weak_password_fails(): void
    {
        $payload = array_merge($this->validPayload, [
            'password' => 'abcdefgh',
            'password_confirmation' => 'abcdefgh',
        ]);

        $this->postJson(self::ENDPOINT, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson(self::ENDPOINT, array_merge($this->validPayload, [
                'email' => "user{$i}@example.com",
            ]))->assertStatus(201);
        }

        $this->postJson(self::ENDPOINT, array_merge($this->validPayload, [
            'email' => 'rate-limited@example.com',
        ]))->assertStatus(429);
    }
}
