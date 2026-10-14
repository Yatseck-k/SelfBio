<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Review;

use Whodoku\Domain\Puzzle\Placement;

readonly class LogicalSolveResult
{
    public const SOLVED = 'solved';

    /** No technique applies, so the case could only be finished by guessing. */
    public const STUCK = 'stuck';

    /** Some guest has no cell left: the statements contradict each other. */
    public const INCONSISTENT = 'inconsistent';

    /**
     * @param  list<LogicStep>  $steps
     * @param  list<Placement>  $placements
     */
    public function __construct(
        public string $status,
        public array $steps,
        public array $placements,
        public int $hardestLevel,
        /** @var array<string, int> how many cells each guest could stand on before the first move */
        public array $initialCandidates = [],
    ) {}
}
