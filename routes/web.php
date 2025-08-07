<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'));

Route::get('/contacts', fn () => Inertia::render('ContactPage'));

Route::get('/blog', fn () => Inertia::render('BlogPage'));

Route::get('/blog/{slug}', fn ($slug) => Inertia::render('BlogPostPage', ['slug' => $slug]));

// Authentication routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

// Dashboard route (require authentication)
Route::middleware(['auth'])->group(function (): void {
    Route::get('/dashboard', fn () => Inertia::render('Dashboard/Index'))
        ->name('dashboard');
});
