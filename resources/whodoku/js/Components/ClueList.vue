<script setup lang="ts">
import { computed } from 'vue';
import PersonAvatar from './PersonAvatar.vue';
import type { ClueTarget, PuzzleToken, Victim } from '../types/puzzle';

const props = withDefaults(defineProps<{
    clues: { text: string; sort_order: number; constraint_type?: string; constraint?: Record<string, unknown> }[];
    usedClues: number[];
    tokens?: PuzzleToken[];
    victim?: Victim | null;
    /** The statement whose objects are pinned on the map. */
    pinned?: number | null;
    /** A statement may light up what it names on the map: a strong hint, so it is an ability, off by default in the game. */
    locate?: boolean;
}>(), { locate: true });

/** Conditions of the whole case (rooms, limits) are not statements of a guest: they are listed apart and are not ticked off. */
const CONDITION_TYPES = ['EVERY_ROOM_HAS_TOKEN', 'ROOM_TOKEN_LIMIT', 'ROOM_TOKEN_COUNT'];

const conditions = computed(() => props.clues.filter((clue) => CONDITION_TYPES.includes(clue.constraint_type ?? '')));
const statementCount = computed(() => props.clues.length - conditions.value.length);
const usedCount = computed(() => props.usedClues.length);

/** «Из записей следователя: в каждой комнате…» → «В каждой комнате…» */
function conditionText(text: string): string {
    const body = text.replace(/^Из записей следователя:\s*/u, '');

    return body.charAt(0).toUpperCase() + body.slice(1);
}

function isStatement(index: number): boolean {
    return !CONDITION_TYPES.includes(props.clues[index]?.constraint_type ?? '');
}

const emit = defineEmits<{
    toggle: [index: number];
    preview: [target: ClueTarget | null];
    pin: [index: number];
}>();

/** The guest who gives the statement, found by the token code of its constraint. */
function speakerOf(index: number): PuzzleToken | undefined {
    const code = props.clues[index]?.constraint?.token;

    return props.tokens?.find((token) => token.code === code);
}

/** The quoted words of «Имя утверждает: «…»»; the whole text when it has another shape. */
function quoteOf(index: number): string {
    const text = props.clues[index]?.text ?? '';

    return /«(.*)»/su.exec(text)?.[1] ?? text;
}

/** What a statement points at on the map: the named object and/or the named room. */
function targetOf(index: number): ClueTarget | null {
    const constraint = props.clues[index]?.constraint ?? {};
    const object = typeof constraint.object === 'string' ? constraint.object : null;
    const room = typeof constraint.room === 'string' ? constraint.room : null;

    const nearWindow = props.clues[index]?.constraint_type === 'TOKEN_NEAR_WINDOW';

    return object === null && room === null && !nearWindow ? null : { objects: object === null ? [] : [object], room, nearWindow };
}
</script>

<template>
    <section class="game-panel clue-panel" aria-labelledby="clues-heading">
        <div class="clue-heading">
            <h2 id="clues-heading">Показания</h2>
            <span class="clue-progress">{{ usedCount }} из {{ statementCount }}</span>
        </div>
        <p v-if="locate" class="clue-note">Наведите на показание или нажмите «На карте» — названный предмет, комната или окно подсветятся. «Возле окна» — любая из двух клеток по сторонам окна.</p>
        <article v-if="victim" class="victim-card">
            <PersonAvatar class="clue-portrait" :name="victim.name" :avatar="victim.avatar" />
            <div class="clue-speech">
                <p class="clue-role">{{ victim.role }}</p>
                <strong>{{ victim.name }}</strong>
                <p class="clue-quote">«{{ victim.statement }}»</p>
            </div>
        </article>
        <section v-if="conditions.length > 0" class="case-conditions" aria-labelledby="conditions-heading">
            <h3 id="conditions-heading">Условия дела</h3>
            <ul>
                <li v-for="condition in conditions" :key="condition.sort_order">{{ conditionText(condition.text) }}</li>
            </ul>
        </section>
        <ol class="clue-list">
            <template v-for="(clue, index) in clues" :key="clue.sort_order">
            <li
                v-if="isStatement(index)"
                class="clue-card"
                :class="{ 'is-used': usedClues.includes(index), 'is-pinned': pinned === index }"
                @mouseenter="locate && emit('preview', targetOf(index))"
                @mouseleave="locate && emit('preview', null)"
                @focusin="locate && emit('preview', targetOf(index))"
                @focusout="locate && emit('preview', null)"
            >
                <label>
                    <span class="clue-index" aria-hidden="true">{{ String(index + 1).padStart(2, '0') }}</span>
                    <input
                        type="checkbox"
                        :checked="usedClues.includes(index)"
                        @change="emit('toggle', index)"
                    >
                    <template v-if="speakerOf(index)">
                        <PersonAvatar class="clue-portrait" :name="speakerOf(index)!.name" :avatar="speakerOf(index)!.avatar" />
                        <span class="clue-speech">
                            <strong>{{ speakerOf(index)!.name }}</strong>
                            <span class="clue-quote">«{{ quoteOf(index) }}»</span>
                        </span>
                    </template>
                    <span v-else>{{ clue.text }}</span>
                </label>
                <button
                    v-if="locate && targetOf(index) !== null"
                    type="button"
                    class="clue-locate"
                    :aria-pressed="pinned === index"
                    @click="emit('pin', index)"
                >{{ pinned === index ? 'Скрыть' : 'На карте' }}</button>
            </li>
            </template>
        </ol>
    </section>
</template>
