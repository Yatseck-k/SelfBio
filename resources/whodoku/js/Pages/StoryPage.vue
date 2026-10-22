<script setup lang="ts">
import { useBrand } from '../composables/useBrand';
import { computed, onMounted, ref } from 'vue';
import PlayerBar from '../Components/PlayerBar.vue';
import StorySpeaker from '../Components/StorySpeaker.vue';
import TelegramLogin from '../Components/TelegramLogin.vue';
import { apiHeaders, loadPlayer } from '../composables/usePlayer';
import { plural } from '../composables/plural';
import type { PlayerRank, StoryAct, StoryChapter } from '../types/puzzle';

const brand = useBrand();

const chapters = ref<StoryChapter[]>([]);
const acts = ref<StoryAct[]>([]);
const solvedCount = ref(0);
const total = ref(0);
const rank = ref<PlayerRank | null>(null);
const signedIn = ref(false);
const loading = ref(true);
const error = ref(false);

async function load(): Promise<void> {
    loading.value = true;
    error.value = false;
    try {
        await loadPlayer(true);
        const response = await globalThis.fetch('/api/story', { headers: apiHeaders() });
        if (!response.ok) throw new Error('Story request failed');
        const body = await response.json() as { signed_in: boolean; chapters: StoryChapter[]; acts?: StoryAct[]; solved?: number; total?: number; rank?: PlayerRank | null };
        chapters.value = body.chapters;
        acts.value = body.acts ?? [];
        solvedCount.value = body.solved ?? 0;
        total.value = body.total ?? body.chapters.length;
        rank.value = body.rank ?? null;
        signedIn.value = body.signed_in;
    } catch {
        error.value = true;
    } finally {
        loading.value = false;
    }
}

onMounted(load);

/** The chapters by act; without acts (or for a chapter outside them) everything is one list. */
const groups = computed(() => {
    if (acts.value.length === 0) {
        return [{ act: null as StoryAct | null, chapters: chapters.value }];
    }

    return acts.value
        .map((act) => ({ act: act as StoryAct | null, chapters: chapters.value.filter((chapter) => chapter.number >= act.from && chapter.number <= act.to) }))
        .filter((group) => group.chapters.length > 0);
});

const rankLine = computed(() => {
    if (rank.value === null) return '';
    const left = (rank.value.next_at ?? 0) - solvedCount.value;

    return rank.value.next_at ? `До звания «${rank.value.next_title}» — ${left} ${plural(left, 'глава', 'главы', 'глав')}` : 'Высшее звание получено';
});

const stateLabel: Record<StoryChapter['state'], string> = { locked: 'Закрыта', open: 'Открыта', solved: 'Пройдена' };
</script>

<template>
    <main class="public-page story-page">
        <header class="public-header">
            <a href="/" class="site-home-link">{{ brand }}</a>
            <a href="/puzzles" class="brand"><span aria-hidden="true">←</span> К делам</a>
            <span class="header-links"><a href="/puzzles" class="quiet-link">К игре</a> <a href="/rating" class="quiet-link">Рейтинг</a> <a href="/shop" class="quiet-link">Магазин</a> <a href="/rules" class="quiet-link">Правила</a> <PlayerBar /></span>
        </header>
        <section class="catalog-intro">
            <p class="eyebrow">Сюжет</p>
            <h1>Путь детектива</h1>
            <p>Пятьдесят глав в пяти актах: от стажёра до гроссмейстера дедукции. Первые десять глав учат играть и ведут до младшего детектива. Каждая глава — дело со своей историей; следующая открывается, когда раскрыта предыдущая. За главу даются двойные очки и очки развития. Звания детектив получает только здесь: звание растёт с каждой пройденной главой.</p>
        </section>
        <section v-if="!signedIn && !loading && !error" class="state-card" aria-live="polite">
            <h2>Сюжет открывается после входа</h2>
            <p>Войдите через Telegram: ваш детектив пройдёт путь от стажёра до суперсыщика, а прогресс сохранится.</p>
            <TelegramLogin @signed-in="load" />
        </section>
        <section v-if="signedIn && rank && !loading && !error" class="state-card story-summary" aria-label="Ваш путь">
            <p class="eyebrow">Звание: {{ rank.title }}</p>
            <div class="rank-bar" role="progressbar" :aria-valuenow="solvedCount" aria-valuemin="0" :aria-valuemax="total" :aria-label="rankLine">
                <span :style="{ width: `${total ? Math.round((solvedCount / total) * 100) : 0}%` }" />
            </div>
            <p class="quiet-note">Пройдено глав: {{ solvedCount }} из {{ total }} · {{ rankLine }}</p>
        </section>
        <p v-if="loading" class="state-message" role="status">Загружаем сюжет…</p>
        <section v-else-if="error" class="state-card">
            <h2>Не удалось загрузить сюжет</h2>
            <button class="primary-action" type="button" @click="load">Повторить</button>
        </section>
        <template v-else>
            <section v-for="(group, index) in groups" :key="group.act?.title ?? index" class="story-act" :aria-label="group.act?.title">
                <header v-if="group.act" class="story-act-heading">
                    <h2>{{ group.act.title }}</h2>
                    <p>{{ group.act.about }}</p>
                </header>
                <ol class="story-path">
                    <li v-for="chapter in group.chapters" :key="chapter.number" class="story-chapter" :class="`is-${chapter.state}`">
                        <header>
                            <span class="story-number" aria-hidden="true">{{ chapter.number }}</span>
                            <div>
                                <p class="eyebrow">глава {{ chapter.number }} · звание после главы: {{ chapter.rank }}</p>
                                <h3>{{ chapter.title }}</h3>
                            </div>
                            <span class="story-state">{{ stateLabel[chapter.state] }}</span>
                        </header>
                        <component :is="chapter.state === 'solved' ? 'details' : 'div'" class="story-body">
                            <summary v-if="chapter.state === 'solved'">Прочитать главу</summary>
                            <StorySpeaker v-if="chapter.briefing" :hero="chapter.speaker" />
                            <p v-if="chapter.briefing" class="story-briefing">{{ chapter.briefing }}</p>
                            <p v-else class="story-briefing is-hidden">🔒 Откроется после предыдущей главы.</p>
                            <p v-if="chapter.lesson" class="quiet-note story-lesson"><strong>Чему учит глава:</strong> {{ chapter.lesson }} Награда: +{{ chapter.grant }} {{ plural(chapter.grant, 'очко развития', 'очка развития', 'очков развития') }}.</p>
                            <p v-else-if="chapter.briefing && chapter.grant" class="quiet-note story-lesson">Награда: +{{ chapter.grant }} {{ plural(chapter.grant, 'очко развития', 'очка развития', 'очков развития') }}.</p>
                            <p v-if="chapter.outro" class="story-outro">{{ chapter.outro }}</p>
                        </component>
                        <a v-if="chapter.slug && chapter.state !== 'locked'" class="primary-action" :href="`/puzzles/${chapter.slug}`">{{ chapter.state === 'solved' ? 'Открыть дело снова' : 'Начать главу' }} <span aria-hidden="true">→</span></a>
                        <p v-else-if="chapter.state === 'open' && !chapter.built" class="quiet-note">Дело этой главы ещё готовится.</p>
                    </li>
                </ol>
            </section>
        </template>
    </main>
</template>
