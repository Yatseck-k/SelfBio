<?php

declare(strict_types=1);

namespace Whodoku\Application\Player;

/**
 * The ranks of a detective, from a trainee to a grandmaster of deduction. Ranks are given only by the campaign
 * («Путь детектива»): the rank follows the number of solved chapters (the first chapters teach the game and lead to
 * the junior detective); points and levels (see {@see LevelLadder}) are a separate line that opens skills.
 */
final class RankLadder
{
    /** @var list<array{int, string}> solved chapters needed, title */
    public const array RANKS = [
        [0, 'Стажёр'],
        [2, 'Практикант'],
        [4, 'Помощник детектива'],
        [7, 'Старший помощник'],
        [10, 'Младший детектив'],
        [13, 'Следователь'],
        [15, 'Детектив'],
        [18, 'Опытный детектив'],
        [21, 'Старший детектив'],
        [23, 'Криминалист'],
        [26, 'Инспектор'],
        [29, 'Старший инспектор'],
        [31, 'Комиссар'],
        [34, 'Главный комиссар'],
        [37, 'Мастер сыска'],
        [39, 'Великий мастер'],
        [42, 'Легенда сыска'],
        [44, 'Живая легенда'],
        [47, 'Шерлок Холмс'],
        [50, 'Гроссмейстер дедукции'],
    ];

    /**
     * @return array{level: int, title: string, chapters_from: int, next_title: ?string, next_at: ?int, progress: float}
     */
    public static function forChapters(int $solved): array
    {
        $index = 0;
        foreach (self::RANKS as $position => [$needed]) {
            if ($solved >= $needed) {
                $index = $position;
            }
        }
        [$from, $title] = self::RANKS[$index];
        $next = self::RANKS[$index + 1] ?? null;

        return [
            'level' => $index + 1,
            'title' => $title,
            'chapters_from' => $from,
            'next_title' => $next[1] ?? null,
            'next_at' => $next[0] ?? null,
            'progress' => $next === null ? 1.0 : round(($solved - $from) / max(1, $next[0] - $from), 3),
        ];
    }

    /** Points for a solved case: ten per difficulty level, a quarter more when no hint was used. */
    public static function pointsForSolve(int $difficulty, int $hintsUsed): int
    {
        $base = 10 * max(1, $difficulty);

        return $hintsUsed === 0 ? $base + (int) round($base * 0.25) : $base;
    }
}
