<?php

declare(strict_types=1);

namespace Whodoku\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Whodoku\Application\Player\TelegramIdentityVerifier;
use Whodoku\Application\Player\TelegramWidgetVerifier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TelegramIdentityVerifier::class, fn () => new TelegramWidgetVerifier(
            (string) config('services.telegram.bot_token'),
            (int) config('services.telegram.max_age_seconds'),
        ));
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('puzzle-check', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('puzzle-hint', fn (Request $request) => Limit::perMinute(10)->by($request->user('player')->id ?? $request->ip()));
        RateLimiter::for('endless-next', fn (Request $request) => Limit::perMinute(20)->by($request->user('player')->id ?? $request->ip()));
        RateLimiter::for('shop-order', fn (Request $request) => Limit::perMinute(6)->by($request->user('player')->id ?? $request->ip()));
        RateLimiter::for('shop-status', fn (Request $request) => Limit::perMinute(40)->by($request->user('player')->id ?? $request->ip()));
        RateLimiter::for('player-login', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
    }
}
