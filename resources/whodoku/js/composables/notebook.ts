import { computed, type ComputedRef, type Ref } from 'vue';
import type { Cell, NoteState, PuzzleDetail } from '../types/puzzle';
import { candidateCells } from './candidates';
import type { PuzzleProgress } from './usePuzzleProgress';
import { SHARED_NOTES } from './usePuzzleProgress';

/**
 * What a tap in «Заметка» does to a cell. With a guest selected the notes are his: возможно → крестик → пусто.
 * Without one a cross is a common mark («здесь никого не будет») that toggles.
 */
export function nextNote(current: NoteState | undefined, guestSelected: boolean): NoteState | undefined {
    if (!guestSelected) {
        return current === 'excluded' ? undefined : 'excluded';
    }

    return current === 'possible' ? 'excluded' : current === 'excluded' ? undefined : 'possible';
}

/** The notebook of a case: notes, the helper that shows where a guest can still stand, and its buttons. */
export function useNotebook(
    puzzle: Ref<PuzzleDetail | null>,
    progress: PuzzleProgress,
    selectedToken: Ref<string | null>,
    save: () => void,
    /** The helper is an ability of the expertise skill; without it the saved switch does nothing. */
    helperOpen: ComputedRef<boolean> | Ref<boolean> = computed(() => true),
) {
    const helperOn = computed(() => helperOpen.value && progress.helper === true);

    /** How many cells each guest can still take; 0 for guests who are already placed. */
    const candidatesByToken = computed<Record<string, number>>(() => {
        const counts: Record<string, number> = {};
        if (!helperOn.value || puzzle.value === null) {
            return counts;
        }
        for (const token of puzzle.value.tokens) {
            counts[token.code] = progress.placements[token.code] ? 0 : candidateCells(puzzle.value, token.code, progress.placements).length;
        }

        return counts;
    });

    /** The cells of the selected guest, as `row:col`; null when there is nothing to show. */
    const candidateKeys = computed<Set<string> | null>(() => {
        if (!helperOn.value || puzzle.value === null || selectedToken.value === null || progress.placements[selectedToken.value]) {
            return null;
        }

        return new Set(candidateCells(puzzle.value, selectedToken.value, progress.placements).map((cell) => `${cell[0]}:${cell[1]}`));
    });

    const helperNote = computed(() => {
        const count = candidateKeys.value?.size;
        if (count === undefined) {
            return selectedToken.value !== null && progress.placements[selectedToken.value]
                ? 'Этот гость уже стоит на карте — подсказывать нечего. Нажмите на него на карте, чтобы снять.'
                : 'Выберите гостя в списке — помощник обведёт клетки, где он ещё может стоять.';
        }

        return count === 0
            ? 'Подходящих клеток нет: где-то в расстановке ошибка.'
            : count === 1 ? 'Осталась единственная клетка — ставьте гостя сюда.' : `Возможных клеток: ${count}.`;
    });

    function toggleHelper(): void {
        progress.helper = !helperOn.value;
        save();
    }

    /** Writes «возможно» on every cell the helper still allows for the chosen guest; crossed-out cells stay. */
    function markCandidates(): void {
        if (selectedToken.value === null || candidateKeys.value === null) {
            return;
        }
        const notes = progress.notes[selectedToken.value] ?? {};
        for (const [key, state] of Object.entries(notes)) {
            if (state === 'possible') delete notes[key];
        }
        for (const key of candidateKeys.value) {
            if (notes[key] !== 'excluded') notes[key] = 'possible';
        }
        progress.notes[selectedToken.value] = notes;
        save();
    }

    function clearNotes(): void {
        if (selectedToken.value === null) {
            return;
        }
        delete progress.notes[selectedToken.value];
        save();
    }

    /** A tap on a cell in the note mode. */
    function tapNote(cell: Cell): void {
        const key = `${cell[0]}:${cell[1]}`;
        const owner = selectedToken.value ?? SHARED_NOTES;
        const notes = progress.notes[owner] ?? {};
        const next = nextNote(notes[key], selectedToken.value !== null);
        if (next === undefined) {
            delete notes[key];
        } else {
            notes[key] = next;
        }
        progress.notes[owner] = notes;
        save();
    }

    return { helperOn, candidatesByToken, candidateKeys, helperNote, toggleHelper, markCandidates, clearNotes, tapNote };
}
