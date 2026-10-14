<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle;

use Whodoku\Domain\Puzzle\Enums\ClueType;

readonly class Clue
{
    /** @param array<string, mixed> $parameters */
    public function __construct(
        public string $text,
        public ClueType $type,
        public array $parameters,
        public int $sortOrder = 0,
    ) {}
}
