<?php

declare(strict_types=1);

namespace Whodoku\Support;

/** Russian number agreement: «1 очко, 2 очка, 5 очков». */
final class Plural
{
    /** The form for a count: one for 1, 21…; few for 2–4, 22…; many for the rest (5–20, 0). */
    public static function ru(int $count, string $one, string $few, string $many): string
    {
        $count = abs($count);
        if ($count % 100 >= 11 && $count % 100 <= 14) {
            return $many;
        }

        return match ($count % 10) {
            1 => $one,
            2, 3, 4 => $few,
            default => $many,
        };
    }
}
