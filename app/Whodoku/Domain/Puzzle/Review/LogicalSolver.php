<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Review;

use Whodoku\Domain\Puzzle\Cell;
use Whodoku\Domain\Puzzle\Constraints\ConstraintEngine;
use Whodoku\Domain\Puzzle\Placement;
use Whodoku\Domain\Puzzle\PuzzleDefinition;

/**
 * Solves a case the way a player does, without trial and error: it keeps a list of possible cells per guest and
 * uses only techniques that can be explained on paper:
 *  1. a guest with one possible cell stands there, and the row and column of every placed guest are crossed out;
 *  2. when exactly as many rows (columns) can still be occupied as there are guests left, every one of them is
 *     occupied: a line that only one guest can reach holds that guest («в линии осталась одна клетка»);
 *  3. when k guests can only be in k rows (columns), those lines belong to them and are crossed out for the others.
 * It never backtracks, so a case it cannot finish would need guessing and is not a good case.
 */
class LogicalSolver
{
    public function solve(PuzzleDefinition $definition): LogicalSolveResult
    {
        $engine = new ConstraintEngine($definition);
        $tokens = $definition->tokens;

        /** @var array<string, array<string, Cell>> $candidates */
        $candidates = [];
        foreach ($tokens as $token) {
            $candidates[$token->code] = [];
            for ($row = 0; $row < $definition->grid->rows; $row++) {
                for ($col = 0; $col < $definition->grid->cols; $col++) {
                    $cell = new Cell($row, $col);
                    if ($engine->isPossible([new Placement($token->code, $cell)])) {
                        $candidates[$token->code][$cell->key()] = $cell;
                    }
                }
            }
        }

        $initial = array_map(count(...), $candidates);
        $placed = [];
        $steps = [];
        $pairwise = $this->hasGroupClues($definition);

        while (count($placed) < count($tokens)) {
            if (!$this->propagate($engine, $placed, $candidates)) {
                return $this->result(LogicalSolveResult::INCONSISTENT, $steps, $placed, $initial);
            }

            $step = $this->single($definition, $placed, $candidates)
                ?? $this->forcedLine($definition, $placed, $candidates)
                ?? $this->subset($definition, $placed, $candidates)
                ?? ($pairwise ? $this->pair($engine, $placed, $candidates) : null);
            if ($step === null) {
                return $this->result(LogicalSolveResult::STUCK, $steps, $placed, $initial);
            }
            $steps[] = $step;
        }

        $all = $this->placements($placed);

        return $this->result($engine->isSatisfied($all) ? LogicalSolveResult::SOLVED : LogicalSolveResult::INCONSISTENT, $steps, $placed, $initial);
    }

    /** Clues that compare guests with each other need the pairwise technique. */
    private function hasGroupClues(PuzzleDefinition $definition): bool
    {
        return array_any($definition->clues, fn ($clue) => isset($clue->parameters['target_token']) || $clue->type->isCondition());
    }

    /**
     * Drops every cell that contradicts a placed guest (their row and column, «один в комнате», …).
     *
     * @param  array<string, Cell>  $placed
     * @param  array<string, array<string, Cell>>  $candidates
     */
    private function propagate(ConstraintEngine $engine, array $placed, array &$candidates): bool
    {
        $base = $this->placements($placed);
        foreach ($candidates as $code => $cells) {
            if (isset($placed[$code])) {
                continue;
            }
            foreach ($cells as $key => $cell) {
                if (!$engine->isPossible([...$base, new Placement($code, $cell)])) {
                    unset($candidates[$code][$key]);
                }
            }
            if ($candidates[$code] === []) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, Cell>  $placed
     * @param  array<string, array<string, Cell>>  $candidates
     */
    private function single(PuzzleDefinition $definition, array &$placed, array $candidates): ?LogicStep
    {
        foreach ($definition->tokens as $token) {
            if (isset($placed[$token->code]) || count($candidates[$token->code]) !== 1) {
                continue;
            }
            $cell = array_first($candidates[$token->code]);
            $placed[$token->code] = $cell;

            return new LogicStep(
                LogicStep::SINGLE, 1,
                sprintf('%s: остаётся одна клетка — строка %d, столбец %d; её строка и столбец перекрыты.', $token->name, $cell->row + 1, $cell->col + 1),
                $token->code, $cell,
            );
        }

        return null;
    }

    /**
     * @param  array<string, Cell>  $placed
     * @param  array<string, array<string, Cell>>  $candidates
     */
    private function forcedLine(PuzzleDefinition $definition, array $placed, array &$candidates): ?LogicStep
    {
        $open = $this->openTokens($definition, $placed);
        foreach (['row' => 'строке', 'col' => 'столбце'] as $axis => $word) {
            $lines = [];
            foreach ($open as $code) {
                foreach ($candidates[$code] as $cell) {
                    $lines[$cell->{$axis}][$code] = true;
                }
            }
            // Only when every line that can still be used must be used.
            if (count($lines) !== count($open)) {
                continue;
            }
            foreach ($lines as $line => $holders) {
                if (count($holders) !== 1) {
                    continue;
                }
                $code = array_key_first($holders);
                $outside = array_filter($candidates[$code], fn (Cell $cell) => $cell->{$axis} !== $line);
                if ($outside === []) {
                    continue;
                }
                foreach (array_keys($outside) as $key) {
                    unset($candidates[$code][$key]);
                }

                return new LogicStep(
                    LogicStep::FORCED_LINE, 2,
                    sprintf('В %s %d достать до клеток может только %s, а свободных линий ровно столько, сколько гостей: эта линия занята.', $word, $line + 1, $this->name($definition, $code)),
                    $code,
                );
            }
        }

        return null;
    }

    /**
     * @param  array<string, Cell>  $placed
     * @param  array<string, array<string, Cell>>  $candidates
     */
    private function subset(PuzzleDefinition $definition, array $placed, array &$candidates): ?LogicStep
    {
        $open = $this->openTokens($definition, $placed);
        for ($size = 1; $size <= min(3, count($open) - 1); $size++) {
            foreach (['row' => 'строк', 'col' => 'столбцов'] as $axis => $word) {
                foreach ($this->combinations($open, $size) as $group) {
                    $lines = [];
                    foreach ($group as $code) {
                        foreach ($candidates[$code] as $cell) {
                            $lines[$cell->{$axis}] = true;
                        }
                    }
                    if (count($lines) !== $size) {
                        continue;
                    }

                    $removed = false;
                    foreach ($open as $other) {
                        if (in_array($other, $group, true)) {
                            continue;
                        }
                        foreach ($candidates[$other] as $key => $cell) {
                            if (isset($lines[$cell->{$axis}])) {
                                unset($candidates[$other][$key]);
                                $removed = true;
                            }
                        }
                    }
                    if ($removed) {
                        return new LogicStep(
                            LogicStep::SUBSET, 3,
                            sprintf('%s могут стоять только в %d %s: они заняты этими гостями и перекрыты для остальных.', implode(' и ', array_map(fn (string $code) => $this->name($definition, $code), $group)), $size, $word),
                            $group[0],
                        );
                    }
                }
            }
        }

        return null;
    }

    /**
     * Pairwise check for statements that compare guests: a cell is crossed out when some other guest has no cell
     * that goes with it.
     *
     * @param  array<string, Cell>  $placed
     * @param  array<string, array<string, Cell>>  $candidates
     */
    private function pair(ConstraintEngine $engine, array $placed, array &$candidates): ?LogicStep
    {
        $base = $this->placements($placed);
        foreach ($candidates as $code => $cells) {
            if (isset($placed[$code])) {
                continue;
            }
            foreach ($cells as $key => $cell) {
                foreach ($candidates as $other => $otherCells) {
                    if ($other === $code || isset($placed[$other])) {
                        continue;
                    }
                    $fits = false;
                    foreach ($otherCells as $otherCell) {
                        if ($engine->isPossible([...$base, new Placement($code, $cell), new Placement($other, $otherCell)])) {
                            $fits = true;
                            break;
                        }
                    }
                    if (!$fits) {
                        unset($candidates[$code][$key]);

                        return new LogicStep(
                            LogicStep::PAIR, 3,
                            sprintf('Клетка (строка %d, столбец %d) не подходит: при ней для другого гостя не остаётся клеток.', $cell->row + 1, $cell->col + 1),
                            $code, $cell,
                        );
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, Cell>  $placed
     * @return list<string>
     */
    private function openTokens(PuzzleDefinition $definition, array $placed): array
    {
        $open = [];
        foreach ($definition->tokens as $token) {
            if (!isset($placed[$token->code]) && $token->countsForRowCol) {
                $open[] = $token->code;
            }
        }

        return $open;
    }

    /**
     * @param  list<string>  $items
     * @return list<list<string>>
     */
    private function combinations(array $items, int $size): array
    {
        if ($size === 0) {
            return [[]];
        }
        $result = [];
        foreach ($items as $index => $item) {
            foreach ($this->combinations(array_slice($items, $index + 1), $size - 1) as $rest) {
                $result[] = [$item, ...$rest];
            }
        }

        return $result;
    }

    private function name(PuzzleDefinition $definition, string $code): string
    {
        return $definition->tokenByCode($code)->name ?? $code;
    }

    /**
     * @param  array<string, Cell>  $placed
     * @return list<Placement>
     */
    private function placements(array $placed): array
    {
        $placements = [];
        foreach ($placed as $code => $cell) {
            $placements[] = new Placement($code, $cell);
        }

        return $placements;
    }

    /**
     * @param  list<LogicStep>  $steps
     * @param  array<string, Cell>  $placed
     * @param  array<string, int>  $initial
     */
    private function result(string $status, array $steps, array $placed, array $initial): LogicalSolveResult
    {
        return new LogicalSolveResult(
            $status, $steps, $this->placements($placed),
            $steps === [] ? 0 : max(array_map(fn (LogicStep $step) => $step->level, $steps)),
            $initial,
        );
    }
}
