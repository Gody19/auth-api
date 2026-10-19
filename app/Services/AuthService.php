<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class AuthService
{
    /**
     * Register a new user and issue a personal access token.
     *
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, access_token: string, expires_at: Carbon}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            return $this->issueToken($user);
        });
    }

    /**
     * Attempt to authenticate the user and issue a personal access token.
     *
     * @return array{user: User, access_token: string, expires_at: Carbon}|null
     */
    public function attemptLogin(string $email, string $password): ?array
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        return $this->issueToken($user);
    }

    /**
     * Revoke the access token used for the current request.
     */
    public function logout(Request $request): void
    {
        $request->user()?->currentAccessToken()?->delete();
    }

    /**
     * Send a password reset link to the given email address.
     *
     * The result is intentionally ignored by callers so that the API never
     * reveals whether an account exists for a given email address.
     */
    public function sendPasswordResetLink(string $email): void
    {
        Password::broker()->sendResetLink(['email' => $email]);
    }

    /**
     * Reset the user's password for a valid broker token.
     *
     * All personal access tokens are revoked on success so that a stolen
     * token cannot survive a password reset.
     *
     * @param  array{token: string, email: string, password: string, password_confirmation: string}  $credentials
     */
    public function resetPassword(array $credentials): string
    {
        return Password::broker()->reset(
            [
                'token' => $credentials['token'],
                'email' => $credentials['email'],
                'password' => $credentials['password'],
                'password_confirmation' => $credentials['password_confirmation'],
            ],
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );
    }

    /**
     * @return array{user: User, access_token: string, expires_at: Carbon}
     */
    private function issueToken(User $user): array
    {
        $plainTextToken = $user->createToken('api')->plainTextToken;

        return [
            'user' => $user,
            'access_token' => $plainTextToken,
            'expires_at' => now()->addMinutes((int) config('sanctum.expiration', 43200)),
        ];
    }
}
