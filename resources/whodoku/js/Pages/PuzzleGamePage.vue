<script setup lang="ts">
import { plural } from '../composables/plural';
import { culpritAccusative, puzzleTypeLabel } from '../composables/puzzleTypes';
import { computed, nextTick, onMounted, ref } from 'vue';
import ClueList from '../Components/ClueList.vue';
import PlayerBar from '../Components/PlayerBar.vue';
import TelegramLogin from '../Components/TelegramLogin.vue';
import PuzzleGrid from '../Components/PuzzleGrid.vue';
import AchievementDialog from '../Components/AchievementDialog.vue';
import StorySpeaker from '../Components/StorySpeaker.vue';
import DifficultyStars from '../Components/DifficultyStars.vue';
import EndingCard from '../Components/EndingCard.vue';
import NotebookPanel from '../Components/NotebookPanel.vue';
import TokenPanel from '../Components/TokenPanel.vue';
import { abilities } from '../composables/abilities';
import { endless, playNext } from '../composables/useEndless';
import { useNotebook } from '../composables/notebook';
import { poseText } from '../composables/pose';
import { coveredCells, findEmptyCell } from '../composables/coverage';
import BuyHintsDialog from '../Components/BuyHintsDialog.vue';
import { apiHeaders, loadPlayer, playerState } from '../composables/usePlayer';
import { usePuzzleProgress } from '../composables/usePuzzleProgress';
import type { Cell, ChapterOutro, ClueTarget, Ending, HintBalance, PuzzleDetail, SolveReward } from '../types/puzzle';

const props = defineProps<{ slug: string }>();
const puzzle = ref<PuzzleDetail | null>(null);
const loading = ref(true);
const error = ref(false);
const selectedToken = ref<string | null>(null);
const mode = ref<'place' | 'note'>('place');
const message = ref('');
const checking = ref(false);
const requestingHint = ref(false);
const buying = ref(false);
const MAX_HINTS = 3;
const { progress, load: loadProgress, save } = usePuzzleProgress();
const reward = ref<SolveReward | null>(null);
// The closing lines of the story chapter, after the correct answer.
const chapterOutro = ref<ChapterOutro | null>(null);
// A guest asked for a case that opens after the sign-in.
const needsLogin = ref(false);
// A signed-in player whose intellect does not open this difficulty yet: what to develop.
const levelLocked = ref<string | null>(null);

function requestErrorMessage(status: number, fallback: string): string {
    if (status === 429) return 'Слишком много запросов. Подождите минуту и попробуйте снова.';
    if (status === 404) return 'Дело больше недоступно. Вернитесь в каталог.';

    return fallback;
}

const scene = computed(() => {
    const found = progress.ending?.scene as { cell?: Cell; icon?: string; label?: string } | undefined;
    if (found?.cell && found.icon) {
        return { cell: found.cell, icon: found.icon, label: found.label ?? 'След преступления' };
    }

    // Everybody is placed: the one cell left uncovered is where the crime left its trace.
    const empty = puzzle.value === null ? null : findEmptyCell(puzzle.value, progress.placements);

    return empty === null ? null : { cell: empty, icon: '❔', label: 'Пустая клетка: здесь след преступления' };
});
const previewTarget = ref<ClueTarget | null>(null);
const pinnedClue = ref<number | null>(null);
const highlight = computed<ClueTarget | null>(() => {
    if (previewTarget.value !== null) {
        return previewTarget.value;
    }
    const pinned = pinnedClue.value === null ? undefined : puzzle.value?.clues[pinnedClue.value];
    const constraint = pinned?.constraint;
    if (pinned === undefined || constraint === undefined) {
        return null;
    }

    return {
        objects: typeof constraint.object === 'string' ? [constraint.object] : [],
        room: typeof constraint.room === 'string' ? constraint.room : null,
        nearWindow: pinned.constraint_type === 'TOKEN_NEAR_WINDOW',
    };
});

function pinClue(index: number): void {
    pinnedClue.value = pinnedClue.value === index ? null : index;
}

const { helperOn, candidatesByToken, candidateKeys, helperNote, toggleHelper, markCandidates, clearNotes, tapNote } = useNotebook(puzzle, progress, selectedToken, save, computed(() => abilities.helper));

/** Guests standing on furniture use it: «Сидит: садовая скамья». */
const poses = computed<Record<string, string>>(() => {
    const result: Record<string, string> = {};
    for (const [code, cell] of Object.entries(progress.placements)) {
        const object = puzzle.value?.objects.find((candidate) => candidate.cell[0] === cell[0] && candidate.cell[1] === cell[1]);
        if (object) result[code] = poseText(object);
    }

    return result;
});

const hintsLeft = computed(() => Math.max(0, MAX_HINTS - progress.hintsUsed.length));
const shownHints = computed(() => progress.hintTexts ?? []);
const selectedName = computed(() => puzzle.value?.tokens.find((token) => token.code === selectedToken.value)?.name ?? 'не выбран');
async function loadPuzzle(): Promise<void> {
    loading.value = true;
    error.value = false;

    try {
        void loadPlayer();
        const response = await globalThis.fetch(`/api/puzzles/${encodeURIComponent(props.slug)}`, { headers: apiHeaders() });
        if (response.status === 403) {
            const body = await response.json().catch(() => ({})) as { code?: string; message?: string };
            levelLocked.value = body.code === 'level_locked' ? (body.message ?? 'Это дело пока закрыто.') : null;
            needsLogin.value = body.code !== 'level_locked';

            return;
        }
        levelLocked.value = null;
        if (!response.ok) {
            throw new Error('Puzzle request failed');
        }
        needsLogin.value = false;
        puzzle.value = await response.json() as PuzzleDetail;
        loadProgress(puzzle.value.slug, puzzle.value.revision);
    } catch {
        error.value = true;
    } finally {
        loading.value = false;
    }
}

/** The only cell the helper leaves for the selected guest, when the guest is not on the map yet. */
const singleCell = computed<Cell | null>(() => {
    if (!abilities.autoplace || candidateKeys.value === null || candidateKeys.value.size !== 1) {
        return null;
    }
    const [row, col] = [...candidateKeys.value][0].split(':').map(Number);

    return [row, col];
});

function placeSingle(): void {
    if (singleCell.value !== null) {
        selectCell(singleCell.value);
    }
}

function selectToken(code: string): void {
    selectedToken.value = code;
}

function selectCell(cell: Cell): void {
    if (puzzle.value === null) {
        return;
    }

    if (mode.value === 'note' && abilities.notes) {
        tapNote(cell);

        return;
    }

    if (selectedToken.value === null) {
        message.value = 'Сначала выберите гостя.';

        return;
    }

    const key = `${cell[0]}:${cell[1]}`;
    const current = progress.placements[selectedToken.value];
    const covering = coveredCells(puzzle.value.tokens, progress.placements, puzzle.value.grid.rows, puzzle.value.grid.cols, selectedToken.value).get(key);
    // Without the auto-crossing the player watches the lines alone: the cell is not refused, the answer is checked at the end.
    if (abilities.autoCross && covering && !(current?.[0] === cell[0] && current[1] === cell[1])) {
        message.value = `Клетка перекрыта: в этой строке или столбце уже стоит ${[...new Set(covering)].join(', ')}.`;

        return;
    }
    message.value = '';

    if (current?.[0] === cell[0] && current[1] === cell[1]) {
        delete progress.placements[selectedToken.value];
    } else {
        Object.keys(progress.placements).forEach((code) => {
            const placement = progress.placements[code];
            if (placement[0] === cell[0] && placement[1] === cell[1]) {
                delete progress.placements[code];
            }
        });
        progress.placements[selectedToken.value] = cell;
    }
    save();
}

function toggleClue(index: number): void {
    const position = progress.usedClues.indexOf(index);
    if (position === -1) {
        progress.usedClues.push(index);
    } else {
        progress.usedClues.splice(position, 1);
    }
    save();
}

async function requestHint(): Promise<void> {
    if (puzzle.value === null || requestingHint.value) {
        return;
    }
    if (playerState.player === null) {
        message.value = 'Подсказки доступны после входа и изучения навыка «Внимательность».';

        return;
    }
    if (playerState.player.hints.locked) {
        message.value = 'Подсказки открывает навык «Внимательность»: вложите очко развития в профиле.';

        return;
    }
    if (hintsLeft.value === 0) {
        message.value = 'К этому делу уже получены все 3 подсказки.';

        return;
    }
    if (playerState.player.hints.available === 0) {
        message.value = `Подсказок не осталось. Новая появится после ещё ${playerState.player.hints.next_in} ${plural(playerState.player.hints.next_in, 'решённого дела', 'решённых дел', 'решённых дел')} — или купите пакет.`;

        return;
    }
    requestingHint.value = true;
    message.value = '';
    const level = progress.hintsUsed.length + 1;

    try {
        const response = await globalThis.fetch(`/api/puzzles/${encodeURIComponent(puzzle.value.slug)}/hint`, {
            method: 'POST', headers: apiHeaders(), body: JSON.stringify({ level, placements: progress.placements }),
        });
        if (!response.ok) {
            const failure = await response.json().catch(() => ({})) as { message?: string; hints?: HintBalance };
            if (failure.hints && playerState.player) playerState.player.hints = failure.hints;
            message.value = failure.message ?? requestErrorMessage(response.status, 'Подсказка сейчас недоступна. Попробуйте позже.');

            return;
        }
        const hint = await response.json() as { level: number; text: string; hints?: HintBalance };
        if (hint.hints && playerState.player) playerState.player.hints = hint.hints;
        progress.hintTexts = [...(progress.hintTexts ?? []), hint];
        progress.hintsUsed.push(hint.level);
        save();
        // The hint is shown above the map; bring it into view.
        await nextTick();
        document.querySelector('.hint-list')?.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
    } catch {
        message.value = 'Подсказка сейчас недоступна. Попробуйте позже.';
    } finally {
        requestingHint.value = false;
    }
}

async function check(): Promise<void> {
    if (puzzle.value === null || checking.value) {
        return;
    }
    checking.value = true;
    message.value = '';

    try {
        const response = await globalThis.fetch(`/api/puzzles/${encodeURIComponent(puzzle.value.slug)}/check`, {
            method: 'POST', headers: apiHeaders(), body: JSON.stringify({ placements: progress.placements }),
        });
        if (!response.ok) {
            message.value = requestErrorMessage(response.status, 'Проверка сейчас недоступна. Попробуйте позже.');

            return;
        }
        const result = await response.json() as { correct: boolean; complete: boolean; ending?: Ending; reward?: SolveReward; chapter?: ChapterOutro };
        if (result.correct && result.ending) {
            progress.ending = result.ending;
            save();
            message.value = 'Дело раскрыто.';
            chapterOutro.value = result.chapter ?? null;
            if (result.reward) {
                reward.value = result.reward;
                playerState.player = result.reward.player;
            }
        } else {
            message.value = result.complete ? 'Расстановка пока неверна.' : 'Разместите все фигуры, чтобы проверить ответ.';
        }
    } catch {
        message.value = 'Проверка сейчас недоступна. Попробуйте позже.';
    } finally {
        checking.value = false;
    }
}

onMounted(loadPuzzle);
</script>

<template>
    <main class="game-page">
        <header class="public-header">
            <a href="/" class="site-home-link">SelfBio</a>
            <a href="/puzzles" class="brand"><span aria-hidden="true">←</span> К делам</a>
            <span v-if="puzzle" class="game-revision"><span class="case-status-dot" aria-hidden="true"></span> Дело · версия {{ puzzle.revision }}</span>
            <span class="header-links"><a href="/rating" class="quiet-link">Рейтинг</a> <a href="/shop" class="quiet-link">Магазин</a> <a href="/rules" class="quiet-link">Правила</a> <PlayerBar /></span>
        </header>
        <p v-if="loading" class="state-message" role="status">Загружаем дело…</p>
        <section v-else-if="needsLogin" class="state-card">
            <h1>Это дело — для вошедших игроков</h1>
            <p>Гостям открыты гостевые дела. Войдите через Telegram, и откроются все дела, очки, звания и подсказки.</p>
            <TelegramLogin @signed-in="loadPuzzle" />
            <a class="quiet-link" href="/puzzles">К списку дел</a>
        </section>
        <section v-else-if="levelLocked" class="state-card">
            <h1>Это дело пока закрыто</h1>
            <p>{{ levelLocked }}</p>
            <a class="primary-action" href="/profile">Развитие детектива →</a>
            <a class="quiet-link" href="/puzzles">К списку дел</a>
        </section>
        <section v-else-if="error || !puzzle" class="state-card">
            <h1>Дело недоступно</h1>
            <p>Возможно, оно ещё не опубликовано или соединение было прервано.</p>
            <button class="primary-action" type="button" @click="loadPuzzle">Повторить</button>
        </section>
        <template v-else>
            <header class="game-heading">
                <p class="eyebrow game-case-meta"><span class="case-file-label">Расследование</span> {{ puzzleTypeLabel(puzzle.type) }} · {{ puzzle.grid.rows }}×{{ puzzle.grid.cols }} · <DifficultyStars :level="puzzle.difficulty" /></p>
                <h1>{{ puzzle.title }}</h1>
                <p v-if="puzzle.story" class="case-story">{{ puzzle.story }}</p>
                <p v-else-if="puzzle.description">{{ puzzle.description }}</p>
                <p class="game-task"><strong>Задача:</strong> расставьте гостей по показаниям и назовите {{ culpritAccusative(puzzle.type) }}.</p>
            </header>
            <section v-if="puzzle.chapter" class="story-banner" aria-label="Глава сюжета">
                <p class="eyebrow">Путь детектива · глава {{ puzzle.chapter.number }} · {{ puzzle.chapter.title }}</p>
                <StorySpeaker :hero="puzzle.chapter.speaker" />
                <p>{{ puzzle.chapter.briefing }}</p>
            </section>
            <section v-if="shownHints.length" class="hint-list" aria-label="Полученные подсказки">
                <article v-for="hint in shownHints" :key="hint.level" class="hint-card"><strong>Подсказка {{ hint.level }} из 3</strong><p>{{ hint.text }}</p></article>
            </section>
            <div class="game-layout">
                <TokenPanel class="game-tokens" :tokens="puzzle.tokens" :placements="progress.placements" :selected-token="selectedToken" :candidate-counts="helperOn ? candidatesByToken : null" :poses="poses" @select="selectToken" />
                <PuzzleGrid class="game-map" :puzzle="puzzle" :placements="progress.placements" :notes="abilities.notes ? progress.notes : {}" :selected-token="selectedToken" :scene="scene" :highlight="highlight" :candidates="candidateKeys" :cover="abilities.autoCross" @select-cell="selectCell" />
                <section class="game-controls" aria-label="Управление расследованием">
                    <div class="controls-main">
                        <div class="selection-summary">
                            <span>Выбранный гость</span>
                            <strong>{{ selectedName }}</strong>
                        </div>
                        <div class="mode-switch" role="group" aria-label="Режим клетки">
                            <button type="button" :class="{ 'is-active': mode === 'place' }" :aria-pressed="mode === 'place'" @click="mode = 'place'">Разместить</button>
                            <button type="button" :class="{ 'is-active': mode === 'note' && abilities.notes }" :aria-pressed="mode === 'note' && abilities.notes" :disabled="!abilities.notes" :title="abilities.notes ? undefined : 'Заметки открывает навык «Экспертность»'" @click="mode = 'note'">Заметка{{ abilities.notes ? '' : ' 🔒' }}</button>
                        </div>
                        <p class="notebook-note">{{ mode === 'place' ? 'Выберите гостя и нажмите клетку — гость встанет туда. Нажмите ещё раз, чтобы убрать.' : selectedToken === null ? 'Гость не выбран: нажатие ставит на клетку крестик «здесь никого не будет» — для всех.' : 'Нажатие по кругу: буква гостя («возможно») → крестик («точно нет») → пусто.' }}</p>
                        <div class="action-row">
                            <button class="secondary-action" type="button" :disabled="requestingHint || (playerState.player !== null && hintsLeft === 0)" @click="requestHint">{{ requestingHint ? 'Запрашиваем…' : !playerState.player ? 'Подсказки — после входа' : playerState.player.hints.locked ? 'Подсказки 🔒 навык «Внимательность»' : hintsLeft === 0 ? 'Все подсказки к делу получены' : `Подсказка (в запасе: ${playerState.player.hints.available})` }}</button>
                            <button v-if="playerState.player && !playerState.player.hints.locked && playerState.player.hints.available === 0 && hintsLeft > 0" class="secondary-action" type="button" @click="buying = true">Купить подсказки</button>
                            <button class="primary-action" type="button" :disabled="checking" @click="check">{{ checking ? 'Проверяем…' : 'Проверить решение' }}</button>
                        </div>
                        <p v-if="message" class="game-message" role="status">{{ message }}</p>
                    </div>
                    <NotebookPanel class="controls-notebook" :notes-open="abilities.notes" :helper-open="abilities.helper" :mark-open="abilities.markCandidates" :place-open="abilities.autoplace" :can-place="singleCell !== null" :signed-in="playerState.player !== null" :helper-on="helperOn" :helper-note="helperNote" :can-mark="candidateKeys !== null" :can-clear="selectedToken !== null" @toggle-helper="toggleHelper" @mark-candidates="markCandidates" @clear-notes="clearNotes" @place-single="placeSingle" />
                </section>
                <ClueList class="game-clues" :clues="puzzle.clues" :used-clues="progress.usedClues" :tokens="puzzle.tokens" :victim="puzzle.victim" :pinned="pinnedClue" :locate="abilities.locateOnMap" @toggle="toggleClue" @preview="previewTarget = $event" @pin="pinClue" />
            </div>
            <EndingCard v-if="progress.ending" :ending="progress.ending" :type="puzzle.type" :tokens="puzzle.tokens" :scene="scene" :reward="reward" :guest="!playerState.player" :chapter="chapterOutro" :next-busy="endless.busy" @next="playNext" />
            <p v-if="endless.error" class="login-error" role="alert">{{ endless.error }}</p>
            <AchievementDialog :reward="reward" />
            <BuyHintsDialog v-model="buying" />
        </template>
    </main>
</template>
