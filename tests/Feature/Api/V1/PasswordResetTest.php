<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const FORGOT_ENDPOINT = '/api/v1/auth/forgot-password';

    private const RESET_ENDPOINT = '/api/v1/auth/reset-password';

    public function test_forgot_password_returns_generic_success_for_existing_account(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->postJson(self::FORGOT_ENDPOINT, ['email' => $user->email]);

        $response->assertStatus(202)
            ->assertJson([
                'success' => true,
                'message' => 'If an account exists for the supplied email address, a password reset link has been sent.',
            ]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_returns_same_response_for_unknown_email(): void
    {
        Notification::fake();

        $known = $this->postJson(self::FORGOT_ENDPOINT, ['email' => User::factory()->create()->email]);
        Notification::fake();
        $unknown = $this->postJson(self::FORGOT_ENDPOINT, ['email' => 'nobody@example.com']);

        $known->assertStatus(202);
        $unknown->assertStatus(202);
        $this->assertSame($known->json('message'), $unknown->json('message'));
    }

    public function test_forgot_password_with_invalid_email_fails(): void
    {
        $this->postJson(self::FORGOT_ENDPOINT, ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson(self::FORGOT_ENDPOINT, ['email' => 'someone@example.com'])
                ->assertStatus(202);
        }

        $this->postJson(self::FORGOT_ENDPOINT, ['email' => 'someone@example.com'])
            ->assertStatus(429);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $stolenToken = $user->createToken('api')->plainTextToken;

        $this->postJson(self::FORGOT_ENDPOINT, ['email' => $user->email])->assertStatus(202);

        $resetToken = Password::broker()->createToken($user);

        $this->postJson(self::RESET_ENDPOINT, [
            'token' => $resetToken,
            'email' => $user->email,
            'password' => 'BrandNew123',
            'password_confirmation' => 'BrandNew123',
        ])->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password has been reset successfully. Please log in with your new password.',
            ]);

        $user->refresh();

        $this->assertTrue(Hash::check('BrandNew123', $user->password));

        // All existing tokens are revoked on password reset.
        $this->assertSame(0, $user->tokens()->count());
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$stolenToken}"])
            ->assertStatus(401);

        // The old password no longer works; the new one does.
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'BrandNew123',
        ])->assertStatus(200);
    }

    public function test_reset_with_invalid_token_fails(): void
    {
        $user = User::factory()->create();

        $this->postJson(self::RESET_ENDPOINT, [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'BrandNew123',
            'password_confirmation' => 'BrandNew123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_reset_with_expired_token_fails(): void
    {
        $user = User::factory()->create();
        $resetToken = Password::broker()->createToken($user);

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subHours(2)]);

        $this->postJson(self::RESET_ENDPOINT, [
            'token' => $resetToken,
            'email' => $user->email,
            'password' => 'BrandNew123',
            'password_confirmation' => 'BrandNew123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_reset_with_unknown_email_fails(): void
    {
        $this->postJson(self::RESET_ENDPOINT, [
            'token' => 'some-token',
            'email' => 'nobody@example.com',
            'password' => 'BrandNew123',
            'password_confirmation' => 'BrandNew123',
        ])->assertStatus(422);
    }

    public function test_reset_with_weak_password_fails(): void
    {
        $user = User::factory()->create();
        $resetToken = Password::broker()->createToken($user);

        $this->postJson(self::RESET_ENDPOINT, [
            'token' => $resetToken,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_with_mismatched_confirmation_fails(): void
    {
        $user = User::factory()->create();
        $resetToken = Password::broker()->createToken($user);

        $this->postJson(self::RESET_ENDPOINT, [
            'token' => $resetToken,
            'email' => $user->email,
            'password' => 'BrandNew123',
            'password_confirmation' => 'Different123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_endpoint_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->postJson(self::RESET_ENDPOINT, [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => 'BrandNew123',
                'password_confirmation' => 'BrandNew123',
            ])->assertStatus(422);
        }

        $this->postJson(self::RESET_ENDPOINT, [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'BrandNew123',
            'password_confirmation' => 'BrandNew123',
        ])->assertStatus(429);
    }
}
