<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

/**
 * A condition of the whole case that a release profile may ask for. It is added only when it is true for the
 * author's placement; with it in the case the generator may weaken the statements further, so the case is solved
 * with the house in mind rather than by reading single statements.
 */
enum CaseCondition: string
{
    /** «В каждой комнате был хотя бы один гость». */
    case EveryRoom = 'every_room';

    /** «В комнате … был ровно один гость». */
    case RoomExactCount = 'room_exact_count';

    /** «Ни в одной комнате не было больше N гостей». */
    case RoomLimit = 'room_limit';
}
