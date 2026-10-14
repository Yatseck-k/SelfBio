<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Constraints;

use Whodoku\Domain\Puzzle\PuzzleDefinition;

readonly class RoomRelationConstraint implements ConstraintInterface
{
    public function __construct(
        private string $tokenCode,
        private string $targetTokenCode,
        private bool $sameRoom,
    ) {}

    public function isSatisfied(PuzzleDefinition $definition, PlacementState $state): bool
    {
        $first = $state->cellOf($this->tokenCode);
        $second = $state->cellOf($this->targetTokenCode);
        if ($first === null || $second === null) {
            return false;
        }

        $firstRoom = $definition->roomAt($first);
        $secondRoom = $definition->roomAt($second);
        if ($firstRoom === null || $secondRoom === null) {
            return false;
        }

        return ($firstRoom->code === $secondRoom->code) === $this->sameRoom;
    }

    public function isPossible(PuzzleDefinition $definition, PlacementState $state): bool
    {
        return $state->cellOf($this->tokenCode) === null || $state->cellOf($this->targetTokenCode) === null
            || $this->isSatisfied($definition, $state);
    }
}
