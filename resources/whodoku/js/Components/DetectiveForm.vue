<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import PersonAvatar from './PersonAvatar.vue';
import { BACKGROUNDS, HAIR_COLORS, SHIRT_COLORS, SKIN_TONES } from '../composables/avatar';
import { playerState, saveDetective } from '../composables/usePlayer';

const emit = defineEmits<{ saved: [] }>();

const current = playerState.player?.detective ?? null;
const recipe = (current?.avatar ?? '').startsWith('v1;')
    ? Object.fromEntries((current?.avatar ?? '').split(';').slice(1).map((part) => part.split('=') as [string, string]))
    : {} as Record<string, string>;

const form = reactive({
    name: current?.name ?? '',
    gender: (recipe.g === 'm' ? 'm' : 'f') as 'm' | 'f',
    skin: Number(recipe.skin ?? 1),
    hair: Number(recipe.hair ?? 1),
    style: Number(recipe.style ?? 1),
    shirt: Number(recipe.shirt ?? 0),
    extra: recipe.extra ?? 'none',
});
const error = ref('');
const saving = ref(false);

const STYLES = {
    f: [[1, 'Длинные'], [2, 'Каре'], [3, 'Пучок'], [4, 'Кудри']],
    m: [[0, 'Короткие'], [4, 'Кудри'], [5, 'Лысый'], [6, 'Кепка']],
} as const;
const EXTRAS = {
    f: [['none', 'Без украшений'], ['glasses', 'Очки'], ['earrings', 'Серьги'], ['freckles', 'Веснушки']],
    m: [['none', 'Без деталей'], ['glasses', 'Очки'], ['beard', 'Борода'], ['mustache', 'Усы']],
} as const;

const styles = computed(() => STYLES[form.gender]);
const extras = computed(() => EXTRAS[form.gender]);
const preview = computed(() => `v1;g=${form.gender};skin=${form.skin};hair=${form.hair};style=${form.style};shirt=${form.shirt};extra=${form.extra}`);

function setGender(gender: 'm' | 'f'): void {
    form.gender = gender;
    form.style = STYLES[gender][0][0];
    form.extra = 'none';
}

async function submit(): Promise<void> {
    saving.value = true;
    error.value = await saveDetective({ ...form }) ?? '';
    saving.value = false;
    if (error.value === '') emit('saved');
}
</script>

<template>
    <form class="detective-form" @submit.prevent="submit">
        <div class="detective-preview" aria-hidden="true">
            <PersonAvatar :name="form.name || 'Детектив'" :avatar="preview" />
        </div>
        <div class="detective-fields">
            <label class="detective-field">
                <span>Имя детектива</span>
                <input v-model="form.name" type="text" maxlength="24" minlength="3" required autocomplete="off" placeholder="Например, Инспектор Лис">
                <small class="quiet-note">3–24 символа: буквы, цифры, пробел, дефис. Под этим именем вы расследуете дела и стоите в рейтинге.</small>
            </label>

            <div class="detective-field">
                <span>Кто вы</span>
                <div class="chip-row" role="group" aria-label="Пол персонажа">
                    <button type="button" :class="{ 'is-active': form.gender === 'f' }" :aria-pressed="form.gender === 'f'" @click="setGender('f')">Женщина</button>
                    <button type="button" :class="{ 'is-active': form.gender === 'm' }" :aria-pressed="form.gender === 'm'" @click="setGender('m')">Мужчина</button>
                </div>
            </div>

            <div class="detective-field">
                <span>Кожа</span>
                <div class="swatch-row">
                    <button v-for="(color, index) in SKIN_TONES" :key="color" type="button" class="swatch" :class="{ 'is-active': form.skin === index }" :style="{ background: color }" :aria-label="`Оттенок ${index + 1}`" :aria-pressed="form.skin === index" @click="form.skin = index" />
                </div>
            </div>
            <div class="detective-field">
                <span>Волосы</span>
                <div class="swatch-row">
                    <button v-for="(color, index) in HAIR_COLORS" :key="color" type="button" class="swatch" :class="{ 'is-active': form.hair === index }" :style="{ background: color }" :aria-label="`Цвет волос ${index + 1}`" :aria-pressed="form.hair === index" @click="form.hair = index" />
                </div>
                <div class="chip-row" role="group" aria-label="Причёска">
                    <button v-for="[value, label] in styles" :key="value" type="button" :class="{ 'is-active': form.style === value }" :aria-pressed="form.style === value" @click="form.style = value">{{ label }}</button>
                </div>
            </div>
            <div class="detective-field">
                <span>Одежда</span>
                <div class="swatch-row">
                    <button v-for="(color, index) in SHIRT_COLORS" :key="color" type="button" class="swatch" :class="{ 'is-active': form.shirt === index }" :style="{ background: color, outlineColor: BACKGROUNDS[index] }" :aria-label="`Цвет одежды ${index + 1}`" :aria-pressed="form.shirt === index" @click="form.shirt = index" />
                </div>
            </div>
            <div class="detective-field">
                <span>Деталь</span>
                <div class="chip-row" role="group" aria-label="Деталь образа">
                    <button v-for="[value, label] in extras" :key="value" type="button" :class="{ 'is-active': form.extra === value }" :aria-pressed="form.extra === value" @click="form.extra = value">{{ label }}</button>
                </div>
            </div>

            <p v-if="error" class="login-error" role="alert">{{ error }}</p>
            <button type="submit" class="primary-action" :disabled="saving">{{ saving ? 'Сохраняем…' : 'Сохранить детектива' }}</button>
        </div>
    </form>
</template>
