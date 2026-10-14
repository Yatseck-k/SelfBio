<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Solver;

use InvalidArgumentException;

readonly class SolverLimits
{
    public function __construct(
        public int $maxNodes = 100_000,
        public int $maxMilliseconds = 5_000,
    ) {
        if ($maxNodes < 0 || $maxMilliseconds < 0) {
            throw new InvalidArgumentException('Solver limits must not be negative.');
        }
    }
}
