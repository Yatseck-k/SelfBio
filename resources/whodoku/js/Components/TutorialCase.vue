<script setup lang="ts">
import { computed, ref } from 'vue';
import PersonAvatar from './PersonAvatar.vue';
import PuzzleGrid from './PuzzleGrid.vue';
import { findEmptyCell } from '../composables/coverage';
import { tutorialPuzzle, tutorialSteps } from '../composables/tutorialCase';

const index = ref(0);
const step = computed(() => tutorialSteps[index.value]);

const scene = computed(() => {
    if (step.value.scene === 'none') {
        return null;
    }
    const cell = findEmptyCell(tutorialPuzzle, step.value.placements);

    return cell === null ? null : step.value.scene === 'body'
        ? { cell, icon: '⚰️', label: 'Тело жертвы' }
        : { cell, icon: '❔', label: 'Пустая клетка: здесь след преступления' };
});

const speakers = tutorialPuzzle.tokens.map((token, position) => ({
    token,
    quote: /«(.*)»/su.exec(tutorialPuzzle.clues[position]?.text ?? '')?.[1] ?? '',
}));

function go(delta: number): void {
    index.value = Math.min(tutorialSteps.length - 1, Math.max(0, index.value + delta));
}
</script>

<template>
    <section class="tutorial" aria-labelledby="tutorial-heading">
        <h2 id="tutorial-heading">Разбор примера: шаг за шагом</h2>
        <p>Учебное дело на карте 4×4. Листайте шаги: карта показывает, как расстановка и крестики ведут к виновному.</p>

        <ol class="tutorial-steps" aria-label="Шаги разбора">
            <li v-for="(item, position) in tutorialSteps" :key="item.title">
                <button type="button" :aria-current="position === index ? 'step' : undefined" @click="index = position">
                    {{ position + 1 }}. {{ item.title }}
                </button>
            </li>
        </ol>

        <div class="tutorial-stage">
            <PuzzleGrid
                :puzzle="tutorialPuzzle"
                :placements="step.placements"
                :notes="{}"
                :selected-token="null"
                :scene="scene"
                :highlight="step.highlight"
            />
            <div class="tutorial-side">
                <div class="tutorial-text" aria-live="polite">
                    <p class="eyebrow">Шаг {{ index + 1 }} из {{ tutorialSteps.length }}</p>
                    <h3>{{ step.title }}</h3>
                    <p>{{ step.text }}</p>
                    <div class="tutorial-nav">
                        <button type="button" class="secondary-action" :disabled="index === 0" @click="go(-1)">← Назад</button>
                        <button type="button" class="primary-action" :disabled="index === tutorialSteps.length - 1" @click="go(1)">Дальше →</button>
                    </div>
                </div>
                <h3>Показания</h3>
                <ul class="tutorial-clues">
                    <li v-for="speaker in speakers" :key="speaker.token.code">
                        <PersonAvatar class="clue-portrait" :name="speaker.token.name" :avatar="speaker.token.avatar" />
                        <span class="clue-speech">
                            <strong>{{ speaker.token.name }}</strong>
                            <span class="clue-quote">«{{ speaker.quote }}»</span>
                        </span>
                    </li>
                    <li v-if="tutorialPuzzle.victim" class="victim-card">
                        <PersonAvatar class="clue-portrait" :name="tutorialPuzzle.victim.name" :avatar="tutorialPuzzle.victim.avatar" />
                        <span class="clue-speech">
                            <span class="clue-role">{{ tutorialPuzzle.victim.role }}</span>
                            <strong>{{ tutorialPuzzle.victim.name }}</strong>
                            <span class="clue-quote">«{{ tutorialPuzzle.victim.statement }}»</span>
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
