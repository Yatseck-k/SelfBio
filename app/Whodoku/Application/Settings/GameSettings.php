<?php

declare(strict_types=1);

namespace Whodoku\Application\Settings;

use Illuminate\Support\Facades\Cache;
use Whodoku\Models\AppearanceSetting;

/**
 * Settings the administrator changes without a deploy (thresholds of skills and of the difficulty levels). They live
 * in the same key/value table as the appearance settings; a missing key means «as in the config».
 */
class GameSettings
{
    private const string CACHE_PREFIX = 'game-settings:';

    /** @var array<string, array<string, mixed>|false> read once per instance: the cache store is a table, and a catalog of cases asks hundreds of times */
    private array $loaded = [];

    /** @return array<string, mixed>|null */
    public function get(string $key): ?array
    {
        $value = $this->loaded[$key] ??= Cache::rememberForever(self::CACHE_PREFIX.$key, fn () => AppearanceSetting::query()->find($key)->value ?? false);

        return is_array($value) ? $value : null;
    }

    /** @param array<string, mixed> $value */
    public function put(string $key, array $value): void
    {
        AppearanceSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_PREFIX.$key);
        unset($this->loaded[$key]);
    }

    public function forget(string $key): void
    {
        AppearanceSetting::query()->where('key', $key)->delete();
        Cache::forget(self::CACHE_PREFIX.$key);
        unset($this->loaded[$key]);
    }
}
