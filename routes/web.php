<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\RepairCatalogController;
use App\Http\Controllers\RepairController;
use App\Services\ContactInfoService;
use App\Services\WelcomeService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn (ContactInfoService $contacts, WelcomeService $welcome) => Inertia::render('Welcome', [
    'contact' => $contacts->getContactInfo()?->toArray(),
    'welcome' => $welcome->getWelcomeInfo()?->toArray(),
]));
Route::get('/contacts', fn (ContactInfoService $contacts) => Inertia::render('ContactPage', [
    'contact' => $contacts->getContactInfo()?->toArray(),
]));
Route::get('/blog', fn () => Inertia::render('BlogPage'));
Route::get('/blog/{slug}', fn ($slug) => Inertia::render('BlogPostPage', ['slug' => $slug]));

Route::get('/game-admin/{path?}', fn (?string $path = null) => redirect('/admin'.($path ? '/'.$path : '')))
    ->where('path', '.*');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/repair', [RepairController::class, 'index'])->name('repair.index');
    Route::get('/Ремонт/{path?}', [RepairController::class, 'repairFile'])->where('path', '.*');
    Route::get('/archive/{path}', [RepairController::class, 'archiveFile'])->where('path', '.*');
    Route::get('/api/catalog', [RepairController::class, 'catalog']);
    Route::get('/api/undo-state', [RepairCatalogController::class, 'undoState']);
    Route::post('/api/status', [RepairCatalogController::class, 'status']);
    Route::post('/api/move', [RepairCatalogController::class, 'move']);
    Route::post('/api/undo', [RepairCatalogController::class, 'undo']);
    Route::post('/api/import', [RepairCatalogController::class, 'import']);
});
