<script setup lang="ts">
import { computed } from 'vue';
import { avatarRecipe, BACKGROUNDS, HAIR_COLORS, SHIRT_COLORS, SKIN_TONES } from '../composables/avatar';

const props = defineProps<{ name: string; avatar?: string | null }>();

const isImage = computed(() => props.avatar != null && !props.avatar.startsWith('v1;') && /^(https?:\/\/|\/)/.test(props.avatar));
const recipe = computed(() => avatarRecipe(props.avatar, props.name));
const skin = computed(() => SKIN_TONES[recipe.value.skin]);
const hair = computed(() => HAIR_COLORS[recipe.value.hair]);
const shirt = computed(() => SHIRT_COLORS[recipe.value.shirt]);
const background = computed(() => BACKGROUNDS[recipe.value.shirt % BACKGROUNDS.length]);
</script>

<template>
    <img v-if="isImage" class="person-avatar" :src="avatar!" alt="" loading="lazy">
    <svg v-else class="person-avatar" viewBox="0 0 64 64" role="presentation" aria-hidden="true">
        <rect width="64" height="64" :fill="background" />
        <!-- hair behind the head -->
        <path v-if="recipe.style === 1" d="M18 30 C16 10 48 10 46 30 L48 54 L16 54 Z" :fill="hair" />
        <path v-else-if="recipe.style === 2" d="M18 28 C16 10 48 10 46 28 L46 40 L18 40 Z" :fill="hair" />
        <circle v-else-if="recipe.style === 3" cx="32" cy="9" r="6" :fill="hair" />
        <!-- shoulders and neck -->
        <path d="M6 64 C6 49 18 45 32 45 C46 45 58 49 58 64 Z" :fill="shirt" />
        <rect x="27" y="36" width="10" height="11" rx="3" :fill="skin" />
        <path d="M27 46 L32 52 L37 46 Z" fill="#fff" opacity="0.85" />
        <!-- head and ears -->
        <circle cx="21" cy="29" r="2.6" :fill="skin" />
        <circle cx="43" cy="29" r="2.6" :fill="skin" />
        <ellipse cx="32" cy="27" rx="11.5" ry="13.5" :fill="skin" />
        <!-- hair on top -->
        <path v-if="recipe.style === 0" d="M20 26 C19 10 45 10 44 26 C41 19 36 17 32 17 C28 17 23 19 20 26 Z" :fill="hair" />
        <path v-else-if="recipe.style === 1 || recipe.style === 2" d="M20 28 C19 9 45 9 44 28 C42 19 38 16 32 16 C26 16 22 19 20 28 Z" :fill="hair" />
        <path v-else-if="recipe.style === 3" d="M20 26 C19 11 45 11 44 26 C41 19 36 17 32 17 C28 17 23 19 20 26 Z" :fill="hair" />
        <g v-else-if="recipe.style === 4" :fill="hair">
            <circle cx="22" cy="19" r="5.5" /><circle cx="29" cy="14" r="6" /><circle cx="36" cy="14" r="6" /><circle cx="42" cy="19" r="5.5" />
            <circle cx="20" cy="26" r="3.5" /><circle cx="44" cy="26" r="3.5" />
        </g>
        <g v-else-if="recipe.style === 6">
            <path d="M19 22 C19 8 45 8 45 22 Z" :fill="shirt" />
            <rect x="17" y="21" width="30" height="4" rx="2" :fill="shirt" />
        </g>
        <!-- face -->
        <circle cx="27.5" cy="28" r="1.5" fill="#2a1d17" />
        <circle cx="36.5" cy="28" r="1.5" fill="#2a1d17" />
        <path d="M24.5 24.5 Q27.5 23 30 24.5 M34 24.5 Q36.5 23 39.5 24.5" fill="none" :stroke="hair" stroke-width="1.2" stroke-linecap="round" />
        <path d="M27.5 34 Q32 38 36.5 34" fill="none" stroke="#7a3b2e" stroke-width="1.5" stroke-linecap="round" />
        <!-- extras -->
        <g v-if="recipe.extra === 'glasses'" fill="none" stroke="#25303a" stroke-width="1.3">
            <circle cx="27.5" cy="28" r="4.2" /><circle cx="36.5" cy="28" r="4.2" /><path d="M31.7 28 H32.3" />
        </g>
        <path v-else-if="recipe.extra === 'beard'" d="M21 31 C21 46 43 46 43 31 C40 38 36 38 32 38 C28 38 24 38 21 31 Z" :fill="hair" />
        <path v-else-if="recipe.extra === 'mustache'" d="M26 33 Q29 31 32 33 Q35 31 38 33 Q35 35 32 34 Q29 35 26 33 Z" :fill="hair" />
        <g v-else-if="recipe.extra === 'earrings'" fill="#d9a82f">
            <circle cx="20.5" cy="33" r="1.6" /><circle cx="43.5" cy="33" r="1.6" />
        </g>
        <g v-else-if="recipe.extra === 'freckles'" fill="#b87650" opacity="0.7">
            <circle cx="26" cy="32" r="0.8" /><circle cx="29" cy="33" r="0.8" /><circle cx="35" cy="33" r="0.8" /><circle cx="38" cy="32" r="0.8" />
        </g>
    </svg>
</template>
