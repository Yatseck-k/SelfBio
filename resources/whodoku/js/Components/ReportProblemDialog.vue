<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { INCIDENT_CATEGORIES, INCIDENT_MESSAGE_MAX, sendIncident, type IncidentCategory } from '../composables/useIncident';
import type { PlacementMap } from '../types/puzzle';

/** The window «Сообщить об ошибке»: what is wrong in a few words; the report goes to the admins' list of incidents. */
const open = defineModel<boolean>({ default: false });
const props = defineProps<{ slug: string; placements: PlacementMap }>();

const dialog = ref<HTMLDialogElement | null>(null);
const category = ref<IncidentCategory | ''>('');
const message = ref('');
const busy = ref(false);
const error = ref('');
const sent = ref('');

const left = computed(() => INCIDENT_MESSAGE_MAX - message.value.length);
const canSend = computed(() => category.value !== '' && message.value.trim().length >= 5 && !busy.value);

watch(open, async (value) => {
    if (!value) {
        dialog.value?.close?.();

        return;
    }
    category.value = '';
    message.value = '';
    error.value = '';
    sent.value = '';
    await nextTick();
    dialog.value?.showModal?.();
});

function close(): void {
    open.value = false;
}

function backdropClick(event: MouseEvent): void {
    if (event.target === dialog.value) close();
}

async function submit(): Promise<void> {
    if (!canSend.value || category.value === '') return;
    busy.value = true;
    error.value = '';
    const result = await sendIncident(props.slug, { category: category.value, message: message.value.trim(), placements: props.placements });
    busy.value = false;
    if (result.ok) {
        sent.value = result.message;
    } else {
        error.value = result.error;
    }
}
</script>

<template>
    <dialog v-if="open" ref="dialog" class="achievement-dialog report-dialog" aria-labelledby="report-title" @click="backdropClick" @close="open = false">
        <div class="achievement-body">
            <p class="eyebrow">Дело</p>
            <h2 id="report-title" class="achievement-rank">Сообщить об ошибке</h2>

            <template v-if="sent">
                <p class="report-thanks" role="status">{{ sent }}</p>
                <div class="achievement-actions">
                    <button type="button" class="primary-action" @click="close">Закрыть</button>
                </div>
            </template>
            <form v-else @submit.prevent="submit">
                <fieldset class="report-categories">
                    <legend>Что не так?</legend>
                    <label v-for="item in INCIDENT_CATEGORIES" :key="item.value">
                        <input v-model="category" type="radio" name="report-category" :value="item.value">
                        <span>{{ item.label }}</span>
                    </label>
                </fieldset>
                <label class="report-message">
                    <span>Опишите кратко</span>
                    <textarea v-model="message" rows="4" :maxlength="INCIDENT_MESSAGE_MAX" placeholder="Например: непонятно, где окно у Станислава" />
                    <small>Осталось символов: {{ left }}</small>
                </label>
                <p v-if="error" class="shop-error" role="alert">{{ error }}</p>
                <div class="achievement-actions">
                    <button type="submit" class="primary-action" :disabled="!canSend">{{ busy ? 'Отправляем…' : 'Отправить' }}</button>
                    <button type="button" class="secondary-action" @click="close">Отмена</button>
                </div>
            </form>
        </div>
    </dialog>
</template>
