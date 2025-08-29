<?php

declare(strict_types=1);

namespace App\Services;

class CacheService
{
    public const TTL_60 = 60;

    public function getCacheKey(string $string, string $key): string
    {
        return sprintf('%s.%s', $string, $key);
    }
}
