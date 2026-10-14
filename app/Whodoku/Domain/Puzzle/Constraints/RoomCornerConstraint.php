<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Constraints;

use Whodoku\Domain\Puzzle\Cell;
use Whodoku\Domain\Puzzle\PuzzleDefinition;
use Whodoku\Domain\Puzzle\Room;

readonly class RoomCornerConstraint implements ConstraintInterface
{
    public function __construct(private string $tokenCode, private Room $room) {}

    public function isSatisfied(PuzzleDefinition $definition, PlacementState $state): bool
    {
        $cell = $state->cellOf($this->tokenCode);
        if ($cell === null || !$this->room->contains($cell)) {
            return false;
        }

        $north = !$this->room->contains(new Cell($cell->row - 1, $cell->col));
        $south = !$this->room->contains(new Cell($cell->row + 1, $cell->col));
        $west = !$this->room->contains(new Cell($cell->row, $cell->col - 1));
        $east = !$this->room->contains(new Cell($cell->row, $cell->col + 1));

        return ($north && $west) || ($north && $east) || ($south && $west) || ($south && $east);
    }

    public function isPossible(PuzzleDefinition $definition, PlacementState $state): bool
    {
        return $state->cellOf($this->tokenCode) === null || $this->isSatisfied($definition, $state);
    }
}
