<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'));

Route::get('/contacts', fn () => Inertia::render('ContactPage'));

Route::get('/blog', fn () => Inertia::render('BlogPage'));

Route::get('/blog/{slug}', fn ($slug) => Inertia::render('BlogPostPage', ['slug' => $slug]));

// User Authentication routes - moved to avoid conflict with MoonShine
Route::get('/enter', [AuthController::class, 'showLogin'])->name('login');
Route::post('/enter', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

// User Dashboard route - moved to avoid conflict with MoonShine  
Route::middleware(['auth'])->group(function (): void {
    Route::get('/home', fn () => Inertia::render('Dashboard/Index'))
        ->name('dashboard');
});