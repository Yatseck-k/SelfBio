<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import PlayerBar from '../Components/PlayerBar.vue';
import DifficultyStars from '../Components/DifficultyStars.vue';
import CaseCard from '../Components/CaseCard.vue';
import TelegramLogin from '../Components/TelegramLogin.vue';
import { endless, playNext } from '../composables/useEndless';
import { plural } from '../composables/plural';
import { apiHeaders, loadPlayer, playerState } from '../composables/usePlayer';
import { isSolved } from '../composables/usePuzzleProgress';
import type { PuzzleSummary } from '../types/puzzle';

const puzzles = ref<PuzzleSummary[]>([]);
// Cases the signed-in player solved, as the server counts them.
const solvedOnServer = ref<string[]>([]);
// How many cases wait behind the sign-in (only guests are told).
const locked = ref(0);
const newToday = computed(() => puzzles.value.filter((puzzle) => puzzle.is_new));
const others = computed(() => puzzles.value.filter((puzzle) => !puzzle.is_new));

/** Levels of difficulty, the headings of the catalog. */
const LEVELS: Record<number, { title: string; note: string }> = {
    1: { title: 'Разминка', note: 'маленькая карта, прямые показания' },
    2: { title: 'Лёгкие', note: 'карта побольше или условия дела' },
    3: { title: 'Средние', note: 'условия дела и слабые показания: нужны вычёркивания и счёт' },
    4: { title: 'Сложные', note: 'большие карты, условия и тонкие намёки' },
    5: { title: 'Экспертные', note: 'всё сразу: без блокнота и автоотметки не обойтись' },
};

/** The cases that are not new, one group per difficulty, easiest first. */
const levels = computed(() => [...new Set(others.value.map((puzzle) => puzzle.difficulty))]
    .sort((a, b) => a - b)
    .map((level) => ({
        level,
        ...(LEVELS[level] ?? { title: `Уровень ${level}`, note: '' }),
        puzzles: others.value.filter((puzzle) => puzzle.difficulty === level),
    })));

async function reload(): Promise<void> {
    await load();
}
const loading = ref(true);
const error = ref(false);

function solved(puzzle: PuzzleSummary): boolean {
    if (solvedOnServer.value.includes(puzzle.slug)) {
        return true;
    }
    try {
        return isSolved(puzzle.slug, puzzle.revision);
    } catch {
        return false;
    }
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = false;

    try {
        await loadPlayer();
        const response = await globalThis.fetch('/api/puzzles', { headers: apiHeaders() });
        if (!response.ok) {
            throw new Error('Catalog request failed');
        }
        const payload = await response.json() as { data: PuzzleSummary[]; solved?: string[]; locked?: number };
        locked.value = payload.locked ?? 0;
        puzzles.value = payload.data;
        solvedOnServer.value = payload.solved ?? [];
    } catch {
        error.value = true;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <main class="public-page">
        <header class="public-header">
            <a href="/" class="site-home-link">SelfBio</a>
            <a href="/puzzles" class="brand">Whodoku</a>
            <span class="header-links"><a href="/rating" class="quiet-link">Рейтинг</a> <a href="/shop" class="quiet-link">Магазин</a> <a href="/rules" class="quiet-link">Правила</a> <PlayerBar /></span>
        </header>
        <section class="catalog-intro">
            <p class="eyebrow">Каталог</p>
            <h1>Выберите дело</h1>
            <p>Расставьте гостей на карте, сопоставьте показания и раскройте дело.</p>
            <div class="play-modes">
                <a class="primary-action" href="/story">Путь детектива <span aria-hidden="true">→</span></a>
                <button class="secondary-action" type="button" :disabled="endless.busy || (playerState.loaded && !playerState.player)" :title="playerState.player ? undefined : 'Свободная игра — после входа'" @click="playNext">{{ endless.busy ? 'Подбираем дело…' : 'Свободная игра: следующее дело' }}</button>
            </div>
            <p v-if="endless.error" class="login-error" role="alert">{{ endless.error }}</p>
        </section>
        <section v-if="locked > 0 && playerState.loaded && !playerState.player" class="state-card locked-banner" aria-live="polite">
            <h2>Ещё {{ locked }} {{ plural(locked, 'дело ждёт', 'дела ждут', 'дел ждут') }} за входом</h2>
            <p>Гостям открыты гостевые дела. Войдите через Telegram, чтобы открыть все дела, копить очки и звания и получать подсказки.</p>
            <TelegramLogin @signed-in="reload" />
        </section>
        <p v-if="loading" class="state-message" role="status">Загружаем дела…</p>
        <template v-else-if="puzzles.length">
            <nav v-if="levels.length + (newToday.length ? 1 : 0) > 1" class="level-nav" aria-label="Уровни сложности">
                <a v-if="newToday.length" href="#level-new" class="level-chip">Новые сегодня <b>{{ newToday.length }}</b></a>
                <a v-for="group in levels" :key="group.level" :href="`#level-${group.level}`" class="level-chip">{{ group.level }}. {{ group.title }} <b>{{ group.puzzles.length }}</b></a>
            </nav>
            <section v-if="newToday.length" id="level-new" class="level-section" aria-labelledby="level-new-title">
                <header class="level-heading"><h2 id="level-new-title">Новые сегодня</h2></header>
                <div class="puzzle-cards">
                    <CaseCard v-for="(puzzle, index) in newToday" :key="puzzle.slug" :puzzle="puzzle" :solved="solved(puzzle)" :index="index" />
                </div>
            </section>
            <section v-for="group in levels" :id="`level-${group.level}`" :key="group.level" class="level-section" :aria-labelledby="`level-${group.level}-title`">
                <header class="level-heading">
                    <h2 :id="`level-${group.level}-title`">Уровень {{ group.level }} · {{ group.title }}</h2>
                    <DifficultyStars :level="group.level" />
                    <p v-if="group.note">{{ group.note }}</p>
                </header>
                <div class="puzzle-cards">
                    <CaseCard v-for="(puzzle, index) in group.puzzles" :key="puzzle.slug" :puzzle="puzzle" :solved="solved(puzzle)" :index="index" />
                </div>
            </section>
        </template>
        <section v-else class="state-card">
            <h2>{{ error ? 'Не удалось загрузить каталог' : 'Дел пока нет' }}</h2>
            <p>{{ error ? 'Проверьте соединение и попробуйте снова.' : 'Первое опубликованное расследование появится здесь.' }}</p>
            <button v-if="error" class="primary-action" type="button" @click="load">Повторить</button>
        </section>
    </main>
</template>
