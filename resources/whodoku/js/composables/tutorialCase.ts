import type { ClueTarget, PlacementMap, PuzzleDetail } from '../types/puzzle';

/**
 * The worked example of the rules page. It mirrors tests/Unit/Domain/Puzzle/TutorialCaseTest.php, which checks
 * that this case is valid, has exactly one solution and names the culprit the walkthrough names.
 */
const block = (row: number, col: number): [number, number][] => [[row, col], [row, col + 1], [row + 1, col], [row + 1, col + 1]];

export const tutorialPuzzle: PuzzleDetail = {
    slug: 'tutorial',
    title: 'Учебное дело',
    description: null,
    story: null,
    type: 'murder',
    difficulty: 1,
    rows: 4,
    cols: 4,
    revision: 1,
    grid: { rows: 4, cols: 4, blocked_cells: [] },
    rooms: [
        { code: 'living', name: 'Гостиная', cells: block(0, 0) },
        { code: 'kitchen', name: 'Кухня', cells: block(0, 2) },
        { code: 'study', name: 'Кабинет', cells: block(2, 0) },
        { code: 'library', name: 'Библиотека', cells: block(2, 2) },
    ],
    walls: [],
    windows: [{ cell: [1, 3], side: 'east' }],
    objects: [
        { code: 'armchair', type: 'armchair', name: 'кресло', cell: [1, 2], blocking: false, occupiable: true },
        { code: 'carpet', type: 'rug', name: 'ковёр', cell: [3, 1], blocking: false, occupiable: true },
        { code: 'piano', type: 'piano', name: 'рояль', cell: [0, 0], blocking: true, occupiable: false },
        { code: 'cabinet', type: 'cabinet', name: 'шкаф', cell: [2, 3], blocking: true, occupiable: false },
        { code: 'plant', type: 'plant', name: 'фикус', cell: [3, 3], blocking: true, occupiable: false },
        { code: 'table', type: 'table', name: 'стол', cell: [2, 2], blocking: true, occupiable: false },
    ],
    tokens: [
        { code: 'anna', name: 'Анна Вишневская', kind: 'character', description: null, avatar: 'v1;g=f;skin=1;hair=2;style=3;shirt=0;extra=earrings', counts_for_row_col: true, sort_order: 0 },
        { code: 'boris', name: 'Борис Лисов', kind: 'character', description: null, avatar: 'v1;g=m;skin=2;hair=1;style=0;shirt=1;extra=glasses', counts_for_row_col: true, sort_order: 1 },
        { code: 'vera', name: 'Вера Соколова', kind: 'character', description: null, avatar: 'v1;g=f;skin=0;hair=4;style=2;shirt=2;extra=none', counts_for_row_col: true, sort_order: 2 },
    ],
    clues: [
        { text: 'Анна Вишневская утверждает: «Я стояла возле окна».', constraint_type: 'TOKEN_NEAR_WINDOW', constraint: { token: 'anna' }, sort_order: 0 },
        { text: 'Борис Лисов утверждает: «Я любовался креслом».', constraint_type: 'TOKEN_ADJACENT_OBJECT', constraint: { token: 'boris', object: 'armchair' }, sort_order: 1 },
        { text: 'Вера Соколова утверждает: «Я стояла на ковре».', constraint_type: 'TOKEN_ON_OBJECT', constraint: { token: 'vera', object: 'carpet' }, sort_order: 2 },
    ],
    victim: {
        name: 'Леонтий Арнаутов', role: 'Жертва', male: true,
        avatar: 'v1;g=m;skin=3;hair=6;style=5;shirt=5;extra=mustache',
        statement: 'Я был наедине с убийцей: в комнате с пустой клеткой больше никого не оказалось.',
    },
};

export interface TutorialStep {
    title: string;
    text: string;
    placements: PlacementMap;
    highlight: ClueTarget | null;
    /** `unknown` shows the empty cell as «❔», `body` shows the trace of the crime. */
    scene: 'none' | 'unknown' | 'body';
}

const anna: PlacementMap = { anna: [1, 3] };
const annaVera: PlacementMap = { ...anna, vera: [3, 1] };
const everyone: PlacementMap = { ...annaVera, boris: [0, 2] };

export const tutorialSteps: TutorialStep[] = [
    {
        title: 'Что дано',
        text: 'Карта 4×4: четыре комнаты, три гостя и жертва. У каждого гостя одно показание — оно правдиво. Нужно расставить гостей так, чтобы совпали все показания и никто не стоял в одной строке или в одном столбце с другим.',
        placements: {},
        highlight: null,
        scene: 'none',
    },
    {
        title: 'Анна: возле окна',
        text: 'Окно стоит на правом краю карты у клетки в строке 2, столбце 4 — это единственная клетка «возле окна». Значит, Анна стоит там. Сразу перекрываем крестиками её строку и столбец: больше никто на них не встанет.',
        placements: anna,
        highlight: { objects: [], room: null, nearWindow: true },
        scene: 'none',
    },
    {
        title: 'Вера: на ковре',
        text: 'Ковёр лежит в одной клетке — в строке 4, столбце 2. Вера стоит на нём, и её линии тоже перекрыты.',
        placements: annaVera,
        highlight: { objects: ['carpet'], room: null },
        scene: 'none',
    },
    {
        title: 'Борис: рядом с креслом',
        text: 'Рядом с креслом — клетки над ним и справа. Клетка слева тоже прилегает к креслу, но она в другой комнате: «рядом» не перешагивает границу комнаты. Справа уже стоит Анна, поэтому остаётся клетка над креслом.',
        placements: everyone,
        highlight: { objects: ['armchair'], room: null },
        scene: 'none',
    },
    {
        title: 'Пустая клетка',
        text: 'Гостей на один меньше, чем строк, поэтому одна строка и один столбец остались свободными. Их пересечение — единственная клетка без крестика, куда можно было что-то положить. Это место преступления.',
        placements: everyone,
        highlight: null,
        scene: 'unknown',
    },
    {
        title: 'Кто виновен',
        text: 'Тело лежит в кабинете. В кабинете стоит только Вера — она и была наедине с жертвой. Правило убийства: виновен единственный гость в одной комнате с телом.',
        placements: everyone,
        highlight: { objects: [], room: 'study' },
        scene: 'body',
    },
];
