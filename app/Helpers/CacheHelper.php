<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Helpers\Interfaces\HelperInterface;

class CacheHelper implements HelperInterface
{
    public const TTL_60 = 60;

    public function getClassName(): string
    {
        return self::class;
    }

    public static function getCacheKey(string $string, string $key): string
    {
        return sprintf('%s.%s', $string, $key);
    }
}
