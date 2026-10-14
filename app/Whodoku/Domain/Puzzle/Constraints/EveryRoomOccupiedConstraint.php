<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Constraints;

use Whodoku\Domain\Puzzle\Enums\TokenKind;
use Whodoku\Domain\Puzzle\PuzzleDefinition;

/** Every room of the map holds at least one guest. */
readonly class EveryRoomOccupiedConstraint implements ConstraintInterface
{
    public function isSatisfied(PuzzleDefinition $definition, PlacementState $state): bool
    {
        [$empty, $unplaced] = $this->counts($definition, $state);

        return $unplaced === 0 && $empty === 0;
    }

    /** Possible while the guests still to be placed are enough to fill the rooms that are empty now. */
    public function isPossible(PuzzleDefinition $definition, PlacementState $state): bool
    {
        [$empty, $unplaced] = $this->counts($definition, $state);

        return $empty <= $unplaced;
    }

    /** @return array{int, int} rooms without a placed guest, guests not placed yet */
    private function counts(PuzzleDefinition $definition, PlacementState $state): array
    {
        $occupied = [];
        $unplaced = 0;

        foreach ($definition->tokens as $token) {
            if ($token->kind !== TokenKind::Character) {
                continue;
            }
            $cell = $state->cellOf($token->code);
            if ($cell === null) {
                $unplaced++;
            } elseif (($room = $definition->roomAt($cell)) !== null) {
                $occupied[$room->code] = true;
            }
        }

        return [count($definition->rooms) - count($occupied), $unplaced];
    }
}
