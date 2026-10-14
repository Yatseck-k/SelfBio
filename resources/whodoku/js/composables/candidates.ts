import type { Cell, PlacementMap, PuzzleDetail } from '../types/puzzle';
import { coveredCells } from './coverage';

const key = (cell: Cell): string => `${cell[0]}:${cell[1]}`;

function roomCode(puzzle: PuzzleDetail, cell: Cell): string | null {
    return puzzle.rooms.find((room) => room.cells.some((candidate) => key(candidate) === key(cell)))?.code ?? null;
}

function wallBetween(puzzle: PuzzleDetail, first: Cell, second: Cell): boolean {
    return puzzle.walls.some((wall) => (
        (key(wall.from) === key(first) && key(wall.to) === key(second))
        || (key(wall.from) === key(second) && key(wall.to) === key(first))
    ));
}

/** «Рядом»: a shared side, the same room and no wall between the cells. Mirrors PuzzleDefinition::areAdjacent. */
export function areAdjacent(puzzle: PuzzleDetail, first: Cell, second: Cell): boolean {
    if (Math.abs(first[0] - second[0]) + Math.abs(first[1] - second[1]) !== 1) {
        return false;
    }

    return roomCode(puzzle, first) === roomCode(puzzle, second) && !wallBetween(puzzle, first, second);
}

const OFFSETS = { north: [-1, 0], south: [1, 0], west: [0, -1], east: [0, 1] } as const;

/** Cells right next to a window, optionally only those in one room. Mirrors PuzzleDefinition::windowCells. */
function windowCells(puzzle: PuzzleDetail, room: string | null): Set<string> {
    const cells = new Set<string>();
    for (const window of puzzle.windows ?? []) {
        const [dRow, dCol] = OFFSETS[window.side];
        const across: Cell = [window.cell[0] + dRow, window.cell[1] + dCol];
        const inside = across[0] >= 0 && across[0] < puzzle.grid.rows && across[1] >= 0 && across[1] < puzzle.grid.cols;
        for (const cell of inside ? [window.cell, across] : [window.cell]) {
            if (room === null || roomCode(puzzle, cell) === room) cells.add(key(cell));
        }
    }

    return cells;
}

function isCorner(puzzle: PuzzleDetail, cell: Cell): boolean {
    const code = roomCode(puzzle, cell);
    const same = (row: number, col: number): boolean => roomCode(puzzle, [row, col]) === code;
    const north = !same(cell[0] - 1, cell[1]);
    const south = !same(cell[0] + 1, cell[1]);
    const west = !same(cell[0], cell[1] - 1);
    const east = !same(cell[0], cell[1] + 1);

    return (north && west) || (north && east) || (south && west) || (south && east);
}

/**
 * Where a guest can still stand: not on a blocked cell or impassable furniture, not on a line of another placed
 * guest, and in line with what the guest said. It never looks at the author's solution, so it only does what a
 * careful player can do on paper. Statements that compare guests with each other are not narrowed.
 */
export function candidateCells(puzzle: PuzzleDetail, tokenCode: string, placements: PlacementMap): Cell[] {
    const covered = coveredCells(puzzle.tokens, placements, puzzle.grid.rows, puzzle.grid.cols, tokenCode);
    const taken = new Set(Object.entries(placements).filter(([code]) => code !== tokenCode).map(([, cell]) => key(cell)));
    const closed = new Set(puzzle.grid.blocked_cells.map(key));
    for (const object of puzzle.objects) {
        if (object.blocking || !object.occupiable) closed.add(key(object.cell));
    }
    const objects = new Map(puzzle.objects.map((object) => [object.code, object]));
    const statements = puzzle.clues.filter((clue) => clue.constraint.token === tokenCode);
    // «Я была одна в комнате…» said by someone else closes that room to everybody else.
    const keptEmpty = new Set(puzzle.clues
        .filter((clue) => clue.constraint_type === 'TOKEN_ALONE_IN_ROOM' && clue.constraint.token !== tokenCode)
        .map((clue) => clue.constraint.room));

    const cells: Cell[] = [];
    for (let row = 0; row < puzzle.grid.rows; row++) {
        for (let col = 0; col < puzzle.grid.cols; col++) {
            const cell: Cell = [row, col];
            if (closed.has(key(cell)) || taken.has(key(cell)) || covered.has(key(cell))) continue;
            if (keptEmpty.has(roomCode(puzzle, cell))) continue;
            if (statements.every((clue) => satisfies(puzzle, clue.constraint_type, clue.constraint, cell, objects, placements, tokenCode))) {
                cells.push(cell);
            }
        }
    }

    return cells;
}

function satisfies(
    puzzle: PuzzleDetail,
    type: string,
    constraint: Record<string, unknown>,
    cell: Cell,
    objects: Map<string, PuzzleDetail['objects'][number]>,
    placements: PlacementMap,
    tokenCode: string,
): boolean {
    const room = typeof constraint.room === 'string' ? constraint.room : null;
    const object = typeof constraint.object === 'string' ? objects.get(constraint.object) : undefined;

    switch (type) {
        case 'TOKEN_IN_ROOM':
            return roomCode(puzzle, cell) === room;
        case 'TOKEN_NOT_IN_ROOM':
            return roomCode(puzzle, cell) !== room;
        case 'TOKEN_ALONE_IN_ROOM':
            return roomCode(puzzle, cell) === room
                && Object.entries(placements).every(([code, other]) => code === tokenCode || roomCode(puzzle, other) !== room);
        case 'TOKEN_IN_ROOM_CORNER':
            return roomCode(puzzle, cell) === room && isCorner(puzzle, cell);
        case 'TOKEN_ON_OBJECT':
            return object !== undefined && key(object.cell) === key(cell);
        case 'TOKEN_ADJACENT_OBJECT':
            return object !== undefined && areAdjacent(puzzle, cell, object.cell);
        case 'TOKEN_NOT_ADJACENT_OBJECT':
            return object !== undefined && !areAdjacent(puzzle, cell, object.cell);
        case 'TOKEN_NORTH_OF_OBJECT':
            return object !== undefined && cell[0] < object.cell[0];
        case 'TOKEN_SOUTH_OF_OBJECT':
            return object !== undefined && cell[0] > object.cell[0];
        case 'TOKEN_WEST_OF_OBJECT':
            return object !== undefined && cell[1] < object.cell[1];
        case 'TOKEN_EAST_OF_OBJECT':
            return object !== undefined && cell[1] > object.cell[1];
        case 'TOKEN_NEAR_WINDOW':
            return windowCells(puzzle, room).has(key(cell));
        default:
            return true;
    }
}
