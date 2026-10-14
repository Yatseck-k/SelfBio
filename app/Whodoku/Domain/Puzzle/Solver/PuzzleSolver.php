<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Solver;

use Whodoku\Domain\Puzzle\Cell;
use Whodoku\Domain\Puzzle\Constraints\ConstraintEngine;
use Whodoku\Domain\Puzzle\Placement;
use Whodoku\Domain\Puzzle\PuzzleDefinition;
use Whodoku\Domain\Puzzle\Token;
use Whodoku\Domain\Puzzle\Validation\PuzzleStructureValidator;

class PuzzleSolver
{
    private int $visitedNodes = 0;

    private bool $timedOut = false;

    /** @var list<list<Placement>> */
    private array $solutions = [];

    private int $startedAt;

    private ConstraintEngine $engine;

    public function solve(PuzzleDefinition $definition, ?SolverLimits $limits = null): SolverResult
    {
        $issues = (new PuzzleStructureValidator)->validate($definition);
        if ($issues !== []) {
            throw new InvalidPuzzleDefinitionException($issues);
        }

        $this->visitedNodes = 0;
        $this->timedOut = false;
        $this->solutions = [];
        $this->startedAt = hrtime(true);
        $this->engine = new ConstraintEngine($definition);
        $this->search($definition, $limits ?? new SolverLimits, []);

        if ($this->timedOut) {
            return SolverResult::timeout($this->visitedNodes);
        }

        return match (count($this->solutions)) {
            0 => SolverResult::zero($this->visitedNodes),
            1 => SolverResult::unique($this->solutions[0], $this->visitedNodes),
            default => SolverResult::multiple($this->visitedNodes),
        };
    }

    /** @param list<Placement> $placements */
    private function search(PuzzleDefinition $definition, SolverLimits $limits, array $placements): void
    {
        if ($this->timedOut || count($this->solutions) >= 2) {
            return;
        }

        if ($this->timeLimitReached($limits)) {
            $this->timedOut = true;

            return;
        }

        $selection = $this->selectToken($definition, $placements);
        if ($selection === null) {
            if ($this->engine->isSatisfied($placements)) {
                $this->solutions[] = $placements;
            }

            return;
        }

        [$token, $candidates] = $selection;
        foreach ($candidates as $cell) {
            if ($this->visitedNodes >= $limits->maxNodes || $this->timeLimitReached($limits)) {
                $this->timedOut = true;

                return;
            }

            $this->visitedNodes++;
            $next = [...$placements, new Placement($token->code, $cell)];
            if ($this->engine->isPossible($next)) {
                $this->search($definition, $limits, $next);
            }

            if ($this->timedOut || count($this->solutions) >= 2) {
                return;
            }
        }
    }

    /** @param list<Placement> $placements
     * @return array{Token, list<Cell>}|null
     */
    private function selectToken(PuzzleDefinition $definition, array $placements): ?array
    {
        $placed = [];
        foreach ($placements as $placement) {
            $placed[$placement->tokenCode] = true;
        }

        $best = null;
        foreach ($definition->tokens as $token) {
            if (isset($placed[$token->code])) {
                continue;
            }

            $candidates = $this->candidatesFor($definition, $placements, $token);
            if ($best === null || count($candidates) < count($best[1])) {
                $best = [$token, $candidates];
            }
        }

        return $best;
    }

    /** @param list<Placement> $placements
     * @return list<Cell>
     */
    private function candidatesFor(PuzzleDefinition $definition, array $placements, Token $token): array
    {
        $candidates = [];
        for ($row = 0; $row < $definition->grid->rows; $row++) {
            for ($col = 0; $col < $definition->grid->cols; $col++) {
                $cell = new Cell($row, $col);
                if ($definition->grid->isBlocked($cell) || !$this->isOccupiable($definition, $cell)) {
                    continue;
                }

                $candidate = [...$placements, new Placement($token->code, $cell)];
                if ($this->engine->isPossible($candidate)) {
                    $candidates[] = $cell;
                }
            }
        }

        return $candidates;
    }

    private function isOccupiable(PuzzleDefinition $definition, Cell $cell): bool
    {
        return array_all($definition->objectsAt($cell), fn ($object) => !($object->blocking || !$object->occupiable));
    }

    private function timeLimitReached(SolverLimits $limits): bool
    {
        return (hrtime(true) - $this->startedAt) >= $limits->maxMilliseconds * 1_000_000;
    }
}
