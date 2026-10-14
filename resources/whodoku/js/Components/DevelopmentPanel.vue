<script setup lang="ts">
import { computed, ref } from 'vue';
import { resetSkills, spendSkillPoint } from '../composables/usePlayer';
import type { Development, PlayerLevel } from '../types/puzzle';

/**
 * Development of the detective: every level gives a point, which goes into a skill; a skill opens abilities
 * at its thresholds. The server decides everything, the panel only shows it and sends the choices.
 */
const props = defineProps<{ development: Development; level: PlayerLevel }>();

const error = ref('');
const busy = ref(false);

const resetNote = computed(() => {
    const reset = props.development.reset;
    if (reset.allowed) return 'Очки можно перераспределить, но потом — только раз в 20 уровней.';
    if (reset.next_level) return `Перераспределить очки можно будет на уровне ${reset.next_level}.`;

    return '';
});

async function spend(skill: string): Promise<void> {
    busy.value = true;
    error.value = await spendSkillPoint(skill) ?? '';
    busy.value = false;
}

async function reset(): Promise<void> {
    busy.value = true;
    error.value = await resetSkills() ?? '';
    busy.value = false;
}

/** The share of a skill bar that is filled: up to the last threshold. */
function fill(points: number, steps: { at: number }[]): number {
    const last = steps[steps.length - 1]?.at ?? 1;

    return Math.min(100, Math.round((points / last) * 100));
}
</script>

<template>
    <section class="development" aria-labelledby="development-heading">
        <header class="development-heading">
            <h2 id="development-heading">Развитие детектива</h2>
            <p class="development-level">Уровень <strong>{{ level.level }}</strong> из {{ level.max }}</p>
        </header>
        <p class="quiet-note">Каждый новый уровень даёт очко развития. Вложите его в навык — он откроет новые возможности. Свободных очков: <strong class="development-free">{{ development.free }}</strong>.</p>
        <div class="skill-grid">
            <article v-for="skill in development.skills" :key="skill.key" class="skill-card">
                <header>
                    <h3>{{ skill.label }}</h3>
                    <span class="skill-points">{{ skill.points }}</span>
                </header>
                <p class="skill-about">{{ skill.about }}</p>
                <div class="skill-bar" aria-hidden="true"><span :style="{ width: `${fill(skill.points, skill.steps)}%` }" /></div>
                <ul class="skill-steps">
                    <li v-for="step in skill.steps" :key="step.at" :class="{ 'is-open': step.open }">
                        <span class="skill-step-mark" aria-hidden="true">{{ step.open ? '✓' : '🔒' }}</span>
                        <span>{{ step.title }} <small>— {{ step.at }}</small></span>
                    </li>
                </ul>
                <button type="button" class="primary-action skill-add" :disabled="development.free < 1 || busy" :aria-label="`Вложить очко в навык ${skill.label}`" @click="spend(skill.key)">+ Вложить очко</button>
            </article>
        </div>
        <div class="development-reset">
            <button v-if="development.reset.allowed" type="button" class="secondary-action" :disabled="busy" @click="reset">Перераспределить очки</button>
            <p v-if="resetNote" class="quiet-note">{{ resetNote }}</p>
        </div>
        <p v-if="error" class="login-error" role="alert">{{ error }}</p>
    </section>
</template>
