<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Validation;

use Whodoku\Domain\Puzzle\Placement;
use Whodoku\Domain\Puzzle\PuzzleDefinition;

class PlacementValidator
{
    /**
     * @param  list<Placement>  $placements
     * @return list<ValidationIssue>
     */
    public function validate(PuzzleDefinition $definition, array $placements, bool $requireComplete = false): array
    {
        $issues = [];
        $seenTokens = [];
        $seenCells = [];
        $usedRows = [];
        $usedCols = [];

        foreach ($placements as $index => $placement) {
            $path = "placements.$index";
            $token = $definition->tokenByCode($placement->tokenCode);
            if ($token === null) {
                $issues[] = new ValidationIssue('placement.unknown_token', "$path.tokenCode");
            }

            $firstForToken = !isset($seenTokens[$placement->tokenCode]);
            if (!$firstForToken) {
                $issues[] = new ValidationIssue('placement.duplicate_token', "$path.tokenCode");
            }
            $seenTokens[$placement->tokenCode] = true;

            $cell = $placement->cell;
            if (isset($seenCells[$cell->key()])) {
                $issues[] = new ValidationIssue('placement.duplicate_cell', "$path.cell");
            }
            $seenCells[$cell->key()] = true;

            if (!$definition->grid->contains($cell)) {
                $issues[] = new ValidationIssue('cell.out_of_bounds', "$path.cell");

                continue;
            }

            if ($definition->grid->isBlocked($cell)) {
                $issues[] = new ValidationIssue('placement.blocked_cell', "$path.cell");
            }

            foreach ($definition->objectsAt($cell) as $object) {
                if ($object->blocking || !$object->occupiable) {
                    $issues[] = new ValidationIssue('placement.object_not_occupiable', "$path.cell");

                    break;
                }
            }

            if ($token === null || !$firstForToken || !$token->countsForRowCol) {
                continue;
            }

            if (isset($usedRows[$cell->row])) {
                $issues[] = new ValidationIssue('placement.row_conflict', "$path.cell.row");
            }
            $usedRows[$cell->row] = true;

            if (isset($usedCols[$cell->col])) {
                $issues[] = new ValidationIssue('placement.col_conflict', "$path.cell.col");
            }
            $usedCols[$cell->col] = true;
        }

        if ($requireComplete) {
            foreach ($definition->tokens as $token) {
                if (!isset($seenTokens[$token->code])) {
                    $issues[] = new ValidationIssue('placement.missing_token', "tokens.$token->code");
                }
            }
        }

        return $issues;
    }
}
