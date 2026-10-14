<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Constraints;

use Whodoku\Domain\Puzzle\Clue;
use Whodoku\Domain\Puzzle\Enums\ClueType;
use Whodoku\Domain\Puzzle\Enums\TokenKind;
use Whodoku\Domain\Puzzle\MapObject;
use Whodoku\Domain\Puzzle\PuzzleDefinition;
use Whodoku\Domain\Puzzle\Room;

class ConstraintFactory
{
    /** @return list<ConstraintInterface> */
    public function compile(PuzzleDefinition $definition): array
    {
        $constraints = [];

        foreach ($definition->clues as $index => $clue) {
            $constraints[] = $this->create($definition, $clue, $index);
        }

        return $constraints;
    }

    public function create(PuzzleDefinition $definition, Clue $clue, int $index = 0): ConstraintInterface
    {
        return match ($clue->type) {
            ClueType::TokenInRoom => new RoomMembershipConstraint(
                $this->token($definition, $clue, $index), $this->room($definition, $clue, $index)->code, true,
            ),
            ClueType::TokenNotInRoom => new RoomMembershipConstraint(
                $this->token($definition, $clue, $index), $this->room($definition, $clue, $index)->code, false,
            ),
            ClueType::TokenAdjacentToken => new AdjacencyConstraint(
                $this->token($definition, $clue, $index), $this->token($definition, $clue, $index, 'target_token'), null, true,
            ),
            ClueType::TokenNotAdjacentToken => new AdjacencyConstraint(
                $this->token($definition, $clue, $index), $this->token($definition, $clue, $index, 'target_token'), null, false,
            ),
            ClueType::TokenAdjacentObject => new AdjacencyConstraint(
                $this->token($definition, $clue, $index), null, $this->object($definition, $clue, $index)->cell, true,
            ),
            ClueType::TokenNotAdjacentObject => new AdjacencyConstraint(
                $this->token($definition, $clue, $index), null, $this->object($definition, $clue, $index)->cell, false,
            ),
            ClueType::TokenNorthOfToken => $this->directionToToken($definition, $clue, $index, 'north'),
            ClueType::TokenSouthOfToken => $this->directionToToken($definition, $clue, $index, 'south'),
            ClueType::TokenWestOfToken => $this->directionToToken($definition, $clue, $index, 'west'),
            ClueType::TokenEastOfToken => $this->directionToToken($definition, $clue, $index, 'east'),
            ClueType::TokenNorthOfObject => $this->directionToObject($definition, $clue, $index, 'north'),
            ClueType::TokenSouthOfObject => $this->directionToObject($definition, $clue, $index, 'south'),
            ClueType::TokenWestOfObject => $this->directionToObject($definition, $clue, $index, 'west'),
            ClueType::TokenEastOfObject => $this->directionToObject($definition, $clue, $index, 'east'),
            ClueType::TokenRowsFromToken => new DistanceConstraint(
                $this->token($definition, $clue, $index),
                $this->token($definition, $clue, $index, 'target_token'),
                $this->direction($clue, $index, ['north', 'south']),
                $this->number($clue, $index, 'distance', 1),
            ),
            ClueType::TokenColsFromToken => new DistanceConstraint(
                $this->token($definition, $clue, $index),
                $this->token($definition, $clue, $index, 'target_token'),
                $this->direction($clue, $index, ['west', 'east']),
                $this->number($clue, $index, 'distance', 1),
            ),
            ClueType::TokenAloneInRoom => new AloneInRoomConstraint(
                $this->token($definition, $clue, $index), $this->room($definition, $clue, $index)->code,
            ),
            ClueType::RoomTokenCount => new RoomTokenCountConstraint(
                $this->room($definition, $clue, $index)->code,
                $this->number($clue, $index, 'count', 0),
                $this->kinds($clue, $index),
            ),
            ClueType::EveryRoomHasToken => new EveryRoomOccupiedConstraint,
            ClueType::RoomTokenLimit => new RoomTokenLimitConstraint($this->number($clue, $index, 'max', 1)),
            ClueType::TokenOnObject => new OnObjectConstraint(
                $this->token($definition, $clue, $index), $this->object($definition, $clue, $index),
            ),
            ClueType::TokenInRoomCorner => new RoomCornerConstraint(
                $this->token($definition, $clue, $index), $this->room($definition, $clue, $index),
            ),
            ClueType::SameRoom => new RoomRelationConstraint(
                $this->token($definition, $clue, $index), $this->token($definition, $clue, $index, 'target_token'), true,
            ),
            ClueType::TokenNearWindow => new WindowProximityConstraint(
                $this->token($definition, $clue, $index), $this->optionalRoom($definition, $clue, $index),
            ),
            ClueType::DifferentRoom => new RoomRelationConstraint(
                $this->token($definition, $clue, $index), $this->token($definition, $clue, $index, 'target_token'), false,
            ),
        };
    }

    private function directionToToken(PuzzleDefinition $definition, Clue $clue, int $index, string $direction): DirectionConstraint
    {
        return new DirectionConstraint(
            $this->token($definition, $clue, $index),
            $this->token($definition, $clue, $index, 'target_token'),
            null,
            $direction,
        );
    }

    private function directionToObject(PuzzleDefinition $definition, Clue $clue, int $index, string $direction): DirectionConstraint
    {
        return new DirectionConstraint(
            $this->token($definition, $clue, $index),
            null,
            $this->object($definition, $clue, $index)->cell,
            $direction,
        );
    }

    private function token(PuzzleDefinition $definition, Clue $clue, int $index, string $key = 'token'): string
    {
        $code = $this->requiredString($clue, $index, $key);
        if ($definition->tokenByCode($code) === null) {
            throw new InvalidConstraintException($this->path($index, $key), 'unknown token');
        }

        return $code;
    }

    private function room(PuzzleDefinition $definition, Clue $clue, int $index): Room
    {
        $code = $this->requiredString($clue, $index, 'room');
        foreach ($definition->rooms as $room) {
            if ($room->code === $code) {
                return $room;
            }
        }

        throw new InvalidConstraintException($this->path($index, 'room'), 'unknown room');
    }

    /** The room code of an optional `room` parameter: «near a window» may be narrowed to one room. */
    private function optionalRoom(PuzzleDefinition $definition, Clue $clue, int $index): ?string
    {
        return array_key_exists('room', $clue->parameters) ? $this->room($definition, $clue, $index)->code : null;
    }

    private function object(PuzzleDefinition $definition, Clue $clue, int $index): MapObject
    {
        $code = $this->requiredString($clue, $index, 'object');
        foreach ($definition->objects as $object) {
            if ($object->code === $code) {
                return $object;
            }
        }

        throw new InvalidConstraintException($this->path($index, 'object'), 'unknown object');
    }

    private function requiredString(Clue $clue, int $index, string $key): string
    {
        $value = $clue->parameters[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidConstraintException($this->path($index, $key), 'non-empty string required');
        }

        return $value;
    }

    /** @param list<string> $allowed */
    private function direction(Clue $clue, int $index, array $allowed): string
    {
        $value = $this->requiredString($clue, $index, 'direction');
        if (!in_array($value, $allowed, true)) {
            throw new InvalidConstraintException($this->path($index, 'direction'), 'invalid direction');
        }

        return $value;
    }

    private function number(Clue $clue, int $index, string $key, int $minimum): int
    {
        $value = $clue->parameters[$key] ?? null;
        if (!is_int($value) || $value < $minimum) {
            throw new InvalidConstraintException($this->path($index, $key), "integer >= $minimum required");
        }

        return $value;
    }

    /** @return list<TokenKind> */
    private function kinds(Clue $clue, int $index): array
    {
        if (!array_key_exists('kinds', $clue->parameters)) {
            return [TokenKind::Character];
        }

        $values = $clue->parameters['kinds'];
        if (!is_array($values) || !array_is_list($values) || $values === []) {
            throw new InvalidConstraintException($this->path($index, 'kinds'), 'non-empty list required');
        }

        $kinds = [];
        foreach ($values as $position => $value) {
            $kind = is_string($value) ? TokenKind::tryFrom($value) : null;
            if ($kind === null || in_array($kind, $kinds, true)) {
                throw new InvalidConstraintException($this->path($index, "kinds.$position"), 'invalid or repeated kind');
            }
            $kinds[] = $kind;
        }

        return $kinds;
    }

    private function path(int $index, string $key): string
    {
        return "clues.$index.parameters.$key";
    }
}
