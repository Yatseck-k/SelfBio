<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Constraints;

use Whodoku\Domain\Puzzle\PuzzleDefinition;

/** «Возле окна»: the guest stands on a cell right next to a window (in the given room, when one is named). */
readonly class WindowProximityConstraint implements ConstraintInterface
{
    public function __construct(private string $tokenCode, private ?string $roomCode = null) {}

    public function isSatisfied(PuzzleDefinition $definition, PlacementState $state): bool
    {
        $cell = $state->cellOf($this->tokenCode);
        if ($cell === null) {
            return false;
        }

        return array_any($definition->windowCells($this->roomCode), fn ($near) => $near->equals($cell));
    }

    public function isPossible(PuzzleDefinition $definition, PlacementState $state): bool
    {
        return $state->cellOf($this->tokenCode) === null || $this->isSatisfied($definition, $state);
    }
}
