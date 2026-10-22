<script setup lang="ts">
import { useBrand } from '../composables/useBrand';
import { computed, onMounted, ref, watch } from 'vue';
import BuyHintsDialog from '../Components/BuyHintsDialog.vue';
import DetectiveForm from '../Components/DetectiveForm.vue';
import DevelopmentPanel from '../Components/DevelopmentPanel.vue';
import PersonAvatar from '../Components/PersonAvatar.vue';
import PlayerBar from '../Components/PlayerBar.vue';
import TelegramLogin from '../Components/TelegramLogin.vue';
import { plural } from '../composables/plural';
import { puzzleTypeLabel } from '../composables/puzzleTypes';
import { apiHeaders, deleteAccount, loadPlayer, playerState } from '../composables/usePlayer';
import type { PlayerStats } from '../types/puzzle';

const brand = useBrand();

const stats = ref<PlayerStats | null>(null);
const editing = ref(false);
const buying = ref(false);
const loading = ref(true);
const confirmingDelete = ref(false);
const deleteError = ref('');

async function removeAccount(): Promise<void> {
    deleteError.value = await deleteAccount() ?? '';
    if (deleteError.value === '') {
        globalThis.location.assign('/puzzles');
    }
}

const player = computed(() => playerState.player);
const rankLine = computed(() => {
    const rank = player.value?.rank;

    const left = (rank?.next_at ?? 0) - (player.value?.chapters ?? 0);

    return rank?.next_at ? `До звания «${rank.next_title}» — ${left} ${plural(left, 'глава', 'главы', 'глав')} сюжета` : 'Высшее звание достигнуто';
});

async function loadStats(): Promise<void> {
    if (player.value === null) {
        stats.value = null;

        return;
    }
    const response = await globalThis.fetch('/player/stats', { headers: apiHeaders() });
    if (response.ok) {
        stats.value = (await response.json() as { stats: PlayerStats }).stats;
    }
}

function duration(seconds: number | null): string {
    if (seconds === null) return '—';
    const minutes = Math.floor(seconds / 60);

    return minutes >= 60 ? `${Math.floor(minutes / 60)} ч ${minutes % 60} мин` : minutes > 0 ? `${minutes} мин ${seconds % 60} с` : `${seconds} с`;
}

onMounted(async () => {
    await loadPlayer();
    await loadStats();
    loading.value = false;
});
watch(() => playerState.player?.id, () => { void loadStats(); });
</script>

<template>
    <main class="public-page profile-page">
        <header class="public-header">
            <a href="/" class="site-home-link">{{ brand }}</a>
            <a href="/puzzles" class="brand">Whodoku</a>
            <span class="header-links"><a href="/puzzles" class="quiet-link">К игре</a> <a href="/rating" class="quiet-link">Рейтинг</a> <a href="/shop" class="quiet-link">Магазин</a> <a href="/rules" class="quiet-link">Правила</a> <PlayerBar /></span>
        </header>

        <p v-if="loading" class="state-message" role="status">Загружаем профиль…</p>

        <section v-else-if="!player" class="state-card">
            <h1>Профиль детектива</h1>
            <p>Войдите через Telegram, чтобы получить детектива, копить очки и звания, подсказки и статистику.</p>
            <TelegramLogin />
        </section>

        <section v-else-if="!player.detective || editing" class="state-card">
            <h1>{{ player.detective ? 'Изменить детектива' : 'Создайте детектива' }}</h1>
            <p v-if="!player.detective">Это ваш игровой персонаж: имя, под которым вы расследуете дела, и внешность.</p>
            <DetectiveForm @saved="editing = false; loadStats()" />
        </section>

        <template v-else>
            <section class="profile-hero">
                <PersonAvatar class="profile-avatar" :name="player.detective.name" :avatar="player.detective.avatar" />
                <div class="profile-hero-text">
                    <p class="eyebrow">{{ player.rank.title }}</p>
                    <h1>{{ player.detective.name }}</h1>
                    <div class="rank-bar" role="progressbar" :aria-valuenow="Math.round(player.rank.progress * 100)" aria-valuemin="0" aria-valuemax="100" :aria-label="rankLine">
                        <span :style="{ width: `${Math.round(player.rank.progress * 100)}%` }" />
                    </div>
                    <p class="quiet-note">{{ player.points }} очков · пройдено глав: {{ player.chapters }} · {{ rankLine }}</p>
                    <button type="button" class="secondary-action" @click="editing = true">Изменить детектива</button>
                </div>
            </section>

            <DevelopmentPanel :development="player.development" :level="player.level" />

            <section v-if="stats" class="stat-grid" aria-label="Статистика">
                <article class="stat-card"><strong>{{ stats.solved }}</strong><span>решено дел</span></article>
                <article class="stat-card"><strong>{{ stats.streak.current }}</strong><span>дней подряд (лучшая серия {{ stats.streak.best }})</span></article>
                <article class="stat-card"><strong>{{ duration(stats.average_seconds) }}</strong><span>среднее время (лучшее {{ duration(stats.best_seconds) }})</span></article>
                <article class="stat-card"><strong>{{ stats.hints.available }}</strong><span v-if="stats.hints.locked">подсказок в запасе · дальше их открывает навык «Внимательность»</span><span v-else>подсказок в запасе · новая через {{ stats.hints.next_in }} {{ stats.hints.next_in === 1 ? 'дело' : 'дела' }}</span><button v-if="!stats.hints.locked" type="button" class="secondary-action buy-hints" @click="buying = true">Купить подсказки</button><a v-else class="secondary-action buy-hints" href="/shop">Магазин подсказок</a></article>
            </section>

            <section v-if="stats && stats.solved > 0" class="profile-columns">
                <article class="term-card">
                    <h2>По типам дел</h2>
                    <ul class="plain-list"><li v-for="(count, type) in stats.by_type" :key="type"><strong>{{ puzzleTypeLabel(String(type)) }}</strong> — {{ count }}</li></ul>
                </article>
                <article class="term-card">
                    <h2>По сложности</h2>
                    <ul class="plain-list"><li v-for="(count, level) in stats.by_difficulty" :key="level"><strong>{{ '★'.repeat(Number(level)) }}</strong> — {{ count }}</li></ul>
                </article>
            </section>

            <section v-if="stats && stats.recent.length" aria-labelledby="recent-heading">
                <h2 id="recent-heading">Последние дела</h2>
                <ol class="recent-list">
                    <li v-for="item in stats.recent" :key="item.slug + item.solved_at">
                        <a :href="`/puzzles/${item.slug}`"><strong>{{ item.title }}</strong></a>
                        <span>{{ puzzleTypeLabel(item.type) }} · {{ '★'.repeat(item.difficulty) }} · {{ duration(item.duration_seconds) }} · {{ item.hints_used ? `подсказок: ${item.hints_used}` : 'без подсказок' }}</span>
                        <em>+{{ item.points }}</em>
                    </li>
                </ol>
            </section>
            <section v-else-if="stats" class="state-card">
                <h2>Пока ничего не решено</h2>
                <p>Решите первое дело — и очки, звание и статистика начнут копиться.</p>
                <a class="primary-action" href="/puzzles">К делам →</a>
            </section>

            <section class="data-card" aria-labelledby="data-heading">
                <h2 id="data-heading">Ваши данные</h2>
                <p>Мы храним только нужное для игры: номер в Telegram, имя и фото профиля (как в Telegram), вашего детектива, очки и историю прохождений. Рейтинг показывает только имя детектива.</p>
                <template v-if="!confirmingDelete">
                    <button type="button" class="secondary-action" @click="confirmingDelete = true">Удалить аккаунт…</button>
                </template>
                <template v-else>
                    <p class="login-error" role="alert">Аккаунт, очки, звание и вся статистика будут удалены без возможности восстановления.</p>
                    <div class="chip-row">
                        <button type="button" class="danger-action" @click="removeAccount">Да, удалить навсегда</button>
                        <button type="button" @click="confirmingDelete = false">Отмена</button>
                    </div>
                    <p v-if="deleteError" class="login-error" role="alert">{{ deleteError }}</p>
                </template>
            </section>
        </template>
        <BuyHintsDialog v-model="buying" />
    </main>
</template>
