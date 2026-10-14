<script setup lang="ts">
import type { StoryHero } from '../types/puzzle';
import PersonAvatar from './PersonAvatar.vue';

/** Who speaks in a scene of the story: a portrait, the name and the role. */
defineProps<{ hero: StoryHero | null }>();
</script>

<template>
    <p v-if="hero" class="story-speaker">
        <PersonAvatar class="story-speaker-avatar" :name="hero.name" :avatar="hero.avatar" />
        <span><strong>{{ hero.name }}</strong><small>{{ hero.role }}</small></span>
    </p>
</template>
