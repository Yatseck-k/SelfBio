<script setup lang="ts">
import type { PlacementMap, PuzzleToken } from '../types/puzzle';
import PersonAvatar from './PersonAvatar.vue';

defineProps<{
    tokens: PuzzleToken[];
    placements: PlacementMap;
    selectedToken: string | null;
    /** How many cells each guest can still take, when the notebook helper is on. */
    candidateCounts?: Record<string, number> | null;
    /** What a placed guest does on the object under him («Сидит: садовая скамья»). */
    poses?: Record<string, string>;
}>();

defineEmits<{ select: [code: string] }>();

</script>

<template>
    <section class="game-panel token-panel" aria-labelledby="tokens-heading">
        <div class="panel-heading">
            <h2 id="tokens-heading">Свидетели</h2>
            <span class="panel-kicker">{{ tokens.length }} в деле</span>
        </div>
        <div class="token-list">
            <button
                v-for="token in tokens"
                :key="token.code"
                class="token-button"
                :class="{ 'is-selected': selectedToken === token.code, 'is-placed': placements[token.code] }"
                type="button"
                :aria-pressed="selectedToken === token.code"
                @click="$emit('select', token.code)"
            >
                <span class="token-avatar" aria-hidden="true">
                    <PersonAvatar :name="token.name" :avatar="token.avatar" />
                </span>
                <span class="token-copy">
                    <strong>{{ token.name }}</strong>
                    <small>{{ placements[token.code] ? (poses?.[token.code] ?? 'На карте') : 'Не размещён' }}<template v-if="candidateCounts && !placements[token.code]"> · клеток: {{ candidateCounts[token.code] }}</template></small>
                </span>
                <span class="token-status" :aria-label="placements[token.code] ? 'размещён' : 'не размещён'">{{ placements[token.code] ? '✓' : '·' }}</span>
            </button>
        </div>
    </section>
</template>
