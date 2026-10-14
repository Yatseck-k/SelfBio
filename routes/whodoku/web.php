<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Whodoku\Http\Controllers\Player\DetectiveController;
use Whodoku\Http\Controllers\Player\FakePaymentController;
use Whodoku\Http\Controllers\Player\PlayerAuthController;
use Whodoku\Http\Controllers\Player\PlayerSkillController;
use Whodoku\Http\Controllers\Player\PlayerStatsController;

Route::get('/game', fn () => Inertia::render('HomePage'));

Route::get('/profile', fn () => Inertia::render('ProfilePage'));

Route::get('/rating', fn () => Inertia::render('RatingPage'));

// Players: sign-in with Telegram, the detective, statistics. JSON for the Vue pages; the session cookie carries the login.
Route::prefix('player')->group(function (): void {
    Route::get('/me', [PlayerAuthController::class, 'me']);
    Route::post('/login/telegram', [PlayerAuthController::class, 'telegram'])->middleware('throttle:player-login');
    Route::post('/login/dev', [PlayerAuthController::class, 'dev'])->middleware('throttle:player-login');
    Route::post('/logout', [PlayerAuthController::class, 'logout']);
    Route::delete('/account', [PlayerAuthController::class, 'destroy'])->middleware('throttle:player-login');
    Route::post('/detective', [DetectiveController::class, 'store'])->middleware('throttle:player-login');
    Route::post('/skills', [PlayerSkillController::class, 'store'])->middleware('throttle:player-login');
    Route::post('/skills/reset', [PlayerSkillController::class, 'reset'])->middleware('throttle:player-login');
    Route::get('/stats', PlayerStatsController::class);
});

Route::get('/shop/return/{order}', fn (string $order) => Inertia::render('ShopReturnPage', ['order' => $order]));

Route::get('/offer', fn () => Inertia::render('OfferPage'));

Route::get('/privacy', fn () => Inertia::render('PrivacyPage'));

// Payment page of the stand-in provider (404 in production and with a real provider).
Route::get('/shop/fake/{payment}', [FakePaymentController::class, 'show']);
Route::post('/shop/fake/{payment}', [FakePaymentController::class, 'decide']);

Route::get('/story', fn () => Inertia::render('StoryPage'));

Route::get('/shop', fn () => Inertia::render('ShopPage'));

Route::get('/rules', fn () => Inertia::render('RulesPage'));

Route::get('/puzzles', fn () => Inertia::render('PuzzleCatalogPage'));

Route::get('/puzzles/{slug}', fn (string $slug) => Inertia::render('PuzzleGamePage', ['slug' => $slug]));
