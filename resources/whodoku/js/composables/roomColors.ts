import type { Cell } from '../types/puzzle';

export const ROOM_PALETTE_SIZE = 8;

// Same tints as the `.map-cell.room-N` classes in app.css, for SVG previews.
export const ROOM_PALETTE = ['#dcebee', '#f2e4ce', '#e5def0', '#deeadb', '#f0dfe2', '#e2e5e3', '#f6e9a8', '#cfe4f6'];

const key = (cell: Cell): string => `${cell[0]}:${cell[1]}`;

/**
 * Greedy graph colouring: rooms that share a border never get the same tint,
 * so neighbouring zones stay distinguishable without relying on the outline.
 */
export function roomColorIndexes(rooms: { cells: Cell[] }[]): number[] {
    const owner = new Map<string, number>();
    rooms.forEach((room, index) => room.cells.forEach((cell) => owner.set(key(cell), index)));

    const neighbours = rooms.map(() => new Set<number>());
    rooms.forEach((room, index) => room.cells.forEach((cell) => {
        for (const next of [[cell[0] + 1, cell[1]], [cell[0], cell[1] + 1]] as Cell[]) {
            const other = owner.get(key(next));
            if (other !== undefined && other !== index) {
                neighbours[index].add(other);
                neighbours[other].add(index);
            }
        }
    }));

    const colors: number[] = rooms.map(() => -1);
    const order = rooms.map((_, index) => index).sort((a, b) => neighbours[b].size - neighbours[a].size || a - b);
    for (const index of order) {
        const taken = new Set([...neighbours[index]].map((other) => colors[other]));
        let color = 0;
        while (taken.has(color) && color < ROOM_PALETTE_SIZE - 1) color++;
        colors[index] = color;
    }

    return colors;
}
