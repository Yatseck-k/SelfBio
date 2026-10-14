<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Validation;

use Whodoku\Domain\Puzzle\Constraints\ConstraintFactory;
use Whodoku\Domain\Puzzle\Constraints\InvalidConstraintException;
use Whodoku\Domain\Puzzle\PuzzleDefinition;
use Whodoku\Domain\Puzzle\Window;

class PuzzleStructureValidator
{
    /** @return list<ValidationIssue> */
    public function validate(PuzzleDefinition $definition): array
    {
        $issues = [];
        $grid = $definition->grid;

        if ($grid->rows <= 0) {
            $issues[] = new ValidationIssue('grid.rows_positive', 'grid.rows');
        }

        if ($grid->cols <= 0) {
            $issues[] = new ValidationIssue('grid.cols_positive', 'grid.cols');
        }

        $blocked = [];
        foreach ($grid->blockedCells as $index => $cell) {
            $path = "grid.blockedCells.$index";
            if (!$grid->contains($cell)) {
                $issues[] = new ValidationIssue('cell.out_of_bounds', $path);
            }

            if (isset($blocked[$cell->key()])) {
                $issues[] = new ValidationIssue('cell.duplicate_blocked', $path);
            }

            $blocked[$cell->key()] = true;
        }

        $roomCodes = [];
        $roomCells = [];
        foreach ($definition->rooms as $roomIndex => $room) {
            $path = "rooms.$roomIndex";
            if (isset($roomCodes[$room->code])) {
                $issues[] = new ValidationIssue('room.duplicate_code', "$path.code");
            }
            $roomCodes[$room->code] = true;

            if ($room->cells === []) {
                $issues[] = new ValidationIssue('room.empty', "$path.cells");
            }

            $withinRoom = [];
            foreach ($room->cells as $cellIndex => $cell) {
                $cellPath = "$path.cells.$cellIndex";
                if (!$grid->contains($cell)) {
                    $issues[] = new ValidationIssue('cell.out_of_bounds', $cellPath);
                }

                if (isset($withinRoom[$cell->key()])) {
                    $issues[] = new ValidationIssue('room.duplicate_cell', $cellPath);
                } elseif (isset($roomCells[$cell->key()])) {
                    $issues[] = new ValidationIssue('room.overlap', $cellPath);
                }

                $withinRoom[$cell->key()] = true;
                $roomCells[$cell->key()] = true;
            }
        }

        $walls = [];
        foreach ($definition->walls as $index => $wall) {
            $path = "walls.$index";
            if (!$grid->contains($wall->from) || !$grid->contains($wall->to)) {
                $issues[] = new ValidationIssue('wall.out_of_bounds', $path);
            } elseif (!$grid->areOrthogonallyAdjacent($wall->from, $wall->to)) {
                $issues[] = new ValidationIssue('wall.not_adjacent', $path);
            }

            if (isset($walls[$wall->key()])) {
                $issues[] = new ValidationIssue('wall.duplicate', $path);
            }
            $walls[$wall->key()] = true;
        }

        $windows = [];
        foreach ($definition->windows as $index => $window) {
            $path = "windows.$index";
            if (!$grid->contains($window->cell)) {
                $issues[] = new ValidationIssue('cell.out_of_bounds', "$path.cell");
            } elseif (!in_array($window->side, Window::SIDES, true)) {
                $issues[] = new ValidationIssue('window.invalid_side', "$path.side");
            } elseif ($grid->contains($window->across()) && $definition->roomAt($window->cell)?->code === $definition->roomAt($window->across())?->code) {
                // A window sits on a thick line: the map edge or a border between two rooms.
                $issues[] = new ValidationIssue('window.not_on_border', $path);
            }

            // The same line may be given from either of its two cells.
            $line = [$window->cell->key(), $window->across()->key()];
            sort($line);
            if (isset($windows[implode('|', $line)])) {
                $issues[] = new ValidationIssue('window.duplicate', $path);
            }
            $windows[implode('|', $line)] = true;
        }

        $objectCodes = [];
        foreach ($definition->objects as $index => $object) {
            $path = "objects.$index";
            if (isset($objectCodes[$object->code])) {
                $issues[] = new ValidationIssue('object.duplicate_code', "$path.code");
            }
            $objectCodes[$object->code] = true;

            if (!$grid->contains($object->cell)) {
                $issues[] = new ValidationIssue('cell.out_of_bounds', "$path.cell");
            }

            if ($object->blocking && $object->occupiable) {
                $issues[] = new ValidationIssue('object.blocking_occupiable', "$path.occupiable");
            }
        }

        $tokenCodes = [];
        foreach ($definition->tokens as $index => $token) {
            if (isset($tokenCodes[$token->code])) {
                $issues[] = new ValidationIssue('token.duplicate_code', "tokens.$index.code");
            }
            $tokenCodes[$token->code] = true;
        }

        $factory = new ConstraintFactory;
        foreach ($definition->clues as $index => $clue) {
            try {
                $factory->create($definition, $clue, $index);
            } catch (InvalidConstraintException $exception) {
                $issues[] = new ValidationIssue('clue.invalid_constraint', $exception->path);
            }
        }

        return $issues;
    }
}
