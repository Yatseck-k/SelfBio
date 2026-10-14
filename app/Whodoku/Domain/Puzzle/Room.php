<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle;

readonly class Room
{
    /** @param list<Cell> $cells */
    public function __construct(
        public string $code,
        public string $name,
        public array $cells,
    ) {}

    public function contains(Cell $cell): bool
    {
        return array_any($this->cells, fn ($roomCell) => $roomCell->equals($cell));
    }
}
