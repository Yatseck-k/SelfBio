<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PomodoroSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class PomodoroController extends Controller
{
    public function index(Request $request)
    {
        $sessions = PomodoroSession::where('user_id', $request->user()->id)
            ->when($request->has('today'), fn ($query) => $query->today())
            ->when($request->has('completed'), fn ($query) => $request->completed ? $query->completed() : $query->where('completed', false))
            ->orderBy('started_at', 'desc')
            ->take(50)
            ->get();

        return response()->json($sessions);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'duration' => 'integer|min:1|max:60',
            'type' => 'in:work,short_break,long_break',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $session = PomodoroSession::create([
            'duration' => $request->duration ?? 25,
            'type' => $request->type ?? 'work',
            'started_at' => now(),
            'user_id' => $request->user()->id,
        ]);

        // Сохраняем активную сессию в Redis
        $this->storeActiveSession($request->user()->id, $session);

        return response()->json($session, 201);
    }

    public function complete(Request $request, PomodoroSession $session)
    {
        if ($session->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $session->update([
            'completed' => true,
            'completed_at' => now(),
        ]);

        // Удаляем из Redis
        $this->removeActiveSession($request->user()->id);

        // Отправляем WebSocket событие
        broadcast(new \App\Events\PomodoroCompleted($session));

        return response()->json($session);
    }

    public function cancel(Request $request, PomodoroSession $session)
    {
        if ($session->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $session->delete();
        $this->removeActiveSession($request->user()->id);

        return response()->json(['message' => 'Session cancelled']);
    }

    public function active(Request $request)
    {
        $activeSession = $this->getActiveSession($request->user()->id);

        if (! $activeSession) {
            return response()->json(['active_session' => null]);
        }

        return response()->json(['active_session' => $activeSession]);
    }

    public function stats(Request $request)
    {
        $userId = $request->user()->id;

        $stats = [
            'today' => [
                'completed_sessions' => PomodoroSession::where('user_id', $userId)->today()->completed()->count(),
                'total_minutes' => PomodoroSession::where('user_id', $userId)->today()->completed()->sum('duration'),
                'work_sessions' => PomodoroSession::where('user_id', $userId)->today()->completed()->workSessions()->count(),
            ],
            'week' => [
                'completed_sessions' => PomodoroSession::where('user_id', $userId)->thisWeek()->completed()->count(),
                'total_minutes' => PomodoroSession::where('user_id', $userId)->thisWeek()->completed()->sum('duration'),
                'work_sessions' => PomodoroSession::where('user_id', $userId)->thisWeek()->completed()->workSessions()->count(),
            ],
            'streak' => $this->calculateStreak($userId),
        ];

        return response()->json($stats);
    }

    private function storeActiveSession($userId, $session)
    {
        Cache::put("pomodoro.active.{$userId}", [
            'id' => $session->id,
            'duration' => $session->duration,
            'type' => $session->type,
            'started_at' => $session->started_at->toISOString(),
            'expires_at' => $session->started_at->addMinutes($session->duration)->toISOString(),
        ], $session->duration * 60 + 300); // TTL с запасом
    }

    private function getActiveSession($userId)
    {
        return Cache::get("pomodoro.active.{$userId}");
    }

    private function removeActiveSession($userId)
    {
        Cache::forget("pomodoro.active.{$userId}");
    }

    private function calculateStreak($userId): int
    {
        $sessions = PomodoroSession::where('user_id', $userId)
            ->completed()
            ->workSessions()
            ->whereDate('started_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(started_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        $streak = 0;
        $currentDate = now()->startOfDay();

        foreach ($sessions as $session) {
            if ($currentDate->isSameDay($session->date)) {
                $streak++;
                $currentDate->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }
}
