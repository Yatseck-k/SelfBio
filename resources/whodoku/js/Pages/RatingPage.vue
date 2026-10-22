<script setup lang="ts">
import { useBrand } from '../composables/useBrand';
import { onMounted, ref, watch } from 'vue';
import PersonAvatar from '../Components/PersonAvatar.vue';
import PlayerBar from '../Components/PlayerBar.vue';
import { apiHeaders, loadPlayer } from '../composables/usePlayer';
import type { LeaderboardRow } from '../types/puzzle';

const brand = useBrand();

const period = ref<'week' | 'all'>('all');
const rows = ref<LeaderboardRow[]>([]);
const me = ref<LeaderboardRow | null>(null);
const loading = ref(true);
const failed = ref(false);

async function load(): Promise<void> {
    loading.value = true;
    failed.value = false;
    try {
        const response = await globalThis.fetch(`/api/leaderboard?period=${period.value}`, { headers: apiHeaders() });
        if (!response.ok) throw new Error('Leaderboard failed');
        const payload = await response.json() as { players: LeaderboardRow[]; me: LeaderboardRow | null };
        rows.value = payload.players;
        me.value = payload.me;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

onMounted(async () => { await loadPlayer(); await load(); });
watch(period, load);
</script>

<template>
    <main class="public-page rating-page">
        <header class="public-header">
            <a href="/" class="site-home-link">{{ brand }}</a>
            <a href="/puzzles" class="brand">Whodoku</a>
            <span class="header-links"><a href="/puzzles" class="quiet-link">К игре</a> <a href="/shop" class="quiet-link">Магазин</a> <a href="/rules" class="quiet-link">Правила</a> <PlayerBar /></span>
        </header>

        <section class="catalog-intro">
            <p class="eyebrow">Рейтинг</p>
            <h1>Лучшие детективы</h1>
            <p>Очки даются за первое верное прохождение дела: 10 за каждую звезду сложности и ещё четверть, если вы справились без подсказок.</p>
            <div class="chip-row" role="group" aria-label="Период">
                <button type="button" :class="{ 'is-active': period === 'all' }" :aria-pressed="period === 'all'" @click="period = 'all'">За всё время</button>
                <button type="button" :class="{ 'is-active': period === 'week' }" :aria-pressed="period === 'week'" @click="period = 'week'">За неделю</button>
            </div>
        </section>

        <p v-if="loading" class="state-message" role="status">Загружаем рейтинг…</p>
        <section v-else-if="failed" class="state-card"><h2>Не удалось загрузить рейтинг</h2><button class="primary-action" type="button" @click="load">Повторить</button></section>
        <section v-else-if="rows.length === 0" class="state-card"><h2>Пока никого нет</h2><p>Станьте первым: войдите, создайте детектива и решите дело.</p></section>
        <ol v-else class="leaderboard">
            <li v-for="row in rows" :key="row.position" :class="{ 'is-me': me?.position === row.position }">
                <span class="leaderboard-place">{{ row.position }}</span>
                <PersonAvatar class="leaderboard-avatar" :name="row.name" :avatar="row.avatar" />
                <span class="leaderboard-name"><strong>{{ row.name }}</strong><small>{{ row.title }} · решено {{ row.solved }}</small></span>
                <span class="leaderboard-points">{{ row.points }}</span>
            </li>
        </ol>
        <p v-if="me && me.position > rows.length" class="quiet-note">Ваше место — {{ me.position }} ({{ me.points }} очков).</p>
    </main>
</template>
