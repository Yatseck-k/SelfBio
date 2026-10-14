<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Review;

/** The verdict of the bot: the checks it ran and how the case was solved by logic alone. */
readonly class ReviewReport
{
    /**
     * @param  list<ReviewCheck>  $checks
     * @param  list<LogicStep>  $steps
     */
    public function __construct(
        public array $checks,
        public array $steps = [],
        /** Hardest technique used: 0 — none, 1 — statements and crossing lines, 2 — forced lines, 3 — subsets. */
        public int $hardestLevel = 0,
        /** @var array<string, int> cells each guest could stand on before the first move of the logical solver */
        public array $initialCandidates = [],
    ) {}

    /** The case may be published when no check of error severity failed. */
    public function passed(): bool
    {
        return $this->failures() === [];
    }

    /** @return list<ReviewCheck> */
    public function failures(): array
    {
        return array_values(array_filter($this->checks, fn (ReviewCheck $check) => !$check->passed && $check->severity === ReviewCheck::ERROR));
    }

    /** @return list<ReviewCheck> */
    public function warnings(): array
    {
        return array_values(array_filter($this->checks, fn (ReviewCheck $check) => !$check->passed && $check->severity === ReviewCheck::WARNING));
    }

    /** @param list<ReviewCheck> $checks */
    public function withChecks(array $checks): self
    {
        return new self([...$this->checks, ...$checks], $this->steps, $this->hardestLevel, $this->initialCandidates);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'passed' => $this->passed(),
            'hardest_level' => $this->hardestLevel,
            'steps' => count($this->steps),
            'checks' => array_map(fn (ReviewCheck $check) => $check->toArray(), $this->checks),
            'logic' => array_map(fn (LogicStep $step) => $step->toArray(), $this->steps),
        ];
    }
}
