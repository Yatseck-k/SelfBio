<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Whodoku\Http\Controllers\Api\AppearanceController;
use Whodoku\Http\Controllers\Api\EndlessController;
use Whodoku\Http\Controllers\Api\LeaderboardController;
use Whodoku\Http\Controllers\Api\PaymentWebhookController;
use Whodoku\Http\Controllers\Api\PuzzleCheckController;
use Whodoku\Http\Controllers\Api\PuzzleController;
use Whodoku\Http\Controllers\Api\PuzzleHintController;
use Whodoku\Http\Controllers\Api\ShopController;
use Whodoku\Http\Controllers\Api\StoryController;
use Whodoku\Models\Puzzle;

Route::bind('publishedPuzzle', fn (string $slug) => Puzzle::query()
    ->where('status', 'published')
    ->where('slug', $slug)
    ->firstOrFail());

// The `web` group gives these routes the session, so a signed-in player is recognised (and the answers are
// protected from cross-site requests); guests use them the same way as before.
Route::middleware('web')->group(function (): void {
    Route::get('/puzzles', [PuzzleController::class, 'index']);
    Route::get('/puzzles/{publishedPuzzle}', [PuzzleController::class, 'show']);
    Route::post('/puzzles/{publishedPuzzle}/check', PuzzleCheckController::class)->middleware('throttle:puzzle-check');
    Route::post('/puzzles/{publishedPuzzle}/hint', PuzzleHintController::class)->middleware('throttle:puzzle-hint');
    Route::get('/leaderboard', LeaderboardController::class);
    Route::get('/story', StoryController::class);
    Route::post('/endless/next', [EndlessController::class, 'next'])->middleware('throttle:endless-next');
    Route::get('/appearance', [AppearanceController::class, 'show']);
    Route::get('/shop', [ShopController::class, 'show']);
    Route::post('/shop/orders', [ShopController::class, 'store'])->middleware('throttle:shop-order');
    Route::get('/shop/orders/{order}', [ShopController::class, 'status'])->middleware('throttle:shop-status');
});

// The provider calls this without a session or a CSRF token; nothing in the body is trusted (see the controller).
Route::post('/payments/yookassa/webhook', [PaymentWebhookController::class, 'yookassa'])->middleware('throttle:60,1');
