<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Constraints;

use Whodoku\Domain\Puzzle\Placement;
use Whodoku\Domain\Puzzle\PuzzleDefinition;
use Whodoku\Domain\Puzzle\Validation\PlacementValidator;

readonly class ConstraintEngine
{
    /** @var list<ConstraintInterface> */
    private array $constraints;

    public function __construct(private PuzzleDefinition $definition)
    {
        $this->constraints = (new ConstraintFactory)->compile($definition);
    }

    /** @param list<Placement> $placements */
    public function isSatisfied(array $placements): bool
    {
        if ((new PlacementValidator)->validate($this->definition, $placements, true) !== []) {
            return false;
        }

        $state = new PlacementState($placements);

        return array_all($this->constraints, fn ($constraint) => $constraint->isSatisfied($this->definition, $state));
    }

    /** @param list<Placement> $placements */
    public function isPossible(array $placements): bool
    {
        if ((new PlacementValidator)->validate($this->definition, $placements) !== []) {
            return false;
        }

        $state = new PlacementState($placements);

        return array_all($this->constraints, fn ($constraint) => $constraint->isPossible($this->definition, $state));
    }
}
