<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

use Whodoku\Domain\Puzzle\Enums\ClueType;

/**
 * How much a single statement narrows the guest's place: 1 leaves a whole room or half of the map, 2 a handful
 * of cells, 3 exactly one cell. Weak statements make a case harder, because the placement has to be deduced
 * from the sudoku rule (one guest per row and column) and from the other statements.
 */
final class ClueStrength
{
    public const int WEAK = 1;

    public const int MEDIUM = 2;

    public const int EXACT = 3;

    public static function of(ClueType $type): int
    {
        return match ($type) {
            ClueType::TokenOnObject => self::EXACT,
            ClueType::TokenAdjacentObject, ClueType::TokenAloneInRoom, ClueType::TokenInRoomCorner, ClueType::TokenNearWindow => self::MEDIUM,
            default => self::WEAK,
        };
    }
}
