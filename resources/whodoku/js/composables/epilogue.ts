import type { Ending } from '../types/puzzle';

/** Sentences that only repeat what the card shows on its own: the culprit and the find on the empty cell. */
const REPEATED = /^Огонь начался|^Тело нашли|^Пропажа нашлась|^Бокал с ядом остался|^Настоящая картина/u;

/** The epilogue as short paragraphs: the first sentence only names the culprit, the card shows him separately. */
export function epilogueLines(ending: Ending | null | undefined): string[] {
    const sentences = String(ending?.text ?? '').trim().split(/(?<=[.!?])\s+/u).filter(Boolean);

    return ending?.culprit ? sentences.slice(1).filter((line) => !REPEATED.test(line)) : sentences;
}

/** A headline only when it says more than «Дело раскрыто», which the eyebrow already says. */
export function epilogueTitle(ending: Ending | null | undefined): string {
    const title = String(ending?.title ?? '');

    return title === 'Дело раскрыто' ? '' : title;
}
