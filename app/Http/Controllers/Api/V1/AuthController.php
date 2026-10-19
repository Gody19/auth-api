<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const LOGIN_RATE_LIMIT = 6;

    private const LOGIN_RATE_WINDOW_SECONDS = 60;

    public function __construct(private readonly AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register($request->safe()->only(['name', 'email', 'password']));

        return $this->successResponse('Registration successful.', [
            'user' => new UserResource($result['user']),
            'access_token' => $result['access_token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at']->toIso8601String(),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $email = (string) $request->input('email');
        $password = (string) $request->input('password');
        $rateLimitKey = $this->loginRateLimitKey($email, $request->ip());

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::LOGIN_RATE_LIMIT)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return $this->errorResponse(
                "Too many login attempts. Please try again in {$seconds} seconds.",
                [],
                429
            );
        }

        $result = $this->auth->attemptLogin($email, $password);

        if ($result === null) {
            RateLimiter::hit($rateLimitKey, self::LOGIN_RATE_WINDOW_SECONDS);

            return $this->errorResponse('The supplied credentials are invalid.', [], 401);
        }

        RateLimiter::clear($rateLimitKey);

        return $this->successResponse('Login successful.', [
            'user' => new UserResource($result['user']),
            'access_token' => $result['access_token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at']->toIso8601String(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse('Authenticated user retrieved successfully.', [
            'user' => new UserResource($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request);

        return $this->successResponse('Successfully logged out.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->auth->sendPasswordResetLink((string) $request->input('email'));

        // Always return the same response so account existence is never revealed.
        return $this->successResponse(
            'If an account exists for the supplied email address, a password reset link has been sent.',
            [],
            202
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $credentials['password_confirmation'] = (string) $request->input('password_confirmation');

        $status = $this->auth->resetPassword($credentials);

        if ($status === Password::PASSWORD_RESET) {
            return $this->successResponse('Password has been reset successfully. Please log in with your new password.');
        }

        return $this->errorResponse($this->passwordResetErrorMessage($status), [
            'token' => [$this->passwordResetErrorMessage($status)],
        ], 422);
    }

    private function loginRateLimitKey(string $email, ?string $ip): string
    {
        return 'login:'.Str::lower($email).'|'.$ip;
    }

    private function passwordResetErrorMessage(string $status): string
    {
        return match ($status) {
            Password::INVALID_USER => 'The supplied credentials are invalid.',
            Password::INVALID_TOKEN => 'The password reset token is invalid or has expired.',
            Password::INVALID_PASSWORD => 'The supplied password is invalid.',
            default => 'The password reset request could not be processed.',
        };
    }
}
