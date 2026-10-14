<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Constraints;

use Whodoku\Domain\Puzzle\PuzzleDefinition;

readonly class RoomMembershipConstraint implements ConstraintInterface
{
    public function __construct(
        private string $tokenCode,
        private string $roomCode,
        private bool $mustBeInside,
    ) {}

    public function isSatisfied(PuzzleDefinition $definition, PlacementState $state): bool
    {
        $cell = $state->cellOf($this->tokenCode);
        if ($cell === null) {
            return false;
        }

        $inside = $definition->roomAt($cell)?->code === $this->roomCode;

        return $inside === $this->mustBeInside;
    }

    public function isPossible(PuzzleDefinition $definition, PlacementState $state): bool
    {
        return $state->cellOf($this->tokenCode) === null || $this->isSatisfied($definition, $state);
    }
}
