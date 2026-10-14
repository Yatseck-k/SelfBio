import type { PuzzleObject } from '../types/puzzle';

// Icons per object kind (the object `type`). Kept to emoji that common system fonts can draw.
const ICONS: Record<string, string> = {
    armchair: '💺',
    sofa: '🛋️',
    stool: '🪑',
    chair: '🪑',
    bench: '🛋️',
    bed: '🛏️',
    rug: '🟫',
    pouf: '⭕',
    shelf: '🗄️',
    table: '🍽️',
    piano: '🎹',
    plant: '🌿',
    cabinet: '🗃️',
    chest: '🧰',
    screen: '🚪',
    statue: '🗿',
    fireplace: '🔥',
};

// Older puzzles stored generic types ("furniture", "obstacle"); fall back to the name.
const NAME_ICONS: [string, string][] = [
    ['шкаф', '🗄️'], ['витрин', '🗄️'], ['роял', '🎹'], ['растен', '🌿'], ['пальм', '🌿'], ['ковёр', '🟫'], ['ковер', '🟫'],
    ['стеллаж', '🗄️'], ['тумб', '🗃️'], ['табурет', '🪑'], ['ламп', '🪔'], ['ваз', '🏺'], ['глобус', '🌐'], ['зеркал', '🖼️'],
    ['ширм', '🚪'], ['сундук', '🧰'], ['стол', '🍽️'], ['кресл', '💺'], ['диван', '🛋️'], ['кровать', '🛏️'],
];

export function isUsable(object: Pick<PuzzleObject, 'blocking' | 'occupiable'>): boolean {
    return object.occupiable && !object.blocking;
}

export function objectIcon(object: Pick<PuzzleObject, 'type' | 'name' | 'blocking' | 'occupiable'> & { icon?: string | null }): string {
    // The icon of the kind as edited in the admin comes with the case; older kinds use the built-in table.
    if (object.icon) return object.icon;

    const byKind = ICONS[object.type];
    if (byKind) return byKind;

    const name = object.name.toLocaleLowerCase('ru');
    const byName = NAME_ICONS.find(([part]) => name.includes(part));
    if (byName) return byName[1];

    return isUsable(object) ? '📦' : '🗄️';
}
