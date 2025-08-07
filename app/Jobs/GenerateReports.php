<?php

namespace App\Jobs;

use App\Models\PomodoroSession;
use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GenerateReports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $period = 'week' // week, month, year
    ) {}

    public function handle(): void
    {
        try {
            $report = $this->generateReport();

            // Кэшируем отчет на час
            $cacheKey = "report.{$this->user->id}.{$this->period}";
            Cache::put($cacheKey, $report, 3600);

            Log::info("Report generated for user {$this->user->id} for period {$this->period}");
        } catch (\Exception $e) {
            Log::error('Failed to generate report: '.$e->getMessage());
        }
    }

    private function generateReport(): array
    {
        $dateRange = $this->getDateRange();

        return [
            'period' => $this->period,
            'start_date' => $dateRange['start']->toDateString(),
            'end_date' => $dateRange['end']->toDateString(),
            'productivity' => $this->getProductivityStats($dateRange),
            'tasks' => $this->getTaskStats($dateRange),
            'calendar' => $this->getCalendarStats($dateRange),
            'generated_at' => now()->toISOString(),
        ];
    }

    private function getDateRange(): array
    {
        return match ($this->period) {
            'week' => [
                'start' => now()->startOfWeek(),
                'end' => now()->endOfWeek(),
            ],
            'month' => [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth(),
            ],
            'year' => [
                'start' => now()->startOfYear(),
                'end' => now()->endOfYear(),
            ],
            default => [
                'start' => now()->startOfWeek(),
                'end' => now()->endOfWeek(),
            ]
        };
    }

    private function getProductivityStats(array $dateRange): array
    {
        $sessions = PomodoroSession::where('user_id', $this->user->id)
            ->whereBetween('started_at', [$dateRange['start'], $dateRange['end']])
            ->completed()
            ->get();

        return [
            'total_sessions' => $sessions->count(),
            'work_sessions' => $sessions->where('type', 'work')->count(),
            'total_minutes' => $sessions->sum('duration'),
            'avg_daily_minutes' => $sessions->sum('duration') / max(1, $dateRange['start']->diffInDays($dateRange['end'])),
            'most_productive_day' => $this->getMostProductiveDay($sessions),
        ];
    }

    private function getTaskStats(array $dateRange): array
    {
        $tasks = Task::where('user_id', $this->user->id)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->get();

        $completedTasks = $tasks->where('completed', true);

        return [
            'total_created' => $tasks->count(),
            'total_completed' => $completedTasks->count(),
            'completion_rate' => $tasks->count() > 0 ? ($completedTasks->count() / $tasks->count()) * 100 : 0,
            'by_priority' => [
                'high' => $tasks->where('priority', 'high')->count(),
                'medium' => $tasks->where('priority', 'medium')->count(),
                'low' => $tasks->where('priority', 'low')->count(),
            ],
            'overdue' => Task::where('user_id', $this->user->id)->overdue()->count(),
        ];
    }

    private function getCalendarStats(array $dateRange): array
    {
        $events = $this->user->calendarEvents()
            ->whereBetween('start_date', [$dateRange['start'], $dateRange['end']])
            ->get();

        return [
            'total_events' => $events->count(),
            'avg_daily_events' => $events->count() / max(1, $dateRange['start']->diffInDays($dateRange['end'])),
            'busiest_day' => $this->getBusiestDay($events),
        ];
    }

    private function getMostProductiveDay($sessions): ?string
    {
        $dailyMinutes = $sessions->groupBy(fn ($session) => $session->started_at->format('Y-m-d'))->map(fn ($daySessions) => $daySessions->sum('duration'));

        if ($dailyMinutes->isEmpty()) {
            return null;
        }

        return $dailyMinutes->keys()->first();
    }

    private function getBusiestDay($events): ?string
    {
        $dailyEvents = $events->groupBy(fn ($event) => $event->start_date->format('Y-m-d'));

        if ($dailyEvents->isEmpty()) {
            return null;
        }

        return $dailyEvents->sortByDesc(fn ($dayEvents) => $dayEvents->count())->keys()->first();
    }
}
