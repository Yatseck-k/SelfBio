<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Enums;

enum ClueType: string
{
    case TokenInRoom = 'TOKEN_IN_ROOM';
    case TokenNotInRoom = 'TOKEN_NOT_IN_ROOM';
    case TokenAdjacentToken = 'TOKEN_ADJACENT_TOKEN';
    case TokenAdjacentObject = 'TOKEN_ADJACENT_OBJECT';
    case TokenNotAdjacentToken = 'TOKEN_NOT_ADJACENT_TOKEN';
    case TokenNotAdjacentObject = 'TOKEN_NOT_ADJACENT_OBJECT';
    case TokenNorthOfToken = 'TOKEN_NORTH_OF_TOKEN';
    case TokenSouthOfToken = 'TOKEN_SOUTH_OF_TOKEN';
    case TokenWestOfToken = 'TOKEN_WEST_OF_TOKEN';
    case TokenEastOfToken = 'TOKEN_EAST_OF_TOKEN';
    case TokenNorthOfObject = 'TOKEN_NORTH_OF_OBJECT';
    case TokenSouthOfObject = 'TOKEN_SOUTH_OF_OBJECT';
    case TokenWestOfObject = 'TOKEN_WEST_OF_OBJECT';
    case TokenEastOfObject = 'TOKEN_EAST_OF_OBJECT';
    case TokenRowsFromToken = 'TOKEN_ROWS_FROM_TOKEN';
    case TokenColsFromToken = 'TOKEN_COLS_FROM_TOKEN';
    case TokenAloneInRoom = 'TOKEN_ALONE_IN_ROOM';
    case RoomTokenCount = 'ROOM_TOKEN_COUNT';
    case TokenOnObject = 'TOKEN_ON_OBJECT';
    case TokenInRoomCorner = 'TOKEN_IN_ROOM_CORNER';
    case SameRoom = 'SAME_ROOM';
    case DifferentRoom = 'DIFFERENT_ROOM';
    case TokenNearWindow = 'TOKEN_NEAR_WINDOW';
    /** Case-wide condition: every room has at least one guest. */
    case EveryRoomHasToken = 'EVERY_ROOM_HAS_TOKEN';
    /** Case-wide condition: no room has more than `max` guests. */
    case RoomTokenLimit = 'ROOM_TOKEN_LIMIT';

    /**
     * Conditions describe the house, not a guest: they have no speaker and are listed apart from the statements.
     */
    public function isCondition(): bool
    {
        return in_array($this, [self::RoomTokenCount, self::EveryRoomHasToken, self::RoomTokenLimit], true);
    }
}
