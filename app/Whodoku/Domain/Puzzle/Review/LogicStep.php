<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Review;

use Whodoku\Domain\Puzzle\Cell;

/** One move of the logical solver: a technique a human could name, and what it concluded. */
readonly class LogicStep
{
    public const SINGLE = 'single';

    public const FORCED_LINE = 'forced_line';

    public const SUBSET = 'subset';

    public const PAIR = 'pair';

    public function __construct(
        public string $technique,
        /** 1 — read it off a statement and cross the lines; 2 — «в линии осталась одна клетка»; 3 — subsets of lines. */
        public int $level,
        public string $note,
        public ?string $tokenCode = null,
        public ?Cell $cell = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'technique' => $this->technique,
            'level' => $this->level,
            'note' => $this->note,
            'token' => $this->tokenCode,
            'cell' => $this->cell === null ? null : [$this->cell->row, $this->cell->col],
        ];
    }
}
