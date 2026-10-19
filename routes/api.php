<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:6,1')
            ->name('auth.register');

        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1')
            ->name('auth.login');

        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:6,1')
            ->name('auth.forgot-password');

        Route::post('reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:6,1')
            ->name('auth.reset-password');

        // Named exactly "verification.verify" because Laravel's built-in
        // VerifyEmail notification generates signed URLs for this route name.
        Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('auth.me');

            Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');

            Route::post('resend-verification', [EmailVerificationController::class, 'resend'])
                ->middleware('throttle:6,1')
                ->name('auth.resend-verification');
        });
    });
});
