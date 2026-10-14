<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

use Whodoku\Domain\Puzzle\Clue;
use Whodoku\Domain\Puzzle\Enums\ClueType;
use Whodoku\Domain\Puzzle\Placement;
use Whodoku\Domain\Puzzle\PuzzleDefinition;

/** Turns the conditions a profile asks for into clues that hold for the author's placement. */
class ConditionPlanner
{
    public function __construct(private readonly CaseTextFactory $texts = new CaseTextFactory) {}

    /**
     * @param  list<CaseCondition>  $wanted
     * @param  list<Placement>  $placements
     * @return list<Clue> conditions that are true for the placement (a wanted condition that is not true is skipped)
     */
    public function plan(PuzzleDefinition $base, array $placements, array $wanted, SeededRandom $random): array
    {
        $perRoom = [];
        foreach ($placements as $placement) {
            $room = $base->roomAt($placement->cell);
            if ($room !== null) {
                $perRoom[$room->code] = ($perRoom[$room->code] ?? 0) + 1;
            }
        }

        $clues = [];
        foreach ($wanted as $condition) {
            $clue = match ($condition) {
                CaseCondition::EveryRoom => count($perRoom) === count($base->rooms)
                    ? new Clue($this->texts->everyRoomCondition(), ClueType::EveryRoomHasToken, [])
                    : null,
                CaseCondition::RoomExactCount => $this->exactCount($base, $perRoom, $random),
                CaseCondition::RoomLimit => $perRoom === [] ? null : new Clue(
                    $this->texts->roomLimitCondition(max($perRoom)), ClueType::RoomTokenLimit, ['max' => max($perRoom)],
                ),
            };
            if ($clue !== null) {
                $clues[] = $clue;
            }
        }

        return $clues;
    }

    /** @param array<string, int> $perRoom */
    private function exactCount(PuzzleDefinition $base, array $perRoom, SeededRandom $random): ?Clue
    {
        // A room with exactly one guest is the clearest wording; any room with two or three will do otherwise.
        $single = array_keys(array_filter($perRoom, fn (int $count) => $count === 1));
        $few = array_keys(array_filter($perRoom, fn (int $count) => $count >= 2 && $count <= 3));
        $codes = $single !== [] ? $single : $few;
        if ($codes === []) {
            return null;
        }

        $code = $random->pick($codes);
        foreach ($base->rooms as $room) {
            if ($room->code === $code) {
                return new Clue(
                    $this->texts->roomCountCondition($room, $perRoom[$code]), ClueType::RoomTokenCount,
                    ['room' => $code, 'count' => $perRoom[$code]],
                );
            }
        }

        return null;
    }
}
