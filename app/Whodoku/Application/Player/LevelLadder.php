<?php

declare(strict_types=1);

namespace Whodoku\Application\Player;

/**
 * The character level: 100 levels in twenty groups of five. Points of a level step grow with the group (the points
 * between two group starts, divided by five); after the last group a level costs as much as the last step does.
 * Every new level gives one development point (see {@see PlayerSkills}). Levels come from points and open skills;
 * the ranks of the detective come only from the campaign (see {@see RankLadder}).
 */
final class LevelLadder
{
    public const int PER_RANK = 5;

    /** Points at which each group of five levels starts. */
    public const array GROUP_POINTS = [0, 30, 60, 110, 160, 260, 360, 555, 750, 1125, 1500, 2250, 3000, 4500, 6000, 9000, 12000, 18000, 24000, 44000];

    /** Levels after the first point of the last rank cost this many points each. */
    public const int LAST_STEP = 4000;

    public static function maxLevel(): int
    {
        return count(self::GROUP_POINTS) * self::PER_RANK;
    }

    /** Points needed to reach a level (level 1 needs none). */
    public static function pointsFor(int $level): int
    {
        $level = max(1, min($level, self::maxLevel()));
        $rank = intdiv($level - 1, self::PER_RANK);
        $within = ($level - 1) % self::PER_RANK;
        $from = self::GROUP_POINTS[$rank];
        $next = self::GROUP_POINTS[$rank + 1] ?? null;
        $step = $next === null ? self::LAST_STEP : intdiv($next - $from, self::PER_RANK);

        return $from + $within * $step;
    }

    /**
     * @return array{level: int, max: int, points_from: int, next_at: ?int, progress: float}
     */
    public static function forPoints(int $points): array
    {
        $level = 1;
        for ($candidate = 2; $candidate <= self::maxLevel(); $candidate++) {
            if ($points >= self::pointsFor($candidate)) {
                $level = $candidate;
            } else {
                break;
            }
        }
        $from = self::pointsFor($level);
        $next = $level >= self::maxLevel() ? null : self::pointsFor($level + 1);

        return [
            'level' => $level,
            'max' => self::maxLevel(),
            'points_from' => $from,
            'next_at' => $next,
            'progress' => $next === null ? 1.0 : round(($points - $from) / max(1, $next - $from), 3),
        ];
    }

    /** Development points earned so far: one for every level after the first. */
    public static function earnedSkillPoints(int $points): int
    {
        return self::forPoints($points)['level'] - 1;
    }
}
