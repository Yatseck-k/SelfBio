<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\PomodoroSession;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $userId = $request->user()->id;

        $cacheKey = "dashboard.stats.{$userId}";

        $stats = Cache::remember($cacheKey, 300, fn () =>
            // 5 минут TTL
            [
                'tasks' => [
                    'total' => Task::where('user_id', $userId)->count(),
                    'completed_today' => Task::where('user_id', $userId)
                        ->completed()
                        ->whereDate('updated_at', today())
                        ->count(),
                    'pending' => Task::where('user_id', $userId)->pending()->count(),
                    'overdue' => Task::where('user_id', $userId)->overdue()->count(),
                ],
                'pomodoro' => [
                    'today_sessions' => PomodoroSession::where('user_id', $userId)
                        ->today()
                        ->completed()
                        ->workSessions()
                        ->count(),
                    'today_minutes' => PomodoroSession::where('user_id', $userId)
                        ->today()
                        ->completed()
                        ->sum('duration'),
                    'week_sessions' => PomodoroSession::where('user_id', $userId)
                        ->thisWeek()
                        ->completed()
                        ->workSessions()
                        ->count(),
                ],
                'calendar' => [
                    'today_events' => CalendarEvent::where('user_id', $userId)
                        ->today()
                        ->count(),
                    'upcoming_week' => CalendarEvent::where('user_id', $userId)
                        ->upcoming(7)
                        ->count(),
                ],
            ]);

        return response()->json($stats);
    }

    public function overview(Request $request)
    {
        $userId = $request->user()->id;

        return response()->json([
            'upcoming_events' => CalendarEvent::where('user_id', $userId)
                ->upcoming(3)
                ->take(5)
                ->get(),
            'pending_tasks' => Task::where('user_id', $userId)
                ->pending()
                ->orderByRaw("CASE WHEN priority = 'high' THEN 1 WHEN priority = 'medium' THEN 2 ELSE 3 END")
                ->orderBy('due_date', 'asc')
                ->take(5)
                ->get(),
            'recent_pomodoros' => PomodoroSession::where('user_id', $userId)
                ->today()
                ->completed()
                ->orderBy('completed_at', 'desc')
                ->take(3)
                ->get(),
        ]);
    }
}
