<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle;

/**
 * A window stands on a thick line: the edge of the map or the border between two rooms. It is stored as a side
 * of one cell; the cell across that side (when it exists) is just as close to the window.
 */
readonly class Window
{
    public const SIDES = ['north', 'south', 'west', 'east'];

    public function __construct(public Cell $cell, public string $side) {}

    public function key(): string
    {
        return "{$this->cell->key()}:{$this->side}";
    }

    /** The cell on the other side of the window, which may lie outside the grid. */
    public function across(): Cell
    {
        return match ($this->side) {
            'north' => new Cell($this->cell->row - 1, $this->cell->col),
            'south' => new Cell($this->cell->row + 1, $this->cell->col),
            'west' => new Cell($this->cell->row, $this->cell->col - 1),
            default => new Cell($this->cell->row, $this->cell->col + 1),
        };
    }

    /**
     * The cells right next to the window: its own cell and the one across the line, when that is on the map.
     *
     * @return list<Cell>
     */
    public function cells(Grid $grid): array
    {
        $across = $this->across();

        return $grid->contains($across) ? [$this->cell, $across] : [$this->cell];
    }
}
