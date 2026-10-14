export interface PuzzlePreview {
    rooms: { code: string; cells: Cell[] }[];
    walls: { from: Cell; to: Cell }[];
    blocked: Cell[];
}

export interface PuzzleSummary {
    slug: string;
    title: string;
    description: string | null;
    type: string;
    difficulty: number;
    rows: number;
    cols: number;
    revision: number;
    preview?: PuzzlePreview;
    /** `custom` (made by hand), `base` (the starting set) or `daily` (released on `released_on`). */
    collection?: 'custom' | 'base' | 'daily';
    guest_access?: boolean;
    released_on?: string | null;
    /** Released today (Moscow time). */
    is_new?: boolean;
    /** A signed-in player whose intellect does not open this difficulty yet sees the card closed. */
    locked?: boolean;
    unlock?: { skill: string; label: string; at: number } | null;
}

export interface Cell {
    0: number;
    1: number;
}

export interface PuzzleObject {
    code: string;
    type: string;
    name: string;
    cell: Cell;
    blocking: boolean;
    occupiable: boolean;
    /** Icon of the object's kind from the admin lists. */
    icon?: string | null;
}

export interface PuzzleToken {
    code: string;
    name: string;
    kind: string;
    description: string | null;
    avatar: string | null;
    counts_for_row_col: boolean;
    sort_order: number;
}

/** A window stands on a side of a cell; the cell across that side is next to it as well. */
export interface PuzzleWindow {
    cell: Cell;
    side: 'north' | 'south' | 'west' | 'east';
}

/** The injured party: a card with a portrait and a statement that tells how the culprit is found. */
export interface Victim {
    name: string;
    role: string;
    male: boolean;
    avatar: string | null;
    statement: string;
}

/** A chapter of the story «Путь детектива»: the lines before the case and, after its solution, after it. */
export interface StoryAct {
    title: string;
    from: number;
    to: number;
    about: string;
}

export interface StoryChapter {
    number: number;
    title: string;
    rank: string | null;
    difficulty: number;
    state: 'locked' | 'open' | 'solved';
    briefing: string | null;
    speaker: StoryHero | null;
    outro: string | null;
    /** What the chapter teaches (shown once the chapter is reached). */
    lesson: string | null;
    /** Development points given for the chapter. */
    grant: number;
    slug: string | null;
    built: boolean;
}

/** A person of the story: drawn from a portrait recipe like the guests. */
export interface StoryHero {
    key: string;
    name: string;
    role: string;
    avatar: string;
}

export interface ChapterBriefing {
    number: number;
    title: string;
    briefing: string;
    speaker: StoryHero | null;
}

export interface ChapterOutro {
    number: number;
    title: string;
    outro: string;
    speaker: StoryHero | null;
    last: boolean;
}

export interface PuzzleDetail extends PuzzleSummary {
    story: string | null;
    chapter?: ChapterBriefing;
    victim?: Victim | null;
    grid: { rows: number; cols: number; blocked_cells: Cell[] };
    rooms: { code: string; name: string; cells: Cell[] }[];
    walls: { from: Cell; to: Cell }[];
    windows: PuzzleWindow[];
    objects: PuzzleObject[];
    tokens: PuzzleToken[];
    clues: { text: string; constraint_type: string; constraint: Record<string, unknown>; sort_order: number }[];
}

/** What a witness statement points at on the map: objects (by code) and a room (by code). */
export interface ClueTarget {
    objects: string[];
    room: string | null;
    /** Point at the cells next to a window. */
    nearWindow?: boolean;
}

export interface Ending {
    title?: string;
    text?: string;
    culprit?: string;
    [key: string]: unknown;
}

export type PlacementMap = Record<string, Cell>;
export type NoteState = 'possible' | 'excluded';

/** The rank of a detective (given only by the campaign) and how many chapters lead to the next one. */
export interface PlayerRank {
    level: number;
    title: string;
    chapters_from: number;
    next_title: string | null;
    next_at: number | null;
    progress: number;
}

/** Hints (opened by the attention skill) are a gift plus one for every three solved cases; `next_in` counts the cases to the next one. */
export interface HintBalance {
    available: number;
    earned: number;
    spent: number;
    /** Hints bought in the shop; they work like the earned ones, while the attention skill is learned. */
    purchased?: number;
    next_in: number;
    /** Hints by solved cases open with the first point of attention; until then only the gift exists. */
    locked?: boolean;
}

/** What the skills of the detective let the player use; computed on the server. */
export interface PlayerAbilities {
    /** The highest difficulty of the cases the intellect opens. */
    difficulty: number;
    hints: boolean;
    locate: boolean;
    notes: boolean;
    /** Crossing out the row and the column of a placed guest on the map: expertise. */
    cover: boolean;
    helper: boolean;
    mark: boolean;
    /** «Поставить»: the guest goes to the only cell the helper leaves (expertise). */
    autoplace: boolean;
    hint_stock: number;
    /** How many solved cases give one hint. */
    hint_pace: number;
    /** Hint levels of a case that keep the no-hints bonus (attention). */
    free_hints: number;
    /** Percent added to the points of every solved case (expertise). */
    points_bonus: number;
    /** Percent added to the points of cases of difficulty 4–5 (intellect). */
    hard_bonus: number;
}

export interface SkillStep {
    at: number;
    title: string;
    open: boolean;
}

export interface SkillView {
    key: 'intellect' | 'attention' | 'expertise';
    label: string;
    about: string;
    points: number;
    steps: SkillStep[];
    next_at: number | null;
}

/** Development of the detective: a development point for every level, put into skills that open abilities. */
export interface Development {
    free: number;
    earned: number;
    skills: SkillView[];
    reset: { allowed: boolean; next_level: number | null };
    abilities: PlayerAbilities;
}

export interface PlayerLevel {
    level: number;
    max: number;
    points_from: number;
    next_at: number | null;
    progress: number;
}

export interface PlayerProfile {
    id: number;
    first_name: string | null;
    telegram_username: string | null;
    photo_url: string | null;
    detective: { name: string; avatar: string | null } | null;
    points: number;
    rank: PlayerRank;
    /** Solved chapters of the story: the rank follows them. */
    chapters: number;
    level: PlayerLevel;
    development: Development;
    solved: number;
    hints: HintBalance;
}

export interface SolveReward {
    points: number;
    bonus: boolean;
    first: boolean;
    rank_up: boolean;
    /** A new level (a development point for each level gained). */
    level_up?: { from: number; to: number; skill_points: number } | null;
    /** The scene of the story shown when a new rank is reached. */
    promotion?: { rank: string; text: string; speaker: StoryHero | null } | null;
    /** Development points given for the first solve of a chapter of the story. */
    story_points?: number;
    player: PlayerProfile;
}

export interface PlayerStats {
    solved: number;
    opened: number;
    by_type: Record<string, number>;
    by_difficulty: Record<string, number>;
    average_seconds: number | null;
    best_seconds: number | null;
    streak: { current: number; best: number };
    hints: HintBalance;
    recent: { slug: string; title: string; type: string; difficulty: number; solved_at: string; points: number; hints_used: number; duration_seconds: number | null }[];
}

export interface LeaderboardRow {
    position: number;
    name: string;
    avatar: string | null;
    title: string;
    points: number;
    solved: number;
}

export interface ShopProduct {
    code: string;
    title: string;
    hints: number;
    /** Price in rubles, e.g. "99". */
    price: string;
}

export interface ShopOffer {
    can_buy: boolean;
    reason: 'login_required' | 'hints_locked' | null;
    /** The alpha test: the payment is a stand-in and no real money is taken. */
    test_mode?: boolean;
    products: ShopProduct[];
}
