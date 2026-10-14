<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

/**
 * Difficulty from 1 to 5, measured on the case rather than read off the size of the map. Four factors, each
 * from 0 to 1 ({@see DifficultyMetrics}):
 *  - logic (40 %): the hardest technique the bot needed and how many moves were harder than crossing lines;
 *  - clarity (30 %): how weak the statements are and how much of the map a guest could still stand on after
 *    reading only their own statement;
 *  - size (20 %): guests and area of the map;
 *  - conditions (10 %): conditions of the whole case that have to be kept in mind.
 * A big map with plain statements stays easy; a small one with weak statements and conditions gets hard.
 */
final class DifficultyRating
{
    public const float LOGIC = 0.4;

    public const float CLARITY = 0.3;

    public const float SIZE = 0.2;

    public const float CONDITIONS = 0.1;

    /** Rating bounds: the score at which the next level starts (levels 2, 3, 4, 5), tuned on generated cases. */
    public const array THRESHOLDS = [0.15, 0.28, 0.40, 0.55];

    /** @param list<float>|null $thresholds the scores of levels 2–5; the defaults of the model when null */
    public static function of(DifficultyMetrics $metrics, ?array $thresholds = null): int
    {
        $score = self::score($metrics);
        $level = 1;
        foreach ($thresholds ?? self::THRESHOLDS as $threshold) {
            if ($score >= $threshold) {
                $level++;
            }
        }

        return $level;
    }

    public static function score(DifficultyMetrics $metrics): float
    {
        $parts = self::parts($metrics);

        return $parts['logic'] * self::LOGIC + $parts['clarity'] * self::CLARITY + $parts['size'] * self::SIZE + $parts['conditions'] * self::CONDITIONS;
    }

    /** @return array{logic: float, clarity: float, size: float, conditions: float} */
    public static function parts(DifficultyMetrics $metrics): array
    {
        $technique = $metrics->hardestLevel <= 1 ? 0.0 : (min(3, $metrics->hardestLevel) - 1) / 2;
        $deep = min(1.0, $metrics->deepSteps / max(1.0, $metrics->tokens * 0.5));
        $weakness = ($metrics->strength >= ClueStrength::EXACT) ? 0.0 : (ClueStrength::EXACT - $metrics->strength) / (ClueStrength::EXACT - ClueStrength::WEAK);
        $figures = min(1.0, max(0.0, ($metrics->tokens - 4) / 5));
        $area = min(1.0, max(0.0, ($metrics->area - 16) / 65));

        return [
            'logic' => $technique * 0.6 + $deep * 0.4,
            'clarity' => $weakness * 0.5 + min(1.0, $metrics->ambiguity / 0.3) * 0.5,
            'size' => $figures * 0.5 + $area * 0.5,
            'conditions' => min(1.0, $metrics->conditions / 2),
        ];
    }
}
