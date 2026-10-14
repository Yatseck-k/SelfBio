<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Constraints;

use Whodoku\Domain\Puzzle\PuzzleDefinition;

readonly class DistanceConstraint implements ConstraintInterface
{
    public function __construct(
        private string $tokenCode,
        private string $targetTokenCode,
        private string $direction,
        private int $distance,
    ) {}

    public function isSatisfied(PuzzleDefinition $definition, PlacementState $state): bool
    {
        $first = $state->cellOf($this->tokenCode);
        $second = $state->cellOf($this->targetTokenCode);
        if ($first === null || $second === null) {
            return false;
        }

        return match ($this->direction) {
            'north' => $second->row - $first->row === $this->distance,
            'south' => $first->row - $second->row === $this->distance,
            'west' => $second->col - $first->col === $this->distance,
            'east' => $first->col - $second->col === $this->distance,
            default => false,
        };
    }

    public function isPossible(PuzzleDefinition $definition, PlacementState $state): bool
    {
        return $state->cellOf($this->tokenCode) === null || $state->cellOf($this->targetTokenCode) === null
            || $this->isSatisfied($definition, $state);
    }
}
