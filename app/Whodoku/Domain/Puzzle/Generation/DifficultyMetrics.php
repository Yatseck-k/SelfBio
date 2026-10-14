<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

use Whodoku\Domain\Puzzle\PuzzleDefinition;
use Whodoku\Domain\Puzzle\Review\LogicStep;

/**
 * What makes a case hard for a person, measured on the case itself (not on the size of the map alone):
 * the logic the bot needed, how clear the statements are, how big the case is and how many conditions of the
 * whole case have to be kept in mind. {@see DifficultyRating} turns it into a rating from 1 to 5.
 */
readonly class DifficultyMetrics
{
    public function __construct(
        public int $tokens,
        public int $area,
        /** The hardest technique of the bot: 1 — cross the lines, 2 — a forced line, 3 — subsets and pairs; 0 — none. */
        public int $hardestLevel,
        /** Moves of the bot, and how many of them were harder than crossing lines. */
        public int $steps,
        public int $deepSteps,
        /** Share of guests whose statement names the exact cell («on the object»). */
        public float $directShare,
        /** Mean strength of the statements, 1 (weak) to 3 (exact). */
        public float $strength,
        /** Mean share of the map where a guest could stand knowing only their statement, 0 to 1. */
        public float $ambiguity,
        /** Conditions of the whole case (every room has a guest, a limit per room, a room with an exact count). */
        public int $conditions,
    ) {}

    /**
     * @param  list<LogicStep>  $steps  the moves of the logical solver
     * @param  array<string, int>  $initialCandidates  cells each guest could stand on before the first move
     */
    public static function measure(PuzzleDefinition $definition, int $hardestLevel, array $steps, array $initialCandidates): self
    {
        $statements = array_values(array_filter($definition->clues, fn ($clue) => !$clue->type->isCondition()));
        $strengths = array_map(fn ($clue) => ClueStrength::of($clue->type), $statements);
        $area = max(1, $definition->grid->rows * $definition->grid->cols);
        $initial = $initialCandidates === [] ? [$area] : array_values($initialCandidates);

        return new self(
            count($definition->tokens),
            $area,
            $hardestLevel,
            count($steps),
            count(array_filter($steps, fn (LogicStep $step) => $step->level >= 2)),
            $strengths === [] ? 0.0 : round(count(array_filter($strengths, fn (int $level) => $level === ClueStrength::EXACT)) / count($strengths), 3),
            $strengths === [] ? (float) ClueStrength::WEAK : round(array_sum($strengths) / count($strengths), 3),
            round(array_sum($initial) / count($initial) / $area, 3),
            count($definition->clues) - count($statements),
        );
    }

    /** @return array<string, int|float> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['tokens'] ?? 0), (int) ($data['area'] ?? 1), (int) ($data['hardestLevel'] ?? 0),
            (int) ($data['steps'] ?? 0), (int) ($data['deepSteps'] ?? 0), (float) ($data['directShare'] ?? 0),
            (float) ($data['strength'] ?? 1), (float) ($data['ambiguity'] ?? 0), (int) ($data['conditions'] ?? 0),
        );
    }
}
