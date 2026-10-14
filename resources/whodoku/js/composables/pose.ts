import type { PuzzleObject } from '../types/puzzle';

const SITTING = ['armchair', 'sofa', 'stool', 'chair', 'bench', 'pouf'];

/** What a guest does on a piece of furniture: a figure never just stands on it, it uses it. */
export function poseVerb(type: string): string {
    if (SITTING.includes(type)) return 'сидит';
    if (type === 'bed') return 'лежит';

    return 'стоит';
}

/** «Сидит: садовая скамья» for the list of guests and the label of a cell. */
export function poseText(object: Pick<PuzzleObject, 'type' | 'name'>): string {
    const verb = poseVerb(object.type);

    return `${verb[0].toUpperCase()}${verb.slice(1)}: ${object.name}`;
}
