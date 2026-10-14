<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle;

readonly class Placement
{
    public function __construct(
        public string $tokenCode,
        public Cell $cell,
    ) {}
}
