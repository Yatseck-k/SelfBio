<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Seo;
use App\Support\SiteName;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        SiteName::apply();

        // Each form of the account has its own bucket: a plain «throttle:5,1» is one counter for every route that uses it,
        // so requests for a new password would lock the sign-in of the same address.
        RateLimiter::for('account-login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('password-email', fn (Request $request) => [Limit::perMinute(5)->by($request->ip()), Limit::perHour(20)->by($request->ip())]);
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // The title, the description and the social tags of the two root views (the site and the game).
        View::composer(['app', 'whodoku'], fn ($view) => $view->with('seo', Seo::forRequest(request())));
    }
}
