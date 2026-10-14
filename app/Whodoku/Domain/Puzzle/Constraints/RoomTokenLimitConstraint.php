<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Constraints;

use Whodoku\Domain\Puzzle\Enums\TokenKind;
use Whodoku\Domain\Puzzle\PuzzleDefinition;

/** No room holds more than `max` guests. */
readonly class RoomTokenLimitConstraint implements ConstraintInterface
{
    public function __construct(private int $max) {}

    /** Satisfied once every guest is placed and no room is over the limit. */
    public function isSatisfied(PuzzleDefinition $definition, PlacementState $state): bool
    {
        foreach ($definition->tokens as $token) {
            if ($token->kind === TokenKind::Character && $state->cellOf($token->code) === null) {
                return false;
            }
        }

        return $this->withinLimit($definition, $state);
    }

    public function isPossible(PuzzleDefinition $definition, PlacementState $state): bool
    {
        return $this->withinLimit($definition, $state);
    }

    private function withinLimit(PuzzleDefinition $definition, PlacementState $state): bool
    {
        $perRoom = [];
        foreach ($definition->tokens as $token) {
            $cell = $token->kind === TokenKind::Character ? $state->cellOf($token->code) : null;
            $room = $cell === null ? null : $definition->roomAt($cell);
            if ($room === null) {
                continue;
            }
            $perRoom[$room->code] = ($perRoom[$room->code] ?? 0) + 1;
            if ($perRoom[$room->code] > $this->max) {
                return false;
            }
        }

        return true;
    }
}
