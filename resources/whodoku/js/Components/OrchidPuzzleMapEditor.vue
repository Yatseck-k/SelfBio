<script setup lang="ts">
import { computed, ref } from 'vue';

type Cell = [number, number];
type Room = { code: string; name: string; cells: Cell[] };
type MapObject = { code: string; type: string; name: string; cell: Cell; blocking: boolean; occupiable: boolean };
type Wall = { from: Cell; to: Cell };
type Side = 'north' | 'south' | 'west' | 'east';
type MapWindow = { cell: Cell; side: Side };
type MapState = { blocked_cells: Cell[]; windows?: MapWindow[]; rooms: Room[]; walls: Wall[]; objects: MapObject[] };
type Tool = 'room' | 'wall' | 'window' | 'object' | 'block' | 'clear';

type ObjectKind = { code: string; label: string; name: string; occupiable: boolean };

const props = defineProps<{ puzzle: { rows: number; cols: number; status: string }; initialMap: MapState; kinds: ObjectKind[] }>();
const map = ref<MapState>({ windows: [], ...(JSON.parse(JSON.stringify(props.initialMap)) as MapState) });
const windowSide = ref<Side>('north');
const sideLabels: Record<Side, string> = { north: 'сверху', south: 'снизу', west: 'слева', east: 'справа' };
const tool = ref<Tool>('room');
const room = ref(map.value.rooms[0]?.code ?? '');
const wallStart = ref<Cell | null>(null);
const kind = ref(props.kinds[0]?.code ?? '');
const usableKinds = computed(() => props.kinds.filter((item) => item.occupiable));
const unusableKinds = computed(() => props.kinds.filter((item) => !item.occupiable));
const notice = ref('');

const tools: { value: Tool; label: string; help: string }[] = [
    { value: 'room', label: 'Комната', help: 'Выберите комнату и нажимайте любые клетки, чтобы назначить их ей.' },
    { value: 'wall', label: 'Стена', help: 'Выберите две соседние клетки. Стена добавится или снимется на общей границе.' },
    { value: 'window', label: 'Окно', help: 'Выберите сторону и нажмите клетку: окно появится на этой стороне клетки или исчезнет. Окно может стоять только на краю карты или на границе двух комнат; гость «возле окна» стоит в любой из двух клеток по сторонам линии.' },
    { value: 'object', label: 'Предмет', help: 'Выберите вид предмета и нажмите клетку. Повторный клик по тому же предмету убирает его. На «используемых» предметах можно разместить персонажа, на «неиспользуемых» — нельзя.' },
    { value: 'block', label: 'Препятствие', help: 'Нажмите любую клетку, чтобы включить или снять запрет размещения.' },
    { value: 'clear', label: 'Очистить клетку', help: 'Удаляет препятствие, предмет и принадлежность выбранной клетки к комнате.' },
];

const cells = computed(() => Array.from(
    { length: props.puzzle.rows * props.puzzle.cols },
    (_, index) => [Math.floor(index / props.puzzle.cols), index % props.puzzle.cols] as Cell,
));
const gridStyle = computed(() => ({
    gridTemplateColumns: `repeat(${props.puzzle.cols}, minmax(4.5rem, 1fr))`,
    minWidth: `${props.puzzle.cols * 4.5}rem`,
}));
const currentHelp = computed(() => tools.find((item) => item.value === tool.value)?.help ?? '');

function key(cell: Cell): string {
    return cell.join(':');
}

function has(items: Cell[], cell: Cell): boolean {
    return items.some((item) => key(item) === key(cell));
}

function objectAt(cell: Cell): MapObject | undefined {
    return map.value.objects.find((item) => key(item.cell) === key(cell));
}

function roomIndex(cell: Cell): number {
    return map.value.rooms.findIndex((item) => has(item.cells, cell));
}

function wallIndex(first: Cell, second: Cell): number {
    return map.value.walls.findIndex((wall) => (
        (key(wall.from) === key(first) && key(wall.to) === key(second))
        || (key(wall.from) === key(second) && key(wall.to) === key(first))
    ));
}

const offsets: Record<Side, Cell> = { north: [-1, 0], south: [1, 0], west: [0, -1], east: [0, 1] };
const opposite: Record<Side, Side> = { north: 'south', south: 'north', west: 'east', east: 'west' };

function across(cell: Cell, side: Side): Cell {
    return [cell[0] + offsets[side][0], cell[1] + offsets[side][1]];
}

function inside(cell: Cell): boolean {
    return cell[0] >= 0 && cell[0] < props.puzzle.rows && cell[1] >= 0 && cell[1] < props.puzzle.cols;
}

/** The line a window stands on is the same whichever of its two cells it was given from. */
function lineKey(cell: Cell, side: Side): string {
    const other = across(cell, side);

    return inside(other) ? [key(cell), key(other)].sort().join('|') : `${key(cell)}:${side}`;
}

/** Sides of the cell that carry a window, including windows given from the neighbouring cell. */
function windowSides(cell: Cell): Side[] {
    const sides = new Set<Side>();
    for (const item of map.value.windows ?? []) {
        if (key(item.cell) === key(cell)) sides.add(item.side);
        if (key(across(item.cell, item.side)) === key(cell)) sides.add(opposite[item.side]);
    }

    return [...sides];
}

function toggleWindow(cell: Cell): void {
    const line = lineKey(cell, windowSide.value);
    const existing = (map.value.windows ?? []).findIndex((item) => lineKey(item.cell, item.side) === line);
    if (existing >= 0) {
        map.value.windows?.splice(existing, 1);
        notice.value = 'Окно убрано.';

        return;
    }
    const other = across(cell, windowSide.value);
    const roomOf = (target: Cell): number => (inside(target) ? roomIndex(target) : -2);
    if (inside(other) && roomOf(cell) === roomOf(other)) {
        notice.value = 'Окно можно поставить только на край карты или на границу двух комнат.';

        return;
    }
    map.value.windows = [...(map.value.windows ?? []), { cell, side: windowSide.value }];
    notice.value = 'Окно добавлено.';
}

function cellClasses(cell: Cell): Record<string, boolean> {
    const roomNumber = roomIndex(cell);
    const roomNeighbor = (row: number, col: number): number => (
        row < 0 || row >= props.puzzle.rows || col < 0 || col >= props.puzzle.cols
            ? -1
            : roomIndex([row, col])
    );
    const roomEdges = {
        'has-room-top': roomNeighbor(cell[0] - 1, cell[1]) !== roomNumber,
        'has-room-right': roomNeighbor(cell[0], cell[1] + 1) !== roomNumber,
        'has-room-bottom': roomNeighbor(cell[0] + 1, cell[1]) !== roomNumber,
        'has-room-left': roomNeighbor(cell[0], cell[1] - 1) !== roomNumber,
    };
    const wallEdges = {
        'has-wall-top': wallIndex(cell, [cell[0] - 1, cell[1]]) >= 0,
        'has-wall-right': wallIndex(cell, [cell[0], cell[1] + 1]) >= 0,
        'has-wall-bottom': wallIndex(cell, [cell[0] + 1, cell[1]]) >= 0,
        'has-wall-left': wallIndex(cell, [cell[0], cell[1] - 1]) >= 0,
    };

    return {
        'is-blocked': has(map.value.blocked_cells, cell),
        'is-wall-start': wallStart.value !== null && key(wallStart.value) === key(cell),
        'room-unassigned': roomNumber < 0,
        [`room-${roomNumber % 6}`]: roomNumber >= 0,
        'has-object': objectAt(cell) !== undefined,
        'has-blocking-object': objectAt(cell)?.blocking === true || objectAt(cell)?.occupiable === false,
        ...roomEdges,
        ...wallEdges,
    };
}

function cellLabel(cell: Cell): string {
    const roomNumber = roomIndex(cell);
    const object = objectAt(cell);
    const walls = [
        wallIndex(cell, [cell[0] - 1, cell[1]]) >= 0 ? 'стена сверху' : '',
        wallIndex(cell, [cell[0], cell[1] + 1]) >= 0 ? 'стена справа' : '',
        wallIndex(cell, [cell[0] + 1, cell[1]]) >= 0 ? 'стена снизу' : '',
        wallIndex(cell, [cell[0], cell[1] - 1]) >= 0 ? 'стена слева' : '',
    ].filter(Boolean);
    const details = [
        roomNumber >= 0 ? map.value.rooms[roomNumber]?.name : 'вне комнаты',
        has(map.value.blocked_cells, cell) ? 'препятствие, размещение запрещено' : '',
        object ? `${object.name}${object.blocking || !object.occupiable ? ', на предмет нельзя поставить персонажа' : ', персонажа можно разместить'}` : '',
        ...walls,
        wallStart.value && key(wallStart.value) === key(cell) ? 'первая клетка стены выбрана' : '',
    ].filter(Boolean);

    return `Строка ${cell[0] + 1}, столбец ${cell[1] + 1}: ${details.join(', ')}`;
}

function nextObjectCode(): string {
    let index = 1;
    const existing = new Set(map.value.objects.map((item) => item.code));
    while (existing.has(`object_${index}`)) {
        index++;
    }

    return `object_${index}`;
}

function click(cell: Cell): void {
    notice.value = '';

    if (tool.value === 'wall') {
        if (!wallStart.value) {
            wallStart.value = cell;
            notice.value = `Выбрана строка ${cell[0] + 1}, столбец ${cell[1] + 1}. Теперь выберите соседнюю клетку.`;
            return;
        }

        if (key(wallStart.value) === key(cell)) {
            wallStart.value = null;
            notice.value = 'Выбор начала стены отменён.';
            return;
        }

        const distance = Math.abs(cell[0] - wallStart.value[0]) + Math.abs(cell[1] - wallStart.value[1]);
        if (distance !== 1) {
            wallStart.value = cell;
            notice.value = 'Клетки должны быть соседними по стороне. Началом стены выбрана новая клетка.';
            return;
        }

        const existingWall = wallIndex(wallStart.value, cell);
        if (existingWall >= 0) {
            map.value.walls.splice(existingWall, 1);
            notice.value = 'Стена снята.';
        } else {
            map.value.walls.push({ from: wallStart.value, to: cell });
            notice.value = 'Стена добавлена.';
        }
        wallStart.value = null;
        return;
    }

    wallStart.value = null;

    if (tool.value === 'window') {
        toggleWindow(cell);

        return;
    }

    if (tool.value === 'block') {
        map.value.blocked_cells = has(map.value.blocked_cells, cell)
            ? map.value.blocked_cells.filter((item) => key(item) !== key(cell))
            : [...map.value.blocked_cells, cell];
        return;
    }

    if (tool.value === 'clear') {
        map.value.blocked_cells = map.value.blocked_cells.filter((item) => key(item) !== key(cell));
        map.value.objects = map.value.objects.filter((item) => key(item.cell) !== key(cell));
        map.value.rooms.forEach((item) => {
            item.cells = item.cells.filter((roomCell) => key(roomCell) !== key(cell));
        });
        return;
    }

    if (tool.value === 'object') {
        const chosen = props.kinds.find((item) => item.code === kind.value);
        const existing = objectAt(cell);
        if (existing && chosen && existing.type === chosen.code) {
            map.value.objects = map.value.objects.filter((item) => item.code !== existing.code);

            return;
        }
        if (chosen) {
            map.value.objects = map.value.objects.filter((item) => key(item.cell) !== key(cell));
            map.value.objects.push({
                code: nextObjectCode(),
                type: chosen.code,
                name: chosen.name,
                cell,
                blocking: !chosen.occupiable,
                occupiable: chosen.occupiable,
            });
        }

        return;
    }

    const selectedRoom = map.value.rooms.find((item) => item.code === room.value);
    if (selectedRoom) {
        map.value.rooms.forEach((item) => {
            item.cells = item.cells.filter((roomCell) => key(roomCell) !== key(cell));
        });
        selectedRoom.cells.push(cell);
    }
}

function setTool(value: Tool): void {
    tool.value = value;
    wallStart.value = null;
    notice.value = '';
}
</script>

<template>
    <div class="map-editor">
        <p v-if="puzzle.status === 'published'" class="alert alert-warning">
            После сохранения дело станет черновиком и потребует повторной проверки.
        </p>

        <div class="map-editor-tools" role="toolbar" aria-label="Инструменты карты">
            <button
                v-for="item in tools"
                :key="item.value"
                class="btn btn-sm"
                :class="tool === item.value ? 'btn-primary' : 'btn-outline-secondary'"
                type="button"
                :aria-pressed="tool === item.value"
                @click="setTool(item.value)"
            >
                {{ item.label }}
            </button>
        </div>

        <label v-if="tool === 'room' && map.rooms.length > 0" class="map-editor-room">
            Комната
            <select v-model="room" class="form-select">
                <option v-for="(item, index) in map.rooms" :key="item.code" :value="item.code">
                    {{ index + 1 }}. {{ item.name }}
                </option>
            </select>
        </label>

        <label v-if="tool === 'window'" class="map-editor-room">
            Сторона клетки
            <select v-model="windowSide" class="form-select">
                <option v-for="(label, side) in sideLabels" :key="side" :value="side">{{ label }}</option>
            </select>
        </label>

        <label v-if="tool === 'object' && kinds.length > 0" class="map-editor-room">
            Вид предмета
            <select v-model="kind" class="form-select">
                <optgroup label="Используемые — можно разместить персонажа">
                    <option v-for="item in usableKinds" :key="item.code" :value="item.code">{{ item.label }} ({{ item.name }})</option>
                </optgroup>
                <optgroup label="Неиспользуемые — персонажа разместить нельзя">
                    <option v-for="item in unusableKinds" :key="item.code" :value="item.code">{{ item.label }} ({{ item.name }})</option>
                </optgroup>
            </select>
        </label>

        <p class="map-editor-help" role="status" aria-live="polite">
            {{ notice || currentHelp }}
        </p>

        <div class="map-editor-viewport" tabindex="0" aria-label="Поле редактирования карты">
            <div class="map-editor-grid" :style="gridStyle">
                <button
                    v-for="cell in cells"
                    :key="key(cell)"
                    type="button"
                    class="map-editor-cell"
                    :class="cellClasses(cell)"
                    :aria-label="cellLabel(cell)"
                    :aria-pressed="wallStart !== null && key(wallStart) === key(cell)"
                    @click="click(cell)"
                >
                    <span v-for="side in windowSides(cell)" :key="side" class="map-editor-window" :class="`is-${side}`" aria-hidden="true" />
                    <span class="map-editor-coordinate">{{ cell[0] + 1 }}:{{ cell[1] + 1 }}</span>
                    <span v-if="roomIndex(cell) >= 0" class="map-editor-room-name">
                        {{ map.rooms[roomIndex(cell)]?.name }}
                    </span>
                    <span v-if="has(map.blocked_cells, cell)" class="map-editor-block-marker" aria-hidden="true">×</span>
                    <span v-if="objectAt(cell)" class="map-editor-object" aria-hidden="true">
                        {{ objectAt(cell)?.blocking || objectAt(cell)?.occupiable === false ? '!' : '●' }}
                    </span>
                    <span v-if="objectAt(cell)" class="map-editor-object-name">{{ objectAt(cell)?.name }}</span>
                </button>
            </div>
        </div>

        <div class="map-editor-legends" aria-label="Условные обозначения карты">
            <span v-for="(item, index) in map.rooms" :key="item.code" class="map-editor-legend-item">
                <span class="map-editor-room-swatch" :class="`room-${index % 6}`" aria-hidden="true">{{ index + 1 }}</span>
                {{ item.name }}
            </span>
            <span class="map-editor-legend-item">
                <span class="map-editor-block-swatch" aria-hidden="true">×</span>
                Недоступная клетка
            </span>
            <span class="map-editor-legend-item">
                <span class="map-editor-object-swatch" aria-hidden="true">●</span>
                Используемый предмет (персонажа можно разместить)
            </span>
            <span class="map-editor-legend-item">
                <span class="map-editor-object-swatch is-unusable" aria-hidden="true">!</span>
                Неиспользуемый предмет (персонажа нельзя)
            </span>
            <span class="map-editor-legend-item">
                <span class="map-editor-wall-swatch" aria-hidden="true"></span>
                Стена между клетками
            </span>
        </div>

        <input type="hidden" name="map" :value="JSON.stringify(map)">
    </div>
</template>

<style scoped>
.map-editor { width: 100%; min-width: 0; }
.map-editor-tools { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; }
.map-editor-room { display: grid; max-width: 26rem; gap: 0.35rem; margin-bottom: 0.75rem; font-weight: 600; }
.map-editor-help { min-height: 1.5rem; margin: 0 0 0.65rem; color: #48574e; font-size: 0.9rem; }
.map-editor-viewport { max-width: 100%; overflow: auto; overscroll-behavior-inline: contain; border: 1px solid #89948e; border-radius: 0.5rem; background: #26352e; }
.map-editor-viewport:focus-visible { outline: 3px solid #326f60; outline-offset: 2px; }
.map-editor-grid { display: grid; gap: 2px; padding: 0.5rem; }
.map-editor-cell { position: relative; display: flex; min-height: 5rem; flex-direction: column; align-items: flex-start; justify-content: flex-start; gap: 0.25rem; overflow: hidden; padding: 0.35rem; border: 1px solid #78867d; border-radius: 0.2rem; color: #18251e; text-align: left; cursor: pointer; }
.map-editor-window { position: absolute; z-index: 1; background: #7cc3e8; box-shadow: 0 0 0 1px #2c6f95; }
.map-editor-window.is-north, .map-editor-window.is-south { right: 18%; left: 18%; height: 5px; }
.map-editor-window.is-west, .map-editor-window.is-east { top: 18%; bottom: 18%; width: 5px; }
.map-editor-window.is-north { top: 0; }
.map-editor-window.is-south { bottom: 0; }
.map-editor-window.is-west { left: 0; }
.map-editor-window.is-east { right: 0; }
.map-editor-cell:focus-visible { z-index: 2; outline: 3px solid #b67a19; outline-offset: -3px; }
.map-editor-coordinate { font-size: 0.7rem; font-weight: 800; }
.map-editor-room-name { max-width: 100%; overflow: hidden; font-size: 0.75rem; line-height: 1.1; text-overflow: ellipsis; }
.map-editor-cell.room-0, .map-editor-room-swatch.room-0 { background-color: #dcebee; }
.map-editor-cell.room-1, .map-editor-room-swatch.room-1 { background-color: #f2e4ce; }
.map-editor-cell.room-2, .map-editor-room-swatch.room-2 { background-color: #e5def0; }
.map-editor-cell.room-3, .map-editor-room-swatch.room-3 { background-color: #deeadb; }
.map-editor-cell.room-4, .map-editor-room-swatch.room-4 { background-color: #f0dfe2; }
.map-editor-cell.room-5, .map-editor-room-swatch.room-5 { background-color: #e2e5e3; }
.map-editor-cell.room-unassigned { background: #f7f4ea; }
.map-editor-cell.is-blocked { background: repeating-linear-gradient(135deg, #68756e 0 0.4rem, #d6d4c9 0.4rem 0.8rem); }
.map-editor-cell.is-wall-start { box-shadow: inset 0 0 0 3px #c88419; }
.map-editor-cell.has-blocking-object { box-shadow: inset 0 0 0 2px #8e352d; }
.map-editor-cell.has-room-top { border-top: 2px solid #677a6e; }
.map-editor-cell.has-room-right { border-right: 2px solid #677a6e; }
.map-editor-cell.has-room-bottom { border-bottom: 2px solid #677a6e; }
.map-editor-cell.has-room-left { border-left: 2px solid #677a6e; }
.map-editor-cell.has-wall-top { border-top: 5px solid #26352e; }
.map-editor-cell.has-wall-right { border-right: 5px solid #26352e; }
.map-editor-cell.has-wall-bottom { border-bottom: 5px solid #26352e; }
.map-editor-cell.has-wall-left { border-left: 5px solid #26352e; }
.map-editor-block-marker, .map-editor-object { position: absolute; right: 0.3rem; bottom: 0.2rem; font-size: 1.25rem; font-weight: 900; }
.map-editor-block-marker { color: #fff; text-shadow: 0 1px 3px #25332c; }
.map-editor-object { color: #81500c; }
.map-editor-cell.has-blocking-object .map-editor-object { color: #8e352d; }
.map-editor-legends { display: flex; flex-wrap: wrap; gap: 0.5rem 0.9rem; margin-top: 0.75rem; }
.map-editor-legend-item { display: inline-flex; align-items: center; gap: 0.35rem; color: #35443c; font-size: 0.8rem; }
.map-editor-room-swatch, .map-editor-block-swatch, .map-editor-object-swatch { display: inline-grid; width: 1.3rem; height: 1.3rem; place-items: center; border: 1px solid #718078; border-radius: 0.25rem; font-size: 0.7rem; font-weight: 800; }
.map-editor-block-swatch { background: repeating-linear-gradient(135deg, #68756e 0 0.25rem, #d6d4c9 0.25rem 0.5rem); color: #fff; text-shadow: 0 1px 2px #25332c; }
.map-editor-object-swatch { border-color: #a26a1d; background: #fff2d7; color: #70460f; }
.map-editor-object-swatch.is-unusable { border-color: #8e352d; background: #f5d9d4; color: #8e352d; }
.map-editor-object-name { max-width: 100%; overflow: hidden; color: #4d3b22; font-size: 0.7rem; line-height: 1.1; text-overflow: ellipsis; }
.map-editor-wall-swatch { display: inline-block; width: 1.3rem; height: 0.25rem; background: #26352e; }
</style>
