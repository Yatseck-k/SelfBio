<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Solver;

use Whodoku\Domain\Puzzle\Placement;

readonly class SolverResult
{
    public const ZERO = 'zero';

    public const UNIQUE = 'unique';

    public const MULTIPLE = 'multiple';

    public const TIMEOUT = 'timeout';

    /** @param list<Placement>|null $solution */
    private function __construct(
        public string $status,
        public int $visitedNodes,
        public ?array $solution,
    ) {}

    /** @param list<Placement> $solution */
    public static function unique(array $solution, int $visitedNodes): self
    {
        return new self(self::UNIQUE, $visitedNodes, $solution);
    }

    public static function zero(int $visitedNodes): self
    {
        return new self(self::ZERO, $visitedNodes, null);
    }

    public static function multiple(int $visitedNodes): self
    {
        return new self(self::MULTIPLE, $visitedNodes, null);
    }

    public static function timeout(int $visitedNodes): self
    {
        return new self(self::TIMEOUT, $visitedNodes, null);
    }

    public function isUnique(): bool
    {
        return $this->status === self::UNIQUE;
    }
}
