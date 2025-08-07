<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Update weather data every 30 minutes
        $schedule->job(\App\Jobs\UpdateWeatherData::class)
            ->everyThirtyMinutes()
            ->name('update-weather-data')
            ->withoutOverlapping();

        // Send calendar reminders
        $schedule->call(function () {
            \App\Models\CalendarEvent::where('start_date', '>', now())
                ->where('start_date', '<=', now()->addMinutes(15))
                ->chunk(100, function ($events) {
                    foreach ($events as $event) {
                        \App\Jobs\SendCalendarReminder::dispatch($event, 15);
                    }
                });
        })->everyFiveMinutes()->name('calendar-reminders');

        // Generate weekly reports
        $schedule->call(function () {
            \App\Models\User::chunk(50, function ($users) {
                foreach ($users as $user) {
                    \App\Jobs\GenerateReports::dispatch($user, 'week');
                }
            });
        })->weekly()->sundays()->at('23:00')->name('weekly-reports');

        // Clean up old completed pomodoro sessions
        $schedule->call(function () {
            \App\Models\PomodoroSession::where('completed', true)
                ->where('completed_at', '<', now()->subMonths(3))
                ->delete();
        })->daily()->at('02:00')->name('cleanup-old-sessions');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}