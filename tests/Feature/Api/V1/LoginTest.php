<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/auth/login';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'developer@example.com',
            'password' => 'Password123',
        ]);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'email' => 'developer@example.com',
            'password' => 'Password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful.',
                'data' => [
                    'user' => [
                        'id' => $this->user->id,
                        'name' => $this->user->name,
                        'email' => 'developer@example.com',
                    ],
                    'token_type' => 'Bearer',
                ],
            ])
            ->assertJsonStructure([
                'data' => ['user', 'access_token', 'token_type', 'expires_at'],
            ]);

        $this->assertNotEmpty($response->json('data.access_token'));
    }

    public function test_issued_token_can_access_protected_endpoints(): void
    {
        $token = $this->postJson(self::ENDPOINT, [
            'email' => 'developer@example.com',
            'password' => 'Password123',
        ])->json('data.access_token');

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);
    }

    public function test_login_with_incorrect_password_returns_generic_error(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'email' => 'developer@example.com',
            'password' => 'WrongPassword123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'The supplied credentials are invalid.',
                'errors' => [],
            ]);
    }

    public function test_login_with_unknown_email_returns_same_generic_error(): void
    {
        $unknown = $this->postJson(self::ENDPOINT, [
            'email' => 'nobody@example.com',
            'password' => 'Password123',
        ]);
        $wrongPassword = $this->postJson(self::ENDPOINT, [
            'email' => 'developer@example.com',
            'password' => 'WrongPassword123',
        ]);

        $unknown->assertStatus(401);
        $wrongPassword->assertStatus(401);

        $this->assertSame(
            $wrongPassword->json('message'),
            $unknown->json('message'),
            'Login responses must not reveal whether an email address exists.'
        );
    }

    public function test_login_with_missing_credentials_fails(): void
    {
        $this->postJson(self::ENDPOINT, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_with_invalid_email_format_fails(): void
    {
        $this->postJson(self::ENDPOINT, [
            'email' => 'not-an-email',
            'password' => 'Password123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_login_is_rate_limited_per_email(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson(self::ENDPOINT, [
                'email' => 'developer@example.com',
                'password' => 'WrongPassword123',
            ])->assertStatus(401);
        }

        $this->postJson(self::ENDPOINT, [
            'email' => 'developer@example.com',
            'password' => 'WrongPassword123',
        ])->assertStatus(429);
    }

    public function test_rate_limiting_does_not_affect_other_accounts(): void
    {
        User::factory()->create(['email' => 'other@example.com', 'password' => 'Password123']);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson(self::ENDPOINT, [
                'email' => 'developer@example.com',
                'password' => 'WrongPassword123',
            ])->assertStatus(401);
        }

        $this->postJson(self::ENDPOINT, [
            'email' => 'other@example.com',
            'password' => 'Password123',
        ])->assertStatus(200);
    }

    public function test_successful_login_resets_rate_limiter(): void
    {
        $this->postJson(self::ENDPOINT, [
            'email' => 'developer@example.com',
            'password' => 'WrongPassword123',
        ])->assertStatus(401);

        $this->postJson(self::ENDPOINT, [
            'email' => 'developer@example.com',
            'password' => 'Password123',
        ])->assertStatus(200);

        $this->postJson(self::ENDPOINT, [
            'email' => 'developer@example.com',
            'password' => 'WrongPassword123',
        ])->assertStatus(401);
    }
}
