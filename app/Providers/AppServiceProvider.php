<?php

namespace App\Providers;

use App\Http\Middleware\VerifySiteKey;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Catch lazy loading, silently discarded attributes and typos while developing.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Password rules (same as the admin panel; production also rejects leaked passwords).
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()->uncompromised()
            : Password::min(8)->letters()->numbers());

        // Reset emails link to the Next.js page, not to this API.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => config('admin.frontend_url')
            .'/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset()));

        $this->configureRateLimits();
    }

    private function configureRateLimits(): void
    {
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        // Views are sent by the Next.js server: limit per visitor (X-Visitor-Ip, trusted only with the site key).
        RateLimiter::for('views', fn (Request $request) => Limit::perMinute(20)->by('ip|'.VerifySiteKey::visitorIp($request)));
        RateLimiter::for('admin', fn (Request $request) => Limit::perMinute(180)->by($request->user()?->id ?: $request->ip()));

        // Brute-force protection: per email + IP, and per IP overall.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);
        // Contact form: per visitor (IP from VerifySiteKey), per sender email, and overall.
        RateLimiter::for('contact', fn (Request $request) => [
            Limit::perMinutes(10, 5)->by('ip|'.VerifySiteKey::visitorIp($request)),
            Limit::perHour(5)->by('email|'.mb_strtolower((string) $request->input('email'))),
            Limit::perHour(300)->by('contact-all'),
        ]);
        // Replies are real emails: cap them so a hijacked session can't send spam.
        RateLimiter::for('inquiry-reply', fn (Request $request) => Limit::perHour(60)->by((string) $request->user()?->id));

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(3)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perHour(20)->by($request->ip()),
        ]);
    }
}
