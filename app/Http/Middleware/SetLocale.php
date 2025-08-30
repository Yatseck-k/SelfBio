<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    private const RU = 'ru';

    private const EN = 'en';

    public function handle(Request $request, Closure $next)
    {
        $locale = Session::get('locale')
            ?? $request->cookie('locale')
            ?? $this->getPreferredLanguage($request)
            ?? config('app.locale');

        if (in_array($locale, config('app.available_locales', [self::EN, self::RU]))) {
            App::setLocale($locale);
        }

        return $next($request);
    }

    private function getPreferredLanguage(Request $request): ?string
    {
        $acceptLanguage = $request->header('Accept-Language', '');

        if (str_contains($acceptLanguage, self::RU)) {
            return self::RU;
        } elseif (str_contains($acceptLanguage, self::EN)) {
            return self::EN;
        }

        return null;
    }
}
