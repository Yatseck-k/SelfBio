import { reactive } from 'vue';
import type { Ending, NoteState, PlacementMap } from '../types/puzzle';

/** The notebook key of a mark that belongs to no guest: «в эту клетку никто не встанет». */
export const SHARED_NOTES = '*';

export interface PuzzleProgress {
    version: 1;
    placements: PlacementMap;
    notes: Record<string, Record<string, NoteState>>;
    usedClues: number[];
    hintsUsed: number[];
    hintTexts?: { level: number; text: string }[];
    /** The notebook helper that shows where the chosen guest can still stand. */
    helper?: boolean;
    ending?: Ending;
}

const emptyProgress = (): PuzzleProgress => ({
    version: 1,
    placements: {},
    notes: {},
    usedClues: [],
    hintsUsed: [],
    hintTexts: [],
});

export const progressKey = (slug: string, revision: number): string => `killdoku:progress:${slug}:${revision}`;

/** True when the guest has already solved this puzzle revision in this browser. */
export function isSolved(slug: string, revision: number): boolean {
    return read(progressKey(slug, revision))?.ending !== undefined;
}

export function usePuzzleProgress() {
    const progress = reactive<PuzzleProgress>(emptyProgress());
    let storageKey = '';

    function load(slug: string, revision: number): void {
        storageKey = progressKey(slug, revision);
        const saved = read(storageKey);
        Object.assign(progress, emptyProgress(), saved ?? {});
    }

    function save(): void {
        if (storageKey === '' || typeof window === 'undefined') {
            return;
        }

        window.localStorage.setItem(storageKey, JSON.stringify(progress));
    }

    return { progress, load, save };
}

function read(key: string): PuzzleProgress | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const value: unknown = JSON.parse(window.localStorage.getItem(key) ?? 'null');
        if (!isProgress(value)) {
            return null;
        }

        return value;
    } catch {
        return null;
    }
}

function isProgress(value: unknown): value is PuzzleProgress {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Partial<PuzzleProgress>;

    return candidate.version === 1
        && isRecord(candidate.placements)
        && isRecord(candidate.notes)
        && Array.isArray(candidate.usedClues)
        && Array.isArray(candidate.hintsUsed);
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
