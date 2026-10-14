<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { isUsable, objectIcon } from '../composables/objectKinds';
import { coveredCells } from '../composables/coverage';
import { SHARED_NOTES } from '../composables/usePuzzleProgress';
import { poseText } from '../composables/pose';
import { floorSlot, slotImage } from '../composables/appearance';
import { roomColorIndexes } from '../composables/roomColors';
import SiteImage from './SiteImage.vue';
import type { Cell, ClueTarget, NoteState, PlacementMap, PuzzleDetail, PuzzleObject } from '../types/puzzle';

const props = withDefaults(defineProps<{
    puzzle: PuzzleDetail;
    placements: PlacementMap;
    notes: Record<string, Record<string, NoteState>>;
    selectedToken: string | null;
    /** The trace of the crime, shown on its empty cell once the case is solved. */
    scene?: { cell: Cell; icon: string; label: string } | null;
    /** Objects (by code) and a room (by code) to point at, e.g. the ones a witness statement names. */
    highlight?: ClueTarget | null;
    /** Cells (as `row:col`) the notebook helper still allows for the selected guest. */
    candidates?: Set<string> | null;
    /** Cross out the row and the column of every placed guest (the auto-crossing is an ability of expertise). */
    cover?: boolean;
}>(), { cover: true });

defineEmits<{ selectCell: [cell: Cell] }>();

const cells = computed(() => Array.from(
    { length: props.puzzle.grid.rows * props.puzzle.grid.cols },
    (_, index) => [Math.floor(index / props.puzzle.grid.cols), index % props.puzzle.grid.cols] as Cell,
));

const gridStyle = computed(() => ({
    gridTemplateColumns: `repeat(${props.puzzle.grid.cols}, var(--map-cell-size))`,
    '--grid-cols': String(props.puzzle.grid.cols),
}));

function isHighlighted(cell: Cell): boolean {
    const object = objectAt(cell);

    return object !== undefined && (props.highlight?.objects.includes(object.code) === true);
}

const opposite = { north: 'south', south: 'north', west: 'east', east: 'west' } as const;
const offsets = { north: [-1, 0], south: [1, 0], west: [0, -1], east: [0, 1] } as const;

/** Sides of the cell that carry a window: the window's own cell and the cell across its line. */
function windowSides(cell: Cell): ('north' | 'south' | 'west' | 'east')[] {
    const sides = new Set<'north' | 'south' | 'west' | 'east'>();
    for (const window of props.puzzle.windows ?? []) {
        if (key(window.cell) === key(cell)) sides.add(window.side);
        const [dRow, dCol] = offsets[window.side];
        if (window.cell[0] + dRow === cell[0] && window.cell[1] + dCol === cell[1]) sides.add(opposite[window.side]);
    }

    return [...sides];
}

function isWindowHighlighted(cell: Cell): boolean {
    if (props.highlight?.nearWindow !== true || windowSides(cell).length === 0) {
        return false;
    }
    const room = props.puzzle.rooms[roomIndex(cell)];

    return props.highlight.room === null || room?.code === props.highlight.room;
}

function isRoomHighlighted(cell: Cell): boolean {
    const room = props.puzzle.rooms[roomIndex(cell)];

    return room !== undefined && props.highlight?.room === room.code;
}

const initials = computed(() => Object.fromEntries(props.puzzle.tokens.map((token) => [token.code, token.name.slice(0, 1)])));

/** Letters of the guests marked «возможно» on this cell, from the notebook of every guest. */
function noteLetters(cell: Cell): string[] {
    return Object.entries(props.notes)
        .filter(([code, notes]) => notes[key(cell)] === 'possible' && !props.placements[code])
        .map(([code]) => initials.value[code])
        .filter((letter): letter is string => letter !== undefined);
}

function isCandidate(cell: Cell): boolean {
    return props.candidates?.has(key(cell)) === true && !tokenAt(cell);
}

function isScene(cell: Cell): boolean {
    return props.scene != null && props.scene.cell[0] === cell[0] && props.scene.cell[1] === cell[1];
}

function key(cell: Cell): string {
    return `${cell[0]}:${cell[1]}`;
}

const covered = computed(() => coveredCells(
    props.puzzle.tokens, props.placements, props.puzzle.grid.rows, props.puzzle.grid.cols,
));

function coveredBy(cell: Cell): string[] {
    if (props.cover === false) {
        return [];
    }

    return covered.value.get(key(cell)) ?? [];
}

function blocked(cell: Cell): boolean {
    return props.puzzle.grid.blocked_cells.some((candidate) => key(candidate) === key(cell));
}

function blockingObject(cell: Cell): boolean {
    return props.puzzle.objects.some((object) => (
        key(object.cell) === key(cell) && (object.blocking || !object.occupiable)
    ));
}

function tokenAt(cell: Cell) {
    const code = Object.keys(props.placements).find((token) => key(props.placements[token]) === key(cell));

    return props.puzzle.tokens.find((token) => token.code === code) ?? null;
}

function roomIndex(cell: Cell): number {
    return props.puzzle.rooms.findIndex((room) => room.cells.some((candidate) => key(candidate) === key(cell)));
}

const roomColors = computed<number[]>(() => roomColorIndexes(props.puzzle.rooms));

function roomClass(cell: Cell): string {
    const index = roomIndex(cell);

    return index < 0 ? 'room-unassigned' : `room-${roomColors.value[index]}`;
}

/** The floor of the room: its image (built-in or from the admin) tiled under the cell. */
function floorStyle(cell: Cell): Record<string, string> {
    const index = roomIndex(cell);
    const url = index < 0 ? null : slotImage(floorSlot(props.puzzle.rooms[index].name));

    return url === null ? {} : { backgroundImage: `url("${url}")`, backgroundSize: '1.5rem' };
}

function roomBoundaryClass(cell: Cell): Record<string, boolean> {
    const index = roomIndex(cell);
    if (index < 0) {
        return {};
    }

    const roomAt = (row: number, col: number): number => {
        if (row < 0 || row >= props.puzzle.grid.rows || col < 0 || col >= props.puzzle.grid.cols) {
            return -1;
        }

        return roomIndex([row, col]);
    };

    return {
        'has-room-boundary-top': roomAt(cell[0] - 1, cell[1]) !== index,
        'has-room-boundary-right': roomAt(cell[0], cell[1] + 1) !== index,
        'has-room-boundary-bottom': roomAt(cell[0] + 1, cell[1]) !== index,
        'has-room-boundary-left': roomAt(cell[0], cell[1] - 1) !== index,
    };
}

function objectIndexAt(cell: Cell): number {
    return props.puzzle.objects.findIndex((object) => key(object.cell) === key(cell));
}

function objectAt(cell: Cell): PuzzleObject | undefined {
    return props.puzzle.objects[objectIndexAt(cell)];
}

function cellLabel(cell: Cell): string {
    const room = props.puzzle.rooms[roomIndex(cell)];
    const object = props.puzzle.objects[objectIndexAt(cell)];
    const token = tokenAt(cell);
    const details = [
        room?.name,
        object?.name,
        object ? (isUsable(object) ? 'на предмете можно разместить персонажа' : 'на предмет нельзя поставить персонажа') : '',
        token?.name,
        token && object ? poseText(object).toLocaleLowerCase('ru') : '',
        isScene(cell) ? `место преступления: ${props.scene?.label}` : '',
    ].filter(Boolean);

    const coverers = coveredBy(cell);
    if (coverers.length > 0) {
        details.push(`перекрыта: в этой строке или столбце уже стоит ${[...new Set(coverers)].join(', ')}`);
    }

    return `Строка ${cell[0] + 1}, столбец ${cell[1] + 1}${details.length > 0 ? `, ${details.join(', ')}` : ''}${blocked(cell) ? ', недоступна' : ''}`;
}

function noteAt(cell: Cell): NoteState | null {
    const own = props.selectedToken === null ? null : props.notes[props.selectedToken]?.[key(cell)] ?? null;

    // A cross put without a guest applies to everybody.
    return own ?? props.notes[SHARED_NOTES]?.[key(cell)] ?? null;
}

function wallClass(cell: Cell): Record<string, boolean> {
    const classes: Record<string, boolean> = {
        'has-wall-top': false,
        'has-wall-right': false,
        'has-wall-bottom': false,
        'has-wall-left': false,
    };

    for (const wall of props.puzzle.walls) {
        if (key(wall.from) !== key(cell)) continue;
        const other = wall.to;

        const rowDelta = other[0] - cell[0];
        const colDelta = other[1] - cell[1];
        if (rowDelta === -1) classes['has-wall-top'] = true;
        if (colDelta === 1) classes['has-wall-right'] = true;
        if (rowDelta === 1) classes['has-wall-bottom'] = true;
        if (colDelta === -1) classes['has-wall-left'] = true;
    }

    return classes;
}

// Room name plates sit on a border of their room, centred on its longest straight edge. The plate grows with
// its text and, if two plates would touch, a later one moves to another edge of its room.
interface LabelCandidate { x: number; y: number }

const boardEl = ref<HTMLElement | null>(null);
const labelEls = new Map<string, HTMLElement>();
const labelPositions = ref<Record<string, { left: number; top: number }>>({});

const labelCandidates = computed<Record<string, LabelCandidate[]>>(() => {
    const result: Record<string, LabelCandidate[]> = {};
    for (const room of props.puzzle.rooms) {
        const inRoom = new Set(room.cells.map(key));
        const runs = (edge: 'top' | 'bottom'): { x: number; y: number; length: number }[] => {
            const rows = new Map<number, number[]>();
            for (const cell of room.cells) {
                const neighbour = edge === 'top' ? [cell[0] - 1, cell[1]] : [cell[0] + 1, cell[1]];
                if (!inRoom.has(`${neighbour[0]}:${neighbour[1]}`)) {
                    rows.set(cell[0], [...(rows.get(cell[0]) ?? []), cell[1]]);
                }
            }

            const found: { x: number; y: number; length: number }[] = [];
            rows.forEach((cols, row) => {
                const sorted = [...cols].sort((a, b) => a - b);
                let start = sorted[0];
                sorted.forEach((col, index) => {
                    if (index === sorted.length - 1 || sorted[index + 1] !== col + 1) {
                        found.push({ x: (start + col + 1) / 2, y: edge === 'top' ? row : row + 1, length: col - start + 1 });
                        start = sorted[index + 1];
                    }
                });
            });

            return found.sort((a, b) => b.length - a.length || a.y - b.y);
        };

        result[room.code] = [...runs('top'), ...runs('bottom')];
    }

    return result;
});

function setLabelRef(code: string, element: unknown): void {
    if (element instanceof HTMLElement) {
        labelEls.set(code, element);
    } else {
        labelEls.delete(code);
    }
}

function layoutLabels(): void {
    const board = boardEl.value;
    const cell = board?.querySelector<HTMLElement>('.map-cell');
    if (!board || !cell) return;

    const cellSize = cell.getBoundingClientRect().width;
    const boardWidth = board.getBoundingClientRect().width;
    const placed: { left: number; right: number; top: number; bottom: number }[] = [];
    const positions: Record<string, { left: number; top: number }> = {};
    const gap = 4;

    // Big rooms pick their edge first; small rooms adapt around them.
    const rooms = [...props.puzzle.rooms].sort((a, b) => b.cells.length - a.cells.length);
    for (const room of rooms) {
        const element = labelEls.get(room.code);
        const candidates = labelCandidates.value[room.code] ?? [];
        if (!element || candidates.length === 0) continue;

        const { width, height } = element.getBoundingClientRect();
        const boxes = candidates.map((candidate) => {
            const left = Math.min(Math.max(candidate.x * cellSize - width / 2, 0), Math.max(0, boardWidth - width));
            const top = candidate.y * cellSize - height / 2;

            return { left, top, right: left + width, bottom: top + height };
        });
        const free = boxes.find((box) => !placed.some((other) => (
            box.left < other.right + gap && box.right > other.left - gap && box.top < other.bottom + gap && box.bottom > other.top - gap
        )));
        const chosen = free ?? boxes[0];

        placed.push(chosen);
        positions[room.code] = { left: chosen.left, top: chosen.top };
    }

    labelPositions.value = positions;
}

let observer: ResizeObserver | null = null;

onMounted(async () => {
    await nextTick();
    layoutLabels();
    if (typeof ResizeObserver !== 'undefined' && boardEl.value) {
        observer = new ResizeObserver(() => layoutLabels());
        observer.observe(boardEl.value);
    }
});

onBeforeUnmount(() => observer?.disconnect());

watch(() => props.puzzle, async () => {
    await nextTick();
    layoutLabels();
});
</script>

<template>
    <section class="map-section" aria-labelledby="map-heading">
        <h2 id="map-heading">Карта</h2>
        <div class="map-viewport">
            <div ref="boardEl" class="map-board">
                <div class="puzzle-grid" :style="gridStyle" role="grid" :aria-rowcount="puzzle.grid.rows" :aria-colcount="puzzle.grid.cols">
                    <button
                        v-for="cell in cells"
                        :key="key(cell)"
                        class="map-cell"
                        :class="[
                            wallClass(cell),
                            roomBoundaryClass(cell),
                            roomClass(cell),
                            { 'is-candidate': isCandidate(cell), 'is-highlight': isHighlighted(cell) || isWindowHighlighted(cell), 'is-room-highlight': isRoomHighlighted(cell), 'is-covered': coveredBy(cell).length > 0 && !tokenAt(cell) && !isScene(cell), 'is-blocked': blocked(cell), 'is-object-blocked': blockingObject(cell), 'is-possible': noteAt(cell) === 'possible', 'is-excluded': noteAt(cell) === 'excluded' },
                        ]"
                        :style="floorStyle(cell)"
                        type="button"
                        :disabled="blocked(cell) || blockingObject(cell)"
                        :aria-label="cellLabel(cell)"
                        @click="$emit('selectCell', cell)"
                    >
                        <span
                            v-if="objectAt(cell)"
                            class="map-object"
                            :class="{ 'is-blocking': !isUsable(objectAt(cell)!) }"
                            aria-hidden="true"
                        >
                            <SiteImage :name="`kind:${objectAt(cell)!.type}`" :fallback="objectIcon(objectAt(cell)!)" />
                        </span>
                        <span v-for="side in windowSides(cell)" :key="side" class="map-window" :class="`is-${side}`" title="Окно" aria-hidden="true" />
                        <span v-if="objectAt(cell)" class="map-object-name" aria-hidden="true">{{ objectAt(cell)!.name }}</span>
                        <span v-if="isScene(cell)" class="map-scene" :title="scene?.label"><SiteImage :name="`finding:${puzzle.type}`" :fallback="scene?.icon" /></span>
                        <strong v-if="tokenAt(cell)" class="map-token" :class="{ 'is-on-object': objectAt(cell) !== undefined }">{{ tokenAt(cell)?.name.slice(0, 1) }}</strong>
                        <span v-else-if="noteAt(cell) === 'excluded'" aria-hidden="true">×</span>
                        <span v-if="!tokenAt(cell) && noteLetters(cell).length" class="map-note-letters" aria-hidden="true">{{ noteLetters(cell).slice(0, 4).join(' ') }}</span>
                    </button>
                </div>
                <div class="room-labels" aria-hidden="true">
                    <span
                        v-for="room in puzzle.rooms"
                        :key="room.code"
                        :ref="(element) => setLabelRef(room.code, element)"
                        class="room-label"
                        :style="labelPositions[room.code] ? { left: `${labelPositions[room.code].left}px`, top: `${labelPositions[room.code].top}px` } : { visibility: 'hidden' }"
                    >{{ room.name }}</span>
                </div>
            </div>
        </div>
    </section>
</template>
