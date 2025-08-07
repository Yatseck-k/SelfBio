<?php

use App\Http\Controllers\API\CalendarController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\PomodoroController;
use App\Http\Controllers\API\QuickLinkController;
use App\Http\Controllers\API\TaskController;
use App\Http\Controllers\API\WeatherController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());

Route::get('/contacts', [ContactController::class, 'show']);

Route::get('/blog', [BlogController::class, 'index']);
Route::get('/blog/{slug}', [BlogController::class, 'show']);

Route::middleware('throttle:60,1')->get('/blog', [BlogController::class, 'index']);

// Dashboard API routes (require authentication)
Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function (): void {
    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/overview', [DashboardController::class, 'overview']);

    // Weather
    Route::get('/weather/current', [WeatherController::class, 'current']);
    Route::get('/weather/forecast', [WeatherController::class, 'forecast']);

    // Tasks
    Route::apiResource('tasks', TaskController::class);
    Route::patch('/tasks/{task}/toggle', [TaskController::class, 'toggle']);
    Route::get('/tasks-stats', [TaskController::class, 'stats']);

    // Calendar Events
    Route::apiResource('calendar-events', CalendarController::class);
    Route::get('/calendar/upcoming', [CalendarController::class, 'upcoming']);

    // Pomodoro
    Route::apiResource('pomodoro', PomodoroController::class)->only(['index', 'store']);
    Route::patch('/pomodoro/{pomodoroSession}/complete', [PomodoroController::class, 'complete']);
    Route::delete('/pomodoro/{pomodoroSession}/cancel', [PomodoroController::class, 'cancel']);
    Route::get('/pomodoro/active', [PomodoroController::class, 'active']);
    Route::get('/pomodoro/stats', [PomodoroController::class, 'stats']);

    // Quick Links
    Route::apiResource('quick-links', QuickLinkController::class);
    Route::post('/quick-links/reorder', [QuickLinkController::class, 'reorder']);
    Route::get('/quick-links-categories', [QuickLinkController::class, 'categories']);
});
