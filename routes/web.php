<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\RepairCatalogController;
use App\Http\Controllers\RepairController;
use App\Http\Controllers\SeoController;
use App\Http\Middleware\EnglishProfile;
use App\Models\BlogPost;
use App\Services\ContactInfoService;
use App\Support\Profile;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);

Route::get('/', fn (ContactInfoService $contacts) => Inertia::render('Welcome', [
    'contact' => $contacts->getContactInfo()?->toArray(),
    'profile' => Profile::content(),
    'posts' => BlogPost::query()->published()->latest('published_at')->limit(3)->get(['slug', 'title', 'preview', 'published_at']),
]));
Route::get('/about', fn () => Inertia::render('AboutPage', ['profile' => Profile::content()]));
Route::get('/contacts', fn (ContactInfoService $contacts) => Inertia::render('ContactPage', [
    'contact' => $contacts->getContactInfo()?->toArray(),
]));
Route::prefix('en')->middleware(EnglishProfile::class)->group(function (): void {
    Route::get('/', fn (ContactInfoService $contacts) => Inertia::render('Welcome', [
        'contact' => $contacts->getContactInfo()?->toArray(),
        'profile' => Profile::content('en'),
        'posts' => BlogPost::query()->published()->latest('published_at')->limit(3)->get(['slug', 'title', 'preview', 'published_at']),
    ]));
    Route::get('/about', fn () => Inertia::render('AboutPage', ['profile' => Profile::content('en')]));
    Route::get('/contacts', fn (ContactInfoService $contacts) => Inertia::render('ContactPage', [
        'contact' => $contacts->getContactInfo()?->toArray(),
    ]));
});
Route::get('/blog', fn () => Inertia::render('BlogPage'));
Route::get('/blog/{slug}', function (string $slug) {
    // A post that does not exist (or is not published yet) is a real 404 for people and bots, not an empty page with code 200.
    abort_unless(BlogPost::query()->published()->where('slug', $slug)->exists(), 404);

    return Inertia::render('BlogPostPage', ['slug' => $slug]);
});

Route::get('/game-admin/{path?}', fn (?string $path = null) => redirect('/admin'.($path ? '/'.$path : '')))
    ->where('path', '.*');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:account-login');

    // A new password by a link in a letter.
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:password-email')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/account', [AccountController::class, 'index'])->name('account.index');
    Route::get('/account/repair', [RepairController::class, 'index'])->name('repair.index');
    Route::get('/repair', fn () => to_route('repair.index', status: 301));
    Route::get('/Ремонт/{path?}', [RepairController::class, 'repairFile'])->where('path', '.*');
    Route::get('/archive/{path}', [RepairController::class, 'archiveFile'])->where('path', '.*');
    Route::get('/api/catalog', [RepairController::class, 'catalog']);
    Route::get('/api/undo-state', [RepairCatalogController::class, 'undoState']);
    Route::post('/api/status', [RepairCatalogController::class, 'status']);
    Route::post('/api/move', [RepairCatalogController::class, 'move']);
    Route::post('/api/undo', [RepairCatalogController::class, 'undo']);
    Route::post('/api/import', [RepairCatalogController::class, 'import']);
});
