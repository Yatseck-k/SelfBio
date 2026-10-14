<?php

declare(strict_types=1);

namespace Whodoku\Application\Puzzle;

use Whodoku\Domain\Puzzle\Generation\DifficultyMetrics;
use Whodoku\Domain\Puzzle\Generation\DifficultyRating;
use Whodoku\Domain\Puzzle\Review\LogicalSolver;
use Whodoku\Models\Puzzle;

/** Measures a stored case and gives it the difficulty rating of the current model. */
class PuzzleRatingService
{
    public function __construct(private readonly PuzzleDefinitionMapper $mapper = new PuzzleDefinitionMapper, private readonly DifficultyThresholds $thresholds = new DifficultyThresholds) {}

    /** @return array{metrics: DifficultyMetrics, difficulty: int, parts: array<string, float>, score: float} */
    public function measure(Puzzle $puzzle): array
    {
        $definition = $this->mapper->fromModel($puzzle->loadMissing(['rooms', 'walls', 'objects', 'tokens', 'clues']));
        $logic = (new LogicalSolver)->solve($definition);
        $metrics = DifficultyMetrics::measure($definition, $logic->hardestLevel, $logic->steps, $logic->initialCandidates);

        return [
            'metrics' => $metrics,
            'difficulty' => DifficultyRating::of($metrics, $this->thresholds->current()),
            'parts' => DifficultyRating::parts($metrics),
            'score' => DifficultyRating::score($metrics),
        ];
    }

    /** Stores the measurement; returns the previous and the new rating. @return array{int, int} */
    public function rate(Puzzle $puzzle): array
    {
        $result = $this->measure($puzzle);
        $before = $puzzle->difficulty;
        $puzzle->update(['difficulty' => $result['difficulty'], 'metrics' => $result['metrics']->toArray()]);

        return [$before, $result['difficulty']];
    }
}
