<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle;

use Whodoku\Domain\Puzzle\Enums\PuzzleType;

readonly class PuzzleDefinition
{
    /**
     * @param  list<Room>  $rooms
     * @param  list<Wall>  $walls
     * @param  list<MapObject>  $objects
     * @param  list<Token>  $tokens
     * @param  list<Clue>  $clues
     * @param  list<Window>  $windows
     */
    public function __construct(
        public string $slug,
        public PuzzleType $type,
        public Grid $grid,
        public array $rooms = [],
        public array $walls = [],
        public array $objects = [],
        public array $tokens = [],
        public array $clues = [],
        public array $windows = [],
    ) {}

    /**
     * Cells right next to any window (restricted to one room when its code is given).
     *
     * @return list<Cell>
     */
    public function windowCells(?string $roomCode = null): array
    {
        $cells = [];
        foreach ($this->windows as $window) {
            foreach ($window->cells($this->grid) as $cell) {
                if ($roomCode === null || $this->roomAt($cell)?->code === $roomCode) {
                    $cells[$cell->key()] = $cell;
                }
            }
        }

        return array_values($cells);
    }

    public function roomAt(Cell $cell): ?Room
    {
        foreach ($this->rooms as $room) {
            if ($room->contains($cell)) {
                return $room;
            }
        }

        return null;
    }

    /** @return list<MapObject> */
    public function objectsAt(Cell $cell): array
    {
        $objects = [];

        foreach ($this->objects as $object) {
            if ($object->cell->equals($cell)) {
                $objects[] = $object;
            }
        }

        return $objects;
    }

    public function tokenByCode(string $code): ?Token
    {
        foreach ($this->tokens as $token) {
            if ($token->code === $code) {
                return $token;
            }
        }

        return null;
    }

    /**
     * «Рядом»: the cells share a side, lie in the same room and no wall stands between them,
     * so neither a room border nor a wall is ever stepped over.
     */
    public function areAdjacent(Cell $first, Cell $second): bool
    {
        if (!$this->grid->areOrthogonallyAdjacent($first, $second)) {
            return false;
        }

        if ($this->roomAt($first)?->code !== $this->roomAt($second)?->code) {
            return false;
        }

        return array_all($this->walls, fn ($wall) => !$wall->separates($first, $second));
    }
}
