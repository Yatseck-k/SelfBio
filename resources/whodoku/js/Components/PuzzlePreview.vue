<script setup lang="ts">
import { computed } from 'vue';
import { ROOM_PALETTE, roomColorIndexes } from '../composables/roomColors';
import type { Cell, PuzzlePreview } from '../types/puzzle';

const props = defineProps<{ rows: number; cols: number; preview: PuzzlePreview }>();

const key = (cell: Cell): string => `${cell[0]}:${cell[1]}`;

const owner = computed(() => {
    const map = new Map<string, number>();
    props.preview.rooms.forEach((room, index) => room.cells.forEach((cell) => map.set(key(cell), index)));

    return map;
});

const colors = computed(() => roomColorIndexes(props.preview.rooms));

const cells = computed(() => props.preview.rooms.flatMap((room, index) => room.cells.map((cell) => ({
    key: key(cell), x: cell[1], y: cell[0], fill: ROOM_PALETTE[colors.value[index]] ?? ROOM_PALETTE[0],
}))));

// Dark outline wherever two neighbouring cells belong to different rooms (or the map edge).
const borders = computed(() => {
    const lines: { key: string; x1: number; y1: number; x2: number; y2: number }[] = [];
    const roomOf = (row: number, col: number): number => owner.value.get(`${row}:${col}`) ?? -1;
    for (let row = 0; row < props.rows; row++) {
        for (let col = 0; col < props.cols; col++) {
            const here = roomOf(row, col);
            if (roomOf(row, col + 1) !== here || col + 1 === props.cols) {
                lines.push({ key: `v${row}:${col}`, x1: col + 1, y1: row, x2: col + 1, y2: row + 1 });
            }
            if (roomOf(row + 1, col) !== here || row + 1 === props.rows) {
                lines.push({ key: `h${row}:${col}`, x1: col, y1: row + 1, x2: col + 1, y2: row + 1 });
            }
        }
    }

    return lines;
});

const walls = computed(() => props.preview.walls.map((wall, index) => {
    const vertical = wall.from[0] === wall.to[0];
    const row = Math.max(wall.from[0], wall.to[0]);
    const col = Math.max(wall.from[1], wall.to[1]);

    return vertical
        ? { key: `w${index}`, x1: col, y1: wall.from[0], x2: col, y2: wall.from[0] + 1 }
        : { key: `w${index}`, x1: wall.from[1], y1: row, x2: wall.from[1] + 1, y2: row };
}));
</script>

<template>
    <svg
        class="puzzle-preview"
        :viewBox="`-0.06 -0.06 ${cols + 0.12} ${rows + 0.12}`"
        role="img"
        :aria-label="`Схема карты ${rows}×${cols}, комнат: ${preview.rooms.length}`"
        preserveAspectRatio="xMidYMid meet"
    >
        <defs>
            <pattern id="preview-blocked" width="0.3" height="0.3" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                <rect width="0.3" height="0.3" fill="#b9ad9a" />
                <rect width="0.1" height="0.3" fill="#6b4a33" />
            </pattern>
        </defs>
        <rect v-for="cell in cells" :key="cell.key" :x="cell.x" :y="cell.y" width="1" height="1" :fill="cell.fill" />
        <rect v-for="cell in preview.blocked" :key="`b${key(cell)}`" :x="cell[1]" :y="cell[0]" width="1" height="1" fill="url(#preview-blocked)" />
        <g stroke="#2f3a34" stroke-width="0.07" stroke-linecap="square">
            <line v-for="line in borders" :key="line.key" :x1="line.x1" :y1="line.y1" :x2="line.x2" :y2="line.y2" />
        </g>
        <g stroke="#49311f" stroke-width="0.16" stroke-linecap="butt">
            <line v-for="line in walls" :key="line.key" :x1="line.x1" :y1="line.y1" :x2="line.x2" :y2="line.y2" />
        </g>
    </svg>
</template>
