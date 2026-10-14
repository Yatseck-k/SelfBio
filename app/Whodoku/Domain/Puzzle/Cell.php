<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle;

readonly class Cell
{
    public function __construct(public int $row, public int $col) {}

    public function key(): string
    {
        return $this->row.':'.$this->col;
    }

    public function equals(self $other): bool
    {
        return $this->row === $other->row && $this->col === $other->col;
    }
}
