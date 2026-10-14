<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Hints;

use Whodoku\Domain\Puzzle\Cell;
use Whodoku\Domain\Puzzle\Placement;
use Whodoku\Domain\Puzzle\PuzzleDefinition;

/**
 * Builds a hint that matches how far the player has got. A misplaced figure is addressed before an
 * unplaced one; the hint level only controls how much it gives away (figure, room, exact cell).
 */
class HintComposer
{
    public const MAX_LEVEL = 3;

    /**
     * @param  list<Placement>  $solution  the author's complete placement
     * @param  list<Placement>  $current  the player's current placement, possibly partial or wrong
     * @return array{text: string, correct: int, total: int}
     */
    public function compose(PuzzleDefinition $definition, array $solution, array $current, int $level): array
    {
        $level = max(1, min(self::MAX_LEVEL, $level));
        $solutionCells = $this->cellsByToken($solution);
        $currentCells = $this->cellsByToken($current);

        $correct = 0;
        $misplaced = null;
        $unplaced = null;
        foreach ($definition->tokens as $token) {
            $expected = $solutionCells[$token->code] ?? null;
            if ($expected === null) {
                continue;
            }

            $placed = $currentCells[$token->code] ?? null;
            if ($placed === null) {
                $unplaced ??= $token;
            } elseif ($placed->equals($expected)) {
                $correct++;
            } else {
                $misplaced ??= $token;
            }
        }

        $total = count($solutionCells);
        $progress = "Верно расставлено: $correct из $total. ";
        $target = $misplaced ?? $unplaced;
        if ($target === null) {
            return ['text' => $progress.'Все фигуры на своих местах — проверьте решение.', 'correct' => $correct, 'total' => $total];
        }

        $cell = $solutionCells[$target->code];
        $room = $definition->roomAt($cell);
        $roomPhrase = $room !== null ? "в комнате «{$room->name}»" : 'вне комнат';
        $object = null;
        foreach ($definition->objectsAt($cell) as $candidate) {
            $object = $candidate;
            break;
        }
        $objectText = $object !== null ? " у предмета «{$object->name}»" : '';
        $coordinates = 'строка '.($cell->row + 1).', столбец '.($cell->col + 1);

        $text = $misplaced !== null
            ? match ($level) {
                1 => "Фигура {$target->name} стоит не на своём месте — попробуйте переставить её.",
                2 => "Фигура {$target->name} стоит не на своём месте. Её нужно искать $roomPhrase.",
                default => "Фигура {$target->name} стоит не на своём месте. Её место: $coordinates, $roomPhrase{$objectText}.",
            }
        : match ($level) {
            1 => "Присмотритесь к фигуре {$target->name}: она находится $roomPhrase.",
            2 => "Фигура {$target->name} находится $roomPhrase{$objectText}.",
            default => "Фигура {$target->name} стоит здесь: $coordinates, $roomPhrase{$objectText}.",
        };

        return ['text' => $progress.$text, 'correct' => $correct, 'total' => $total];
    }

    /**
     * @param  list<Placement>  $placements
     * @return array<string, Cell>
     */
    private function cellsByToken(array $placements): array
    {
        $cells = [];
        foreach ($placements as $placement) {
            $cells[$placement->tokenCode] = $placement->cell;
        }

        return $cells;
    }
}
