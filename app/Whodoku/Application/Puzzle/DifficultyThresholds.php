<?php

declare(strict_types=1);

namespace Whodoku\Application\Puzzle;

use Whodoku\Application\Settings\GameSettings;
use Whodoku\Domain\Puzzle\Generation\DifficultyRating;

/** The scores at which the difficulty levels 2–5 start: the defaults of the model, or what the administrator tuned. */
class DifficultyThresholds
{
    public const KEY = 'difficulty.thresholds';

    public function __construct(private readonly GameSettings $settings = new GameSettings) {}

    /** @return list<float> four ascending scores */
    public function current(): array
    {
        $stored = $this->settings->get(self::KEY)['values'] ?? null;

        return is_array($stored) && count($stored) === 4 ? array_map(floatval(...), array_values($stored)) : DifficultyRating::THRESHOLDS;
    }

    public function isCustom(): bool
    {
        return $this->current() !== DifficultyRating::THRESHOLDS;
    }

    /** @param list<float|int|string> $values */
    public function valid(array $values): bool
    {
        if (count($values) !== 4) {
            return false;
        }
        $previous = 0.0;
        foreach ($values as $value) {
            if (!is_numeric($value) || (float) $value <= $previous || (float) $value >= 1.0) {
                return false;
            }
            $previous = (float) $value;
        }

        return true;
    }

    /** @param list<float|int|string> $values */
    public function save(array $values): void
    {
        $this->valid($values) || throw new \InvalidArgumentException('Thresholds must be four ascending scores between 0 and 1.');
        $this->settings->put(self::KEY, ['values' => array_map(fn ($value) => round((float) $value, 3), $values)]);
    }

    public function reset(): void
    {
        $this->settings->forget(self::KEY);
    }
}
