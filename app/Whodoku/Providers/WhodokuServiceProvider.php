<?php

declare(strict_types=1);

namespace Whodoku\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Whodoku\Console\Commands\BuildStory;
use Whodoku\Console\Commands\ClarifyWindowStatements;
use Whodoku\Console\Commands\RatePuzzles;
use Whodoku\Console\Commands\RegeneratePuzzles;
use Whodoku\Console\Commands\ReleaseCases;
use Whodoku\Console\Commands\ReviewPuzzles;
use Whodoku\Console\Commands\PrepareTestPlayer;
use Whodoku\Console\Commands\TelegramCheck;
use Whodoku\Console\Commands\TelegramPoll;
use Whodoku\Http\Middleware\UseWhodokuDatabase;

class WhodokuServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (config('database.default') === 'pgsql') {
            $this->loadMigrationsFrom(database_path('whodoku/migrations'));
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                BuildStory::class,
                RatePuzzles::class,
                ReleaseCases::class,
                ReviewPuzzles::class,
                PrepareTestPlayer::class,
                ClarifyWindowStatements::class,
                RegeneratePuzzles::class,
                TelegramCheck::class,
                TelegramPoll::class,
            ]);
        }

        Route::middleware([UseWhodokuDatabase::class, 'web'])
            ->group(base_path('routes/whodoku/web.php'));
        Route::middleware([UseWhodokuDatabase::class, 'api'])
            ->prefix('api')
            ->group(base_path('routes/whodoku/api.php'));
    }
}
