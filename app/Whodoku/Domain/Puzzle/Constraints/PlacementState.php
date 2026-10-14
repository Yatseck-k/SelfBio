<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Constraints;

use Whodoku\Domain\Puzzle\Cell;
use Whodoku\Domain\Puzzle\Placement;

readonly class PlacementState
{
    /** @var array<string, Cell> */
    private array $cells;

    /** @param list<Placement> $placements */
    public function __construct(array $placements)
    {
        $cells = [];

        foreach ($placements as $placement) {
            $cells[$placement->tokenCode] = $placement->cell;
        }

        $this->cells = $cells;
    }

    public function cellOf(string $tokenCode): ?Cell
    {
        return $this->cells[$tokenCode] ?? null;
    }
}
