<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

use Whodoku\Domain\Puzzle\Placement;
use Whodoku\Domain\Puzzle\PuzzleDefinition;

readonly class GeneratedPuzzle
{
    /**
     * @param  list<Placement>  $authorPlacements
     * @param  list<string>  $hints
     * @param  array<string, mixed>  $ending
     */
    public function __construct(
        public PuzzleDefinition $definition,
        public array $authorPlacements,
        public string $title,
        public string $description,
        public string $story,
        public array $hints,
        public array $ending,
        /** @var array{name: string, role: string, male: bool, avatar: string, statement: string}|array{} */
        public array $victim = [],
    ) {}
}
