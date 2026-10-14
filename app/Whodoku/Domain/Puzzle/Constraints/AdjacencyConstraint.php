<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Constraints;

use Whodoku\Domain\Puzzle\Cell;
use Whodoku\Domain\Puzzle\PuzzleDefinition;

readonly class AdjacencyConstraint implements ConstraintInterface
{
    public function __construct(
        private string $tokenCode,
        private ?string $targetTokenCode,
        private ?Cell $objectCell,
        private bool $mustBeAdjacent,
    ) {}

    public function isSatisfied(PuzzleDefinition $definition, PlacementState $state): bool
    {
        $first = $state->cellOf($this->tokenCode);
        $second = $this->targetCell($state);
        if ($first === null || $second === null) {
            return false;
        }

        return $definition->areAdjacent($first, $second) === $this->mustBeAdjacent;
    }

    public function isPossible(PuzzleDefinition $definition, PlacementState $state): bool
    {
        return $state->cellOf($this->tokenCode) === null || $this->targetCell($state) === null
            || $this->isSatisfied($definition, $state);
    }

    private function targetCell(PlacementState $state): ?Cell
    {
        return $this->targetTokenCode === null ? $this->objectCell : $state->cellOf($this->targetTokenCode);
    }
}
