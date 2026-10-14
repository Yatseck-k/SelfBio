import { createApp } from 'vue';
import OrchidPuzzleMapEditor from './Components/OrchidPuzzleMapEditor.vue';

const root = document.getElementById('killdoku-map-editor');
if (root) {
    createApp(OrchidPuzzleMapEditor, {
        puzzle: JSON.parse(root.dataset.puzzle ?? '{}'),
        initialMap: JSON.parse(root.dataset.map ?? '{}'),
        kinds: JSON.parse(root.dataset.kinds ?? '[]'),
    }).mount(root);
}
