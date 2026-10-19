<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const RESEND_ENDPOINT = '/api/v1/auth/resend-verification';

    private function verificationUrl(User $user, ?string $hash = null, int $expiresInMinutes = 60): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes($expiresInMinutes),
            [
                'id' => $user->id,
                'hash' => $hash ?? sha1($user->getEmailForVerification()),
            ]
        );
    }

    public function test_unverified_user_can_verify_email_via_signed_url(): void
    {
        $user = User::factory()->unverified()->create();

        $this->getJson($this->verificationUrl($user))
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Email address verified successfully.',
            ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_with_tampered_hash_fails(): void
    {
        $user = User::factory()->unverified()->create();

        $url = $this->verificationUrl($user, sha1('tampered@example.com'));

        $this->getJson($url)
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'The email verification link is invalid.',
            ]);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verification_with_unsigned_url_fails(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user);
        $unsignedUrl = preg_replace('/&signature=[^&]+/', '', $url);

        $this->getJson($unsignedUrl)->assertStatus(403);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verification_with_expired_signature_fails(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $this->getJson($url)->assertStatus(403);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verification_for_unknown_user_fails(): void
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => 999999, 'hash' => sha1('ghost@example.com')]
        );

        $this->getJson($url)->assertStatus(403);
    }

    public function test_already_verified_user_gets_success_message(): void
    {
        $user = User::factory()->create();

        $this->getJson($this->verificationUrl($user))
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Email address already verified.',
            ]);
    }

    public function test_unauthenticated_user_cannot_resend_verification(): void
    {
        $this->postJson(self::RESEND_ENDPOINT)
            ->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Unauthenticated.']);
    }

    public function test_unverified_user_can_resend_verification(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->postJson(self::RESEND_ENDPOINT)
            ->assertStatus(202)
            ->assertJson([
                'success' => true,
                'message' => 'A new verification link has been sent.',
            ]);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verified_user_cannot_resend_verification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(self::RESEND_ENDPOINT)
            ->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Email address already verified.',
            ]);

        Notification::assertNotSentTo($user, VerifyEmail::class);
    }

    public function test_resend_verification_is_rate_limited(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->postJson(self::RESEND_ENDPOINT)->assertStatus(202);
        }

        $this->actingAs($user)->postJson(self::RESEND_ENDPOINT)->assertStatus(429);
    }

    public function test_unverified_user_can_still_authenticate_by_default(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        $token = $response->json('data.access_token');
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);
    }
}
