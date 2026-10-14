<script setup lang="ts">
/** Difficulty as a labelled row of stars: filled ones are the level, the empty ones show how far it could go. */
const props = withDefaults(defineProps<{ level: number; max?: number }>(), { max: 5 });
</script>

<template>
    <span class="difficulty-stars" role="img" :aria-label="`Сложность: ${props.level} из ${props.max}`">
        <span class="difficulty-label">Сложность</span>
        <span class="difficulty-row" aria-hidden="true">
            <span v-for="index in props.max" :key="index" class="difficulty-star" :class="{ 'is-on': index <= props.level }">★</span>
        </span>
        <span class="difficulty-number" aria-hidden="true">{{ props.level }}/{{ props.max }}</span>
    </span>
</template>
