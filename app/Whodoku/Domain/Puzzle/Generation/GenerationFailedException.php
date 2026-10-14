<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

use RuntimeException;

class GenerationFailedException extends RuntimeException
{
    public function __construct(
        public readonly int $attempts,
        public readonly string $lastStatus,
    ) {
        parent::__construct("Puzzle generation failed after $attempts attempts: $lastStatus.");
    }
}
