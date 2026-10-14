<script setup lang="ts">
import { computed } from 'vue';
import { plural } from '../composables/plural';
import { epilogueLines, epilogueTitle } from '../composables/epilogue';
import type { ChapterOutro, Ending, PuzzleToken, SolveReward } from '../types/puzzle';
import CaseSeal from './CaseSeal.vue';
import StorySpeaker from './StorySpeaker.vue';
import PersonAvatar from './PersonAvatar.vue';
import SiteImage from './SiteImage.vue';

/** The epilogue of a solved case: the culprit, the find on the empty cell, a few lines, the reward. */
const props = defineProps<{
    ending: Ending;
    type: string;
    tokens: PuzzleToken[];
    scene: { icon: string; label: string } | null;
    reward: SolveReward | null;
    /** A guest is told that signing in brings points. */
    guest: boolean;
    /** The closing lines of a story chapter. */
    chapter?: ChapterOutro | null;
    /** The free play is busy choosing the next case. */
    nextBusy?: boolean;
}>();

defineEmits<{ next: [] }>();

const lines = computed(() => epilogueLines(props.ending));
const title = computed(() => epilogueTitle(props.ending));
const culpritToken = computed(() => props.tokens.find((token) => token.name === props.ending.culprit) ?? null);
</script>

<template>
    <section class="ending-card" aria-live="polite">
        <CaseSeal class="ending-seal" code="solved" />
        <p class="eyebrow">Дело раскрыто</p>
        <h2 v-if="title">{{ title }}</h2>
        <div v-if="culpritToken" class="ending-culprit">
            <PersonAvatar class="ending-culprit-avatar" :name="culpritToken.name" :avatar="culpritToken.avatar" />
            <div>
                <span class="ending-label">Виновник</span>
                <strong>{{ culpritToken.name }}</strong>
            </div>
        </div>
        <p v-else-if="ending.culprit" class="ending-culprit-name"><span class="ending-label">Виновник</span> <strong>{{ ending.culprit }}</strong></p>
        <div v-if="scene" class="ending-find">
            <span class="ending-find-icon"><SiteImage :name="`finding:${type}`" :fallback="scene.icon" /></span>
            <div>
                <span class="ending-label">Находка на пустой клетке</span>
                <strong>{{ scene.label }}</strong>
            </div>
        </div>
        <p v-for="line in lines" :key="line" class="ending-line">{{ line }}</p>
        <ul v-if="reward" class="reward-chips" aria-label="Награда">
            <li class="reward-chip is-points"><strong>+{{ reward.points }}</strong> очков<template v-if="reward.bonus"> · бонус за решение без подсказок</template></li>
            <li v-if="reward.story_points" class="reward-chip is-points"><strong>+{{ reward.story_points }}</strong> {{ plural(reward.story_points, 'очко развития', 'очка развития', 'очков развития') }} за главу</li>
            <li class="reward-chip">Звание: <strong>{{ reward.player.rank.title }}</strong><CaseSeal v-if="reward.rank_up" class="rank-seal" code="rank" /></li>
            <li v-if="!reward.player.hints.locked" class="reward-chip">Новая подсказка — после ещё {{ reward.player.hints.next_in }} {{ plural(reward.player.hints.next_in, 'решённого дела', 'решённых дел', 'решённых дел') }}</li>
        </ul>
        <p v-else-if="guest" class="reward-line">Войдите через Telegram, чтобы такие дела приносили очки, звания и подсказки.</p>
        <blockquote v-if="chapter" class="ending-chapter">
            <p class="eyebrow">Глава {{ chapter.number }} · {{ chapter.title }}</p>
            <StorySpeaker :hero="chapter.speaker" />
            <p>{{ chapter.outro }}</p>
        </blockquote>
        <div class="ending-next">
            <a v-if="chapter && !chapter.last" class="primary-action" href="/story">Следующая глава <span aria-hidden="true">→</span></a>
            <a v-else-if="chapter" class="primary-action" href="/story">К пути детектива <span aria-hidden="true">→</span></a>
            <button v-if="!guest" class="primary-action" :class="{ 'is-quiet': chapter }" type="button" :disabled="nextBusy" @click="$emit('next')">{{ nextBusy ? 'Подбираем дело…' : 'Следующее дело' }}</button>
            <a class="secondary-action" href="/puzzles">К каталогу</a>
        </div>
    </section>
</template>
