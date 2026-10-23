<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\PageTexts;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see [https://inertiajs.com/server-side-setup#root-template](https://inertiajs.com/server-side-setup#root-template)
     *
     * @var string
     */
    #[\Override]
    protected $rootView = 'app';

    public function rootView(Request $request): string
    {
        return $request->is('game', 'profile', 'rating', 'story', 'shop', 'shop/return/*', 'rules', 'offer', 'privacy', 'puzzles*')
            ? 'whodoku'
            : 'app';
    }

    /**
     * Determines the current asset version.
     *
     * @see [https://inertiajs.com/asset-versioning](https://inertiajs.com/asset-versioning)
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see [https://inertiajs.com/shared-data](https://inertiajs.com/shared-data)
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'signedIn' => $request->user() !== null,
            // The name of the site (APP_NAME, changed by `php artisan site:rename`): pages never spell it out.
            'brand' => config('app.name'),
            // Shared props are prepared before route middleware switches the locale for /en.
            'locale' => $request->is('en', 'en/*') ? 'en' : 'ru',
            // One-time message after a redirect («Пароль изменён»).
            'status' => fn () => $request->session()->get('status'),
            // The headings and explanations of the pages that the editors change in the admin panel.
            'pageTexts' => PageTexts::all(),
        ];
    }
}
