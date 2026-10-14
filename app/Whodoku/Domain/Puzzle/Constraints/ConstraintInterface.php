<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Constraints;

use Whodoku\Domain\Puzzle\PuzzleDefinition;

interface ConstraintInterface
{
    public function isSatisfied(PuzzleDefinition $definition, PlacementState $state): bool;

    public function isPossible(PuzzleDefinition $definition, PlacementState $state): bool;
}
