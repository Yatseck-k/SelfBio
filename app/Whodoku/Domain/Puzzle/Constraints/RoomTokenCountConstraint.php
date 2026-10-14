<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Constraints;

use Whodoku\Domain\Puzzle\Enums\TokenKind;
use Whodoku\Domain\Puzzle\PuzzleDefinition;

readonly class RoomTokenCountConstraint implements ConstraintInterface
{
    /** @param list<TokenKind> $kinds */
    public function __construct(private string $roomCode, private int $count, private array $kinds) {}

    public function isSatisfied(PuzzleDefinition $definition, PlacementState $state): bool
    {
        [$placedInRoom, $unplaced] = $this->counts($definition, $state);

        return $unplaced === 0 && $placedInRoom === $this->count;
    }

    public function isPossible(PuzzleDefinition $definition, PlacementState $state): bool
    {
        [$placedInRoom, $unplaced] = $this->counts($definition, $state);

        return $placedInRoom <= $this->count && $placedInRoom + $unplaced >= $this->count;
    }

    /** @return array{int, int} */
    private function counts(PuzzleDefinition $definition, PlacementState $state): array
    {
        $placedInRoom = 0;
        $unplaced = 0;

        foreach ($definition->tokens as $token) {
            if (!in_array($token->kind, $this->kinds, true)) {
                continue;
            }

            $cell = $state->cellOf($token->code);
            if ($cell === null) {
                $unplaced++;
            } elseif ($definition->roomAt($cell)?->code === $this->roomCode) {
                $placedInRoom++;
            }
        }

        return [$placedInRoom, $unplaced];
    }
}
