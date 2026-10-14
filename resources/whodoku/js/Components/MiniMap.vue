<script setup lang="ts">
import { computed } from 'vue';

/**
 * A small diagram for the glossary. Every cell is three characters: a room letter, an icon and a mark.
 * Icons: `.` none, `c` armchair, `p` plant (cannot stand on it), `r` rug. Marks: `+` suitable (✓),
 * `-` not suitable (✕), `.` nothing, a capital letter is a guest.
 */
const props = defineProps<{
    rows: string[][];
    windows?: { row: number; col: number; side: 'north' | 'south' | 'west' | 'east' }[];
    caption?: string;
}>();

const ICONS: Record<string, string> = { c: '💺', p: '🌿', r: '🟫' };

interface Cell { room: string; icon: string; mark: string; borders: Record<string, boolean>; }

const cells = computed<Cell[]>(() => props.rows.flatMap((row, r) => row.map((code, c) => {
    const roomAt = (rr: number, cc: number): string | null => props.rows[rr]?.[cc]?.[0] ?? null;
    const room = code[0];

    return {
        room,
        icon: ICONS[code[1]] ?? '',
        mark: code[2] ?? '.',
        borders: {
            top: roomAt(r - 1, c) !== room, bottom: roomAt(r + 1, c) !== room,
            left: roomAt(r, c - 1) !== room, right: roomAt(r, c + 1) !== room,
        },
    };
})));

const columns = computed(() => props.rows[0]?.length ?? 1);

function windowsOf(index: number): string[] {
    const row = Math.floor(index / columns.value);
    const col = index % columns.value;

    return (props.windows ?? []).filter((item) => item.row === row && item.col === col).map((item) => item.side);
}
</script>

<template>
    <figure class="mini-map">
        <div class="mini-grid" :style="{ gridTemplateColumns: `repeat(${columns}, 2.4rem)` }" role="img" :aria-label="caption">
            <div
                v-for="(cell, index) in cells"
                :key="index"
                class="mini-cell"
                :class="[`mini-room-${cell.room}`, { 'b-top': cell.borders.top, 'b-bottom': cell.borders.bottom, 'b-left': cell.borders.left, 'b-right': cell.borders.right }]"
            >
                <span v-if="cell.icon" class="mini-icon">{{ cell.icon }}</span>
                <span v-if="cell.mark === '+'" class="mini-mark is-yes">✓</span>
                <span v-else-if="cell.mark === '-'" class="mini-mark is-no">✕</span>
                <span v-else-if="cell.mark !== '.'" class="mini-guest">{{ cell.mark }}</span>
                <span v-for="side in windowsOf(index)" :key="side" class="map-window" :class="`is-${side}`" />
            </div>
        </div>
        <figcaption v-if="caption">{{ caption }}</figcaption>
    </figure>
</template>
