<script setup lang="ts">
import { ref } from 'vue';

/** The notebook of the game page: what it is for, the helper with its buttons; the instruction opens in a window. */
defineProps<{
    /** Skills of the detective (expertise) open the notes, the helper and «Отметить кандидатов». */
    notesOpen: boolean;
    helperOpen: boolean;
    markOpen: boolean;
    /** «Поставить» is learned (expertise). */
    placeOpen?: boolean;
    signedIn: boolean;
    helperOn: boolean;
    helperNote: string;
    /** «Отметить кандидатов» works for a selected guest who is not on the map yet. */
    canMark: boolean;
    /** «Поставить» works when the selected guest has only one cell left. */
    canPlace?: boolean;
    /** «Стереть заметки» works when a guest is selected. */
    canClear: boolean;
}>();

defineEmits<{ toggleHelper: []; markCandidates: []; clearNotes: []; placeSingle: [] }>();

const help = ref<HTMLDialogElement | null>(null);

function openHelp(): void {
    help.value?.showModal?.();
}

function closeHelp(): void {
    help.value?.close?.();
}

/** A click on the backdrop (the dialog itself, outside its content) closes the window. */
function backdropClick(event: MouseEvent): void {
    if (event.target === help.value) closeHelp();
}
</script>

<template>
    <section class="notebook" aria-labelledby="notebook-heading">
        <h3 id="notebook-heading">Блокнот</h3>
        <p v-if="!notesOpen" class="notebook-locked" role="status">
            <span aria-hidden="true">🔒</span>
            Блокнот открывает навык «Экспертность»<template v-if="signedIn">: вложите очко развития в <a href="/profile">профиле</a> — оно даётся за каждый новый уровень.</template><template v-else>. <a href="/profile">Войдите</a>, развивайте детектива, и блокнот откроется.</template>
        </p>
        <template v-else>
        <p class="notebook-lead">Место для догадок. Пометки не ставят гостя на карту и не проверяются — они нужны, чтобы не держать всё в голове.</p>
        <button class="secondary-action notebook-help-button" type="button" aria-haspopup="dialog" @click="openHelp">
            <span aria-hidden="true">?</span> Как пользоваться блокнотом
        </button>
        <dialog ref="help" class="notebook-dialog" aria-labelledby="notebook-help-title" @click="backdropClick">
            <div class="notebook-dialog-body">
                <header>
                    <h3 id="notebook-help-title">Как пользоваться блокнотом</h3>
                    <button class="notebook-dialog-close" type="button" aria-label="Закрыть" @click="closeHelp">×</button>
                </header>
                <p>Блокнот — место для догадок. Пометки не ставят гостя на карту и не проверяются: они нужны, чтобы не держать всё в голове.</p>
                <ol>
                    <li>Включите режим «Заметка» и нажимайте клетки: буква гостя — «возможно здесь», крестик — «здесь точно нет».</li>
                    <li>Помощник по показанию гостя и уже перекрытым строкам и столбцам сам обводит клетки, где гость ещё может стоять.</li>
                    <li>«Отметить кандидатов» превращает эти клетки в заметки «возможно» — дальше вы вычёркиваете лишнее.</li>
                </ol>
                <ul class="notebook-legend">
                    <li><span class="legend-chip is-letter">Б</span> возможно</li>
                    <li><span class="legend-chip is-cross">×</span> точно нет</li>
                    <li><span class="legend-chip is-candidate"></span> подходит по показанию</li>
                </ul>
                <button class="primary-action" type="button" @click="closeHelp">Понятно</button>
            </div>
        </dialog>
        <label class="notebook-toggle" :class="{ 'is-locked': !helperOpen }">
            <input type="checkbox" :checked="helperOn" :disabled="!helperOpen" @change="$emit('toggleHelper')">
            <span>Помощник: подсвечивать подходящие клетки<template v-if="!helperOpen"> 🔒 — откроется навыком «Экспертность»</template></span>
        </label>
        <template v-if="helperOn">
            <p class="notebook-note" role="status">{{ helperNote }}</p>
            <div class="action-row">
                <button class="secondary-action" type="button" :disabled="!canMark || !markOpen" :title="markOpen ? 'Пометить «возможно» все подсвеченные клетки выбранного гостя' : 'Откроется навыком «Экспертность»'" @click="$emit('markCandidates')">Отметить кандидатов</button>
                <button v-if="placeOpen" class="secondary-action" type="button" :disabled="!canPlace" title="Поставить выбранного гостя на единственную подходящую клетку" @click="$emit('placeSingle')">Поставить</button>
                <button class="secondary-action" type="button" :disabled="!canClear" title="Убрать заметки выбранного гостя" @click="$emit('clearNotes')">Стереть заметки гостя</button>
            </div>
            <p v-if="!markOpen" class="notebook-note">«Отметить кандидатов» откроется навыком «Экспертность».</p>
            <p v-else-if="!canMark" class="notebook-note">Кнопка «Отметить кандидатов» работает, когда выбран гость, которого ещё нет на карте.</p>
        </template>
        </template>
    </section>
</template>
