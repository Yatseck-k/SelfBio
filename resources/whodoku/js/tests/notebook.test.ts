import { describe, expect, it } from 'vitest';
import { epilogueLines, epilogueTitle } from '../composables/epilogue';
import { nextNote } from '../composables/notebook';

describe('nextNote', () => {
    it('cycles possible → excluded → empty for a selected guest', () => {
        expect(nextNote(undefined, true)).toBe('possible');
        expect(nextNote('possible', true)).toBe('excluded');
        expect(nextNote('excluded', true)).toBe(undefined);
    });

    it('toggles a common cross when no guest is selected', () => {
        expect(nextNote(undefined, false)).toBe('excluded');
        expect(nextNote('excluded', false)).toBe(undefined);
    });
});

describe('epilogue', () => {
    const ending = { title: 'Дело раскрыто', culprit: 'Вера', text: 'Поджигатель — Вера. Огонь начался в пустой клетке. В веранде, где всё случилось, находилась только Вера.' };

    it('drops the sentences the card shows on its own', () => {
        expect(epilogueLines(ending)).toEqual(['В веранде, где всё случилось, находилась только Вера.']);
    });

    it('keeps every sentence when there is no culprit and hides the plain headline', () => {
        expect(epilogueLines({ text: 'Раз. Два.' })).toEqual(['Раз.', 'Два.']);
        expect(epilogueTitle(ending)).toBe('');
        expect(epilogueTitle({ title: 'Финал' })).toBe('Финал');
        expect(epilogueLines(null)).toEqual([]);
    });
});
