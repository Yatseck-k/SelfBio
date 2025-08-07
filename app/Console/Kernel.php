<?php

namespace App\Console;

use App\Jobs\GenerateReports;
use App\Jobs\SendCalendarReminder;
use App\Jobs\UpdateWeatherData;
use App\Models\CalendarEvent;
use App\Models\PomodoroSession;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $this->scheduleWeatherUpdates($schedule);
        $this->scheduleCalendarReminders($schedule);
        $this->scheduleReports($schedule);
        $this->scheduleCleanupTasks($schedule);
    }

    /**
     * Schedule weather data updates.
     */
    private function scheduleWeatherUpdates(Schedule $schedule): void
    {
        $schedule->job(UpdateWeatherData::class)
            ->everyThirtyMinutes()
            ->name('update-weather-data')
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer();
    }

    /**
     * Schedule calendar event reminders.
     */
    private function scheduleCalendarReminders(Schedule $schedule): void
    {
        $schedule->call($this->processCalendarReminders(...))
            ->everyFiveMinutes()
            ->name('calendar-reminders')
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Schedule weekly report generation.
     */
    private function scheduleReports(Schedule $schedule): void
    {
        $schedule->call($this->generateWeeklyReports(...))
            ->weekly()
            ->sundays()
            ->at('23:00')
            ->name('weekly-reports')
            ->withoutOverlapping()
            ->onOneServer();
    }

    /**
     * Schedule cleanup tasks.
     */
    private function scheduleCleanupTasks(Schedule $schedule): void
    {
        $schedule->call($this->cleanupOldSessions(...))
            ->daily()
            ->at('02:00')
            ->name('cleanup-old-sessions')
            ->withoutOverlapping();
    }

    /**
     * Process calendar reminders for upcoming events.
     */
    public function processCalendarReminders(): void
    {
        $reminderWindow = now()->addMinutes(15);

        CalendarEvent::query()
            ->where('start_date', '>', now())
            ->where('start_date', '<=', $reminderWindow)
            ->whereDoesntHave('reminders', function ($query): void {
                $query->where('sent_at', '>', now()->subMinutes(20));
            })
            ->with('user')
            ->chunk(100, function ($events): void {
                foreach ($events as $event) {
                    SendCalendarReminder::dispatch($event, 15)
                        ->onQueue('notifications');
                }
            });
    }

    /**
     * Generate weekly reports for all users.
     */
    public function generateWeeklyReports(): void
    {
        User::query()
            ->active()
            ->chunk(50, function ($users): void {
                foreach ($users as $user) {
                    GenerateReports::dispatch($user, 'week')
                        ->onQueue('reports')
                        ->delay(now()->addSeconds(random_int(1, 300)));
                }
            });
    }

    /**
     * Clean up old completed pomodoro sessions.
     */
    public function cleanupOldSessions(): void
    {
        $deletedCount = PomodoroSession::query()
            ->where('completed', true)
            ->where('completed_at', '<', now()->subMonths(3))
            ->delete();

        if ($deletedCount > 0) {
            \Log::info("Cleaned up {$deletedCount} old pomodoro sessions");
        }
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
