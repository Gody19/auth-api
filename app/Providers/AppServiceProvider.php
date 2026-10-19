<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // This API is consumed by external clients, so password-reset emails
        // must link to a configurable URL instead of a Laravel "password.reset"
        // web route (which does not exist in this application).
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $baseUrl = rtrim((string) config('services.authapi.password_reset_url'), '/');

            $url = $baseUrl.'?'.http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);

            $expireMinutes = (int) config(
                'auth.passwords.'.config('auth.defaults.passwords').'.expire',
                60
            );

            return (new MailMessage)
                ->subject('Reset Password Notification')
                ->line('You are receiving this email because we received a password reset request for your account.')
                ->action('Reset Password', $url)
                ->line('This password reset link will expire in '.$expireMinutes.' minutes.')
                ->line('If you did not request a password reset, no further action is required.');
        });
    }
}
