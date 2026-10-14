<script setup lang="ts">
import type { PuzzleSummary } from '../types/puzzle';
import { plural } from '../composables/plural';
import { puzzleTypeLabel } from '../composables/puzzleTypes';
import CaseSeal from './CaseSeal.vue';
import DifficultyStars from './DifficultyStars.vue';
import PuzzlePreview from './PuzzlePreview.vue';

/** A case in the catalog: type, preview of the map, teaser, size, difficulty and the way in. */
const props = defineProps<{ puzzle: PuzzleSummary; solved: boolean; index?: number }>();

/** What the case is called besides its type: today's case or one of the starting set. */
function collectionLabel(): string {
    if (props.puzzle.collection === 'daily') return 'Дело дня';
    if (props.puzzle.collection === 'base') return props.puzzle.guest_access ? '' : 'Базовое';

    return '';
}
</script>

<template>
    <article class="puzzle-card case-card" :class="{ 'is-solved': solved, 'is-locked': puzzle.locked }" :style="{ '--i': index ?? 0 }">
        <span class="case-pin" aria-hidden="true"></span>
        <div class="case-card-heading">
            <p class="eyebrow">{{ puzzleTypeLabel(puzzle.type) }}<span v-if="collectionLabel()" class="case-collection"> · {{ collectionLabel() }}</span></p>
            <CaseSeal v-if="solved" code="solved" />
            <CaseSeal v-else-if="puzzle.is_new" code="new" />
            <CaseSeal v-else-if="puzzle.collection === 'base' && puzzle.guest_access" code="guest" />
            <span v-else class="case-file-label">Дело</span>
        </div>
        <PuzzlePreview v-if="puzzle.preview" class="case-preview" :rows="puzzle.rows" :cols="puzzle.cols" :preview="puzzle.preview" />
        <h2>{{ puzzle.title }}</h2>
        <p>{{ puzzle.description || 'Описание дела появится перед началом расследования.' }}</p>
        <div class="case-card-meta">
            <span>{{ puzzle.rows }}×{{ puzzle.cols }} поле</span>
            <DifficultyStars :level="puzzle.difficulty" />
        </div>
        <p v-if="puzzle.locked" class="case-locked">
            <span aria-hidden="true">🔒</span>
            <template v-if="puzzle.unlock">Откроется при {{ puzzle.unlock.at }} {{ plural(puzzle.unlock.at, 'очке', 'очках', 'очках') }} навыка «{{ puzzle.unlock.label }}»</template>
            <template v-else>Пока закрыто</template>
            · <a href="/profile">развитие детектива</a>
        </p>
        <a v-else class="primary-action" :href="`/puzzles/${puzzle.slug}`">{{ solved ? 'Открыть снова' : 'Открыть дело' }} <span aria-hidden="true">→</span></a>
    </article>
</template>
