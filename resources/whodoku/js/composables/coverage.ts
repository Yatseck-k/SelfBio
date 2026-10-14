import type { Cell, PlacementMap, PuzzleDetail, PuzzleToken } from '../types/puzzle';

/**
 * Sudoku-style elimination: a placed guest covers the rest of their row and column, because two guests
 * never share a line. Returns the covered cells with the names of the guests covering them. The guest
 * given in `ignoreToken` is skipped, so the selected guest can still be moved along their own lines.
 */
export function coveredCells(
    tokens: PuzzleToken[],
    placements: PlacementMap,
    rows: number,
    cols: number,
    ignoreToken: string | null = null,
): Map<string, string[]> {
    const covered = new Map<string, string[]>();
    const cover = (row: number, col: number, name: string): void => {
        const key = `${row}:${col}`;
        covered.set(key, [...(covered.get(key) ?? []), name]);
    };

    for (const token of tokens) {
        const cell: Cell | undefined = placements[token.code];
        if (!token.counts_for_row_col || cell === undefined || token.code === ignoreToken) continue;

        for (let col = 0; col < cols; col++) {
            if (col !== cell[1]) cover(cell[0], col, token.name);
        }
        for (let row = 0; row < rows; row++) {
            if (row !== cell[0]) cover(row, cell[1], token.name);
        }
    }

    return covered;
}

/**
 * Once every guest is placed, the crime scene is the single free cell that no guest's row or column covers
 * (impassable furniture and blocked cells cannot hold anything). Returns null while it is not unique.
 */
export function findEmptyCell(puzzle: PuzzleDetail, placements: PlacementMap): Cell | null {
    const { rows, cols, blocked_cells: blockedCells } = puzzle.grid;
    if (puzzle.tokens.some((token) => placements[token.code] === undefined)) {
        return null;
    }

    const covered = coveredCells(puzzle.tokens, placements, rows, cols);
    const taken = new Set(Object.values(placements).map((cell) => `${cell[0]}:${cell[1]}`));
    const closed = new Set(blockedCells.map((cell) => `${cell[0]}:${cell[1]}`));
    for (const object of puzzle.objects) {
        if (object.blocking || !object.occupiable) closed.add(`${object.cell[0]}:${object.cell[1]}`);
    }

    const free: Cell[] = [];
    for (let row = 0; row < rows; row++) {
        for (let col = 0; col < cols; col++) {
            const key = `${row}:${col}`;
            if (!covered.has(key) && !taken.has(key) && !closed.has(key)) free.push([row, col]);
        }
    }

    return free.length === 1 ? free[0] : null;
}
