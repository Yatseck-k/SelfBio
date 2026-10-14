<script setup lang="ts">
import { computed } from 'vue';
import { slotImage } from '../composables/appearance';

/** An image of the site by slot (built-in or uploaded in the admin); the emoji fallback shows until it is known. */
const props = defineProps<{ name: string; fallback?: string }>();
const url = computed(() => slotImage(props.name));
</script>

<template>
    <img v-if="url" class="site-image" :src="url" alt="" draggable="false">
    <template v-else>{{ fallback }}</template>
</template>
