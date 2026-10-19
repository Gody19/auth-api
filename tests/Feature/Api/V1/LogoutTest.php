<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/auth/logout';

    public function test_user_can_logout_and_token_is_revoked(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->postJson(self::ENDPOINT, [], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully logged out.',
            ]);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_revoked_token_cannot_access_protected_endpoints_after_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->postJson(self::ENDPOINT, [], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);

        // Flush cached auth guards so the revoked token is re-evaluated.
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(401);

        $this->postJson(self::ENDPOINT, [], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(401);
    }

    public function test_logout_only_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('api')->plainTextToken;
        $otherToken = $user->createToken('api')->plainTextToken;

        $this->postJson(self::ENDPOINT, [], ['Authorization' => "Bearer {$currentToken}"])
            ->assertStatus(200);

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$otherToken}"])
            ->assertStatus(200);
    }

    public function test_unauthenticated_logout_returns_401(): void
    {
        $this->postJson(self::ENDPOINT)
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }
}
