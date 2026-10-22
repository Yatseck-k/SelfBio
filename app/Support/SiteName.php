<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * The name of the site. The editors set it in the admin panel («Настройки сайта»); until they do, APP_NAME is used.
 * Every page, title and admin screen reads `config('app.name')`, so the saved name is put there at the start of the app.
 */
final class SiteName
{
    public const string KEY = 'site.name';

    private const string CACHE_KEY = 'site.name';

    public const int MAX_LENGTH = 60;

    /** Put the saved name into the config; the database may not exist yet (migrations, build), then APP_NAME stays. */
    public static function apply(): void
    {
        // Without a saved name the name of the environment comes back (a cached config may still hold a name that was reset).
        self::adopt(self::saved() ?? (string) config('app.default_name', config('app.name')));
    }

    public static function save(string $name): void
    {
        SiteSetting::query()->updateOrCreate(['key' => self::KEY], ['value' => $name]);
        Cache::forget(self::CACHE_KEY);
        self::adopt($name);
    }

    /** Back to APP_NAME from the environment. */
    public static function reset(): void
    {
        SiteSetting::query()->where('key', self::KEY)->delete();
        Cache::forget(self::CACHE_KEY);
        self::adopt((string) config('app.default_name', config('app.name')));
    }

    public static function valid(string $name): bool
    {
        return $name !== '' && mb_strlen($name) <= self::MAX_LENGTH && preg_match('/[\x00-\x1F\x7F]/u', $name) !== 1;
    }

    /** The name of the site is also the name of the sender of the letters, unless MAIL_FROM_NAME was set to something of its own. */
    private static function adopt(string $name): void
    {
        $senderFollowsSite = config('mail.from.name') === config('app.name');
        config(['app.name' => $name]);
        if ($senderFollowsSite) {
            config(['mail.from.name' => $name]);
        }
    }

    private static function saved(): ?string
    {
        $value = rescue(
            fn () => Cache::remember(self::CACHE_KEY, 3600, fn (): string => (string) SiteSetting::query()->where('key', self::KEY)->value('value')),
            '',
            false,
        );

        return is_string($value) && $value !== '' ? $value : null;
    }
}
