<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

use Whodoku\Domain\Puzzle\Cell;
use Whodoku\Domain\Puzzle\Enums\PuzzleType;
use Whodoku\Domain\Puzzle\Placement;
use Whodoku\Domain\Puzzle\PuzzleDefinition;

/**
 * Names the culprit from the finished placement. The figures cover every row and column but one, so exactly one
 * empty cell is left over (the scene: the body, the stolen item, …). Whatever the crime, the culprit is the only
 * figure standing in the room of the scene: the crimes differ in the story, not in the rule.
 */
class CrimePlanner
{
    /** @param list<Placement> $placements the author's complete placement */
    public function plan(PuzzleType $type, PuzzleDefinition $definition, array $placements, SeededRandom $random, Cell $scene): ?CrimePlan
    {
        $codes = $this->culpritCodes($type, $definition, $placements, $scene);

        return count($codes) === 1 ? new CrimePlan($type, $definition->tokenByCode($codes[0]), $scene, $definition->roomAt($scene)) : null;
    }

    /**
     * The guests standing in the room of the scene; a case is sound when exactly one is left.
     *
     * @param  list<Placement>  $placements
     * @return list<string>
     */
    public function culpritCodes(PuzzleType $type, PuzzleDefinition $definition, array $placements, Cell $scene): array
    {
        $room = $definition->roomAt($scene);
        if ($room === null) {
            return [];
        }

        $codes = [];
        foreach ($placements as $placement) {
            if ($definition->roomAt($placement->cell)?->code === $room->code) {
                $codes[] = $placement->tokenCode;
            }
        }

        return $codes;
    }
}
