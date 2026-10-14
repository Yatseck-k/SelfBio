<script setup lang="ts">
import { computed } from 'vue';
import { sealText, slotImage } from '../composables/appearance';

/** A stamp: the caption from the admin and, when one was uploaded, its picture. */
const props = defineProps<{ code: 'solved' | 'new' | 'guest' | 'rank' }>();
const image = computed(() => slotImage(`seal:${props.code}`));
</script>

<template>
    <span class="seal" :class="`seal-${code}`">
        <img v-if="image" class="seal-image" :src="image" alt="" draggable="false">
        <span>{{ sealText(code) }}</span>
    </span>
</template>
