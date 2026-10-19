<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/auth/me';

    public function test_authenticated_user_can_fetch_profile_with_valid_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson(self::ENDPOINT)
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Authenticated user retrieved successfully.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'email_verified_at', 'created_at'],
                ],
            ]);
    }

    public function test_profile_response_does_not_expose_sensitive_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('password', $response->json('data.user'));
        $this->assertArrayNotHasKey('remember_token', $response->json('data.user'));
    }

    public function test_me_returns_401_without_token(): void
    {
        $this->getJson(self::ENDPOINT)
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_me_returns_401_with_invalid_token(): void
    {
        $this->getJson(self::ENDPOINT, ['Authorization' => 'Bearer invalid-token-value'])
            ->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_me_returns_401_with_malformed_authorization_header(): void
    {
        $this->getJson(self::ENDPOINT, ['Authorization' => 'Token abc'])
            ->assertStatus(401);
    }

    public function test_me_returns_401_with_revoked_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->getJson(self::ENDPOINT, ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);

        $user->tokens()->delete();

        // Flush cached auth guards so the revoked token is re-evaluated.
        $this->app['auth']->forgetGuards();

        $this->getJson(self::ENDPOINT, ['Authorization' => "Bearer {$token}"])
            ->assertStatus(401);
    }
}
