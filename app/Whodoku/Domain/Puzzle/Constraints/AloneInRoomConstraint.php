<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Constraints;

use Whodoku\Domain\Puzzle\PuzzleDefinition;

readonly class AloneInRoomConstraint implements ConstraintInterface
{
    public function __construct(private string $tokenCode, private string $roomCode) {}

    public function isSatisfied(PuzzleDefinition $definition, PlacementState $state): bool
    {
        return $state->cellOf($this->tokenCode) !== null && $this->isPossible($definition, $state);
    }

    public function isPossible(PuzzleDefinition $definition, PlacementState $state): bool
    {
        $targetCell = $state->cellOf($this->tokenCode);
        if ($targetCell !== null && $definition->roomAt($targetCell)?->code !== $this->roomCode) {
            return false;
        }

        foreach ($definition->tokens as $token) {
            if ($token->code === $this->tokenCode) {
                continue;
            }

            $cell = $state->cellOf($token->code);
            if ($cell !== null && $definition->roomAt($cell)?->code === $this->roomCode) {
                return false;
            }
        }

        return true;
    }
}
