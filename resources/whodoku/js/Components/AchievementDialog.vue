<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import type { SolveReward } from '../types/puzzle';
import StorySpeaker from './StorySpeaker.vue';

/**
 * A window after a solved case that brought a new level (with development points to spend) and, when the rank
 * changed too, the scene of the story: a few lines about the new rank. Nothing is shown otherwise.
 */
const props = defineProps<{ reward: SolveReward | null }>();

const dialog = ref<HTMLDialogElement | null>(null);

const levelUp = computed(() => props.reward?.level_up ?? null);
const promotion = computed(() => props.reward?.promotion ?? null);
const free = computed(() => props.reward?.player.development.free ?? 0);
const visible = computed(() => levelUp.value !== null || promotion.value !== null);

function pointsWord(count: number): string {
    const rest = count % 100;
    if (rest >= 11 && rest <= 14) return 'очков развития';

    return { 1: 'очко развития', 2: 'очка развития', 3: 'очка развития', 4: 'очка развития' }[count % 10] ?? 'очков развития';
}

watch(() => props.reward, async () => {
    if (!visible.value) return;
    await nextTick();
    dialog.value?.showModal?.();
}, { immediate: true });

function close(): void {
    dialog.value?.close?.();
}

/** A click on the backdrop closes the window. */
function backdropClick(event: MouseEvent): void {
    if (event.target === dialog.value) close();
}
</script>

<template>
    <dialog v-if="visible" ref="dialog" class="achievement-dialog" :class="{ 'is-scene': promotion }" aria-labelledby="achievement-title" @click="backdropClick">
        <div class="achievement-body">
            <template v-if="promotion">
                <p class="eyebrow">Новое звание</p>
                <h2 id="achievement-title" class="achievement-rank">{{ promotion.rank }}</h2>
                <StorySpeaker :hero="promotion.speaker" class="achievement-speaker" />
                <blockquote class="achievement-scene">{{ promotion.text }}</blockquote>
            </template>
            <template v-else>
                <p class="eyebrow">Новый уровень</p>
                <h2 id="achievement-title" class="achievement-rank">Уровень {{ levelUp?.to }}</h2>
            </template>
            <p v-if="levelUp" class="achievement-level">
                <template v-if="promotion">Уровень {{ levelUp.to }}. </template>
                +{{ levelUp.skill_points }} {{ pointsWord(levelUp.skill_points) }}<template v-if="free > 0"> — свободных: {{ free }}</template>
            </p>
            <div class="achievement-actions">
                <a v-if="free > 0" class="primary-action" href="/profile">Вложить очки в навыки <span aria-hidden="true">→</span></a>
                <button type="button" class="secondary-action" @click="close">Продолжить</button>
            </div>
        </div>
    </dialog>
</template>
