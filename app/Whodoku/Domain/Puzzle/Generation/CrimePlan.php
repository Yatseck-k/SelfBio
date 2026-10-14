<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

use Whodoku\Domain\Puzzle\Cell;
use Whodoku\Domain\Puzzle\Enums\PuzzleType;
use Whodoku\Domain\Puzzle\Room;
use Whodoku\Domain\Puzzle\Token;

/** The crime of a case and its culprit: the only figure in the room of the scene once everybody is placed. */
readonly class CrimePlan
{
    public function __construct(
        public PuzzleType $type,
        public Token $culprit,
        /** The empty cell left over by the placement: the only cell not covered by any figure's row or column. */
        public Cell $scene,
        /** The room of the scene cell. */
        public ?Room $room = null,
    ) {}
}
