<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

use InvalidArgumentException;
use Whodoku\Domain\Puzzle\Enums\PuzzleType;

readonly class GenerationProfile
{
    public function __construct(
        public int $seed = 1,
        public int $maxAttempts = 12,
        public int $rows = 4,
        public int $cols = 4,
        /** Crime type; null lets the generator pick one from the seed. */
        public ?PuzzleType $type = null,
        /** @var list<CaseCondition> conditions of the whole case; each one is added only when it is true for the generated placement */
        public array $conditions = [],
    ) {
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('Generation maxAttempts must be positive.');
        }

        if ($type !== null && !in_array($type, PuzzleType::generated(), true)) {
            throw new InvalidArgumentException('The generator cannot build this puzzle type.');
        }

        if ($rows < 4 || $rows > 9 || $cols < 4 || $cols > 9) {
            throw new InvalidArgumentException('Generated grid dimensions must be between 4 and 9.');
        }
    }

    /** The same profile for another seed: the bot asks for a new case when it rejects one. */
    public function withSeed(int $seed): self
    {
        return new self($seed, $this->maxAttempts, $this->rows, $this->cols, $this->type, $this->conditions);
    }

    public static function murder4x4(int $seed = 1, int $maxAttempts = 12): self
    {
        return new self($seed, $maxAttempts, 4, 4, PuzzleType::Murder);
    }

    /** @param list<CaseCondition> $conditions */
    public static function forGrid(int $rows, int $cols, int $seed = 1, int $maxAttempts = 12, ?PuzzleType $type = null, array $conditions = []): self
    {
        return new self($seed, $maxAttempts, $rows, $cols, $type, $conditions);
    }
}
