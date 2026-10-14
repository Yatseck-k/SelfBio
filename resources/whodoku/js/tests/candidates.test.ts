import { describe, expect, it } from 'vitest';
import { areAdjacent, candidateCells } from '../composables/candidates';
import { tutorialPuzzle } from '../composables/tutorialCase';
import type { Cell, PuzzleDetail } from '../types/puzzle';

const keys = (cells: Cell[]): string[] => cells.map((cell) => `${cell[0]}:${cell[1]}`).sort();

describe('candidateCells', () => {
    it('narrows each guest of the worked example by their own statement', () => {
        expect(keys(candidateCells(tutorialPuzzle, 'anna', {}))).toEqual(['1:3']);
        expect(keys(candidateCells(tutorialPuzzle, 'vera', {}))).toEqual(['3:1']);
        expect(keys(candidateCells(tutorialPuzzle, 'boris', {}))).toEqual(['0:2', '1:3']);
    });

    it('crosses the lines of the guests who are already placed', () => {
        expect(keys(candidateCells(tutorialPuzzle, 'boris', { anna: [1, 3] }))).toEqual(['0:2']);
        // The chosen guest's own lines stay open, so they can still be moved.
        expect(keys(candidateCells(tutorialPuzzle, 'boris', { boris: [0, 2], anna: [1, 3] }))).toEqual(['0:2']);
    });

    it('never offers a cell with impassable furniture or one that another guest stands on', () => {
        const everyone = candidateCells({ ...tutorialPuzzle, clues: [] }, 'anna', { vera: [3, 1] });
        expect(keys(everyone)).not.toContain('0:0');
        expect(keys(everyone)).not.toContain('3:1');
    });

    it('lets nobody else into a room where one guest said they were alone', () => {
        const puzzle: PuzzleDetail = {
            ...tutorialPuzzle,
            clues: [{ text: 'x', constraint_type: 'TOKEN_ALONE_IN_ROOM', constraint: { token: 'anna', room: 'kitchen' }, sort_order: 0 }],
        };

        expect(keys(candidateCells(puzzle, 'boris', {})).filter((key) => ['0:2', '0:3', '1:2', '1:3'].includes(key))).toEqual([]);
        expect(keys(candidateCells(puzzle, 'anna', {}))).toEqual(expect.arrayContaining(['0:3', '1:2']));
    });
});

describe('areAdjacent', () => {
    it('does not step over a room border or a wall', () => {
        expect(areAdjacent(tutorialPuzzle, [1, 2], [0, 2])).toBe(true);
        expect(areAdjacent(tutorialPuzzle, [1, 2], [1, 1])).toBe(false);
        expect(areAdjacent(tutorialPuzzle, [1, 2], [2, 2])).toBe(false);
        expect(areAdjacent(tutorialPuzzle, [1, 2], [0, 3])).toBe(false);

        const walled: PuzzleDetail = { ...tutorialPuzzle, walls: [{ from: [0, 2], to: [1, 2] }] };
        expect(areAdjacent(walled, [1, 2], [0, 2])).toBe(false);
    });
});
