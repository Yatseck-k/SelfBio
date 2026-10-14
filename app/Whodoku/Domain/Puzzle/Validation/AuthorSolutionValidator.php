<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Validation;

use Whodoku\Domain\Puzzle\Constraints\ConstraintEngine;
use Whodoku\Domain\Puzzle\Placement;
use Whodoku\Domain\Puzzle\PuzzleDefinition;

class AuthorSolutionValidator
{
    /** @param list<Placement> $placements
     * @return list<ValidationIssue>
     */
    public function validate(PuzzleDefinition $definition, array $placements): array
    {
        $issues = (new PuzzleStructureValidator)->validate($definition);
        if ($issues !== []) {
            return $issues;
        }

        $issues = (new PlacementValidator)->validate($definition, $placements, true);
        if ($issues !== []) {
            return $issues;
        }

        if (!new ConstraintEngine($definition)->isSatisfied($placements)) {
            return [new ValidationIssue('solution.constraints_unsatisfied', 'solution')];
        }

        return [];
    }
}
