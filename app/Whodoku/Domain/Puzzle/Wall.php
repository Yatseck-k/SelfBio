<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle;

readonly class Wall
{
    public Cell $from;

    public Cell $to;

    public function __construct(Cell $first, Cell $second)
    {
        if ([$first->row, $first->col] <= [$second->row, $second->col]) {
            $this->from = $first;
            $this->to = $second;
        } else {
            $this->from = $second;
            $this->to = $first;
        }
    }

    public function key(): string
    {
        return $this->from->key().'|'.$this->to->key();
    }

    public function separates(Cell $first, Cell $second): bool
    {
        return $this->key() === new self($first, $second)->key();
    }
}
