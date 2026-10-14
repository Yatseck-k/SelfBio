<?php

declare(strict_types=1);

namespace Whodoku\Application\Puzzle;

use Whodoku\Domain\Puzzle\Cell;
use Whodoku\Domain\Puzzle\Clue;
use Whodoku\Domain\Puzzle\Enums\ClueType;
use Whodoku\Domain\Puzzle\Enums\PuzzleType;
use Whodoku\Domain\Puzzle\Enums\TokenKind;
use Whodoku\Domain\Puzzle\Grid;
use Whodoku\Domain\Puzzle\MapObject;
use Whodoku\Domain\Puzzle\PuzzleDefinition;
use Whodoku\Domain\Puzzle\Room;
use Whodoku\Domain\Puzzle\Token;
use Whodoku\Domain\Puzzle\Wall;
use Whodoku\Domain\Puzzle\Window;
use Whodoku\Models\Puzzle;

class PuzzleDefinitionMapper
{
    public function fromModel(Puzzle $puzzle): PuzzleDefinition
    {
        $puzzle->loadMissing(['rooms', 'walls', 'objects', 'tokens', 'clues']);

        return new PuzzleDefinition(
            $puzzle->slug,
            $this->puzzleType($puzzle->type),
            new Grid($puzzle->rows, $puzzle->cols, array_map($this->cell(...), $puzzle->blocked_cells ?? [])),
            $puzzle->rooms->map(fn ($room) => new Room(
                $room->code, $room->name, array_map($this->cell(...), $room->cells),
            ))->all(),
            $puzzle->walls->map(fn ($wall) => new Wall(
                new Cell($wall->row_from, $wall->col_from), new Cell($wall->row_to, $wall->col_to),
            ))->all(),
            $puzzle->objects->map(fn ($object) => new MapObject(
                $object->code, $object->type, $object->name, new Cell($object->row, $object->col),
                $object->blocking, $object->occupiable, $object->metadata ?? [],
            ))->all(),
            $puzzle->tokens->map(fn ($token) => new Token(
                $token->code, $token->name, $this->tokenKind($token->kind), $token->counts_for_row_col,
                $token->description, $token->avatar, $token->sort_order, $token->metadata ?? [],
            ))->all(),
            $puzzle->clues->map(fn ($clue) => new Clue(
                $clue->text, $this->clueType($clue->constraint_type), $clue->constraint, $clue->sort_order,
            ))->all(),
            array_map($this->window(...), $puzzle->windows ?? []),
        );
    }

    private function cell(mixed $value): Cell
    {
        if (!is_array($value) || !array_is_list($value) || count($value) !== 2 || !is_int($value[0]) || !is_int($value[1])) {
            throw new InvalidPuzzleDataException('Cell coordinates must be an integer pair.');
        }

        return new Cell($value[0], $value[1]);
    }

    private function window(mixed $value): Window
    {
        if (!is_array($value) || !isset($value['cell'], $value['side']) || !is_string($value['side'])) {
            throw new InvalidPuzzleDataException('A window needs a cell and a side.');
        }

        return new Window($this->cell($value['cell']), $value['side']);
    }

    private function puzzleType(string $value): PuzzleType
    {
        return PuzzleType::tryFrom($value) ?? throw new InvalidPuzzleDataException('Unknown puzzle type.');
    }

    private function tokenKind(string $value): TokenKind
    {
        return TokenKind::tryFrom($value) ?? throw new InvalidPuzzleDataException('Unknown token kind.');
    }

    private function clueType(string $value): ClueType
    {
        return ClueType::tryFrom($value) ?? throw new InvalidPuzzleDataException('Unknown clue type.');
    }
}
