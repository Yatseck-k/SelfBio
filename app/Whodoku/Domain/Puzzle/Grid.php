<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle;

readonly class Grid
{
    /** @param list<Cell> $blockedCells */
    public function __construct(
        public int $rows,
        public int $cols,
        public array $blockedCells = [],
    ) {}

    public function contains(Cell $cell): bool
    {
        return $cell->row >= 0 && $cell->row < $this->rows
            && $cell->col >= 0 && $cell->col < $this->cols;
    }

    public function isBlocked(Cell $cell): bool
    {
        return array_any($this->blockedCells, fn ($blocked) => $blocked->equals($cell));
    }

    public function areOrthogonallyAdjacent(Cell $first, Cell $second): bool
    {
        return $this->contains($first) && $this->contains($second)
            && abs($first->row - $second->row) + abs($first->col - $second->col) === 1;
    }
}
