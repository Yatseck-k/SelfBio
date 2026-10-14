import { describe, expect, it } from 'vitest';
import { coveredCells, findEmptyCell } from '../composables/coverage';
import { tutorialPuzzle, tutorialSteps } from '../composables/tutorialCase';

describe('coveredCells', () => {
    it('crosses the rest of the row and the column of every placed guest', () => {
        const covered = coveredCells(tutorialPuzzle.tokens, { anna: [1, 3] }, 4, 4);

        expect(covered.get('1:0')).toEqual(['Анна Вишневская']);
        expect(covered.get('3:3')).toEqual(['Анна Вишневская']);
        expect(covered.has('1:3')).toBe(false);
        expect(covered.has('0:0')).toBe(false);
    });

    it('skips the guest who is being moved', () => {
        expect(coveredCells(tutorialPuzzle.tokens, { anna: [1, 3] }, 4, 4, 'anna').size).toBe(0);
    });
});

describe('findEmptyCell', () => {
    it('finds the only free cell once every guest is placed', () => {
        expect(findEmptyCell(tutorialPuzzle, { anna: [1, 3], boris: [0, 2], vera: [3, 1] })).toEqual([2, 0]);
    });

    it('waits until everybody stands on the map', () => {
        expect(findEmptyCell(tutorialPuzzle, { anna: [1, 3], boris: [0, 2] })).toBeNull();
    });
});

describe('the worked example', () => {
    it('places guests only where the walkthrough says and ends with the same empty cell', () => {
        const last = tutorialSteps.at(-1)!;

        expect(last.placements).toEqual({ anna: [1, 3], boris: [0, 2], vera: [3, 1] });
        expect(findEmptyCell(tutorialPuzzle, last.placements)).toEqual([2, 0]);
        for (const step of tutorialSteps) {
            for (const [code, cell] of Object.entries(step.placements)) {
                expect(last.placements[code]).toEqual(cell);
            }
        }
    });
});
