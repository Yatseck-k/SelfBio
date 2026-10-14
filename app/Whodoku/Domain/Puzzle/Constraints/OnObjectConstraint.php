<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Constraints;

use Whodoku\Domain\Puzzle\MapObject;
use Whodoku\Domain\Puzzle\PuzzleDefinition;

readonly class OnObjectConstraint implements ConstraintInterface
{
    public function __construct(private string $tokenCode, private MapObject $object) {}

    public function isSatisfied(PuzzleDefinition $definition, PlacementState $state): bool
    {
        $cell = $state->cellOf($this->tokenCode);

        return $cell !== null && $this->object->occupiable && !$this->object->blocking
            && $cell->equals($this->object->cell);
    }

    public function isPossible(PuzzleDefinition $definition, PlacementState $state): bool
    {
        return $state->cellOf($this->tokenCode) === null || $this->isSatisfied($definition, $state);
    }
}
