const LABELS: Record<string, string> = {
    murder: 'Убийство',
    theft: 'Кража',
    poisoning: 'Отравление',
    arson: 'Поджог',
    forgery: 'Подлог',
    missing_item: 'Пропажа предмета',
    location: 'Поиск места',
};

export function puzzleTypeLabel(type: string): string {
    return LABELS[type] ?? type.replaceAll('_', ' ');
}

/** How the culprit is found once every guest is placed: one rule for every crime, only the find on the empty cell differs. */
export const CRIME_RULE = 'Виновный — единственный гость, который оказался в одной комнате с пустой клеткой.';

/** What lies on the empty cell, per crime type. */
export const CRIME_FINDS: { type: string; find: string }[] = [
    { type: 'murder', find: 'тело жертвы' },
    { type: 'theft', find: 'украденная вещь' },
    { type: 'poisoning', find: 'бокал с ядом' },
    { type: 'arson', find: 'очаг пожара' },
    { type: 'forgery', find: 'подлинник картины' },
];

/** Who the player names at the end, in the accusative case («назовите …»). */
export function culpritAccusative(type: string): string {
    return { murder: 'убийцу', theft: 'вора', poisoning: 'отравителя', arson: 'поджигателя', forgery: 'автора подлога' }[type] ?? 'виновного';
}
