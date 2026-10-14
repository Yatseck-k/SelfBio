<script setup lang="ts">
import { onMounted } from 'vue';
import PersonAvatar from './PersonAvatar.vue';
import TelegramLogin from './TelegramLogin.vue';
import { theme, toggleTheme } from '../composables/theme';
import { loadPlayer, playerState, signOut } from '../composables/usePlayer';

onMounted(() => { void loadPlayer(); });
</script>

<template>
    <div class="player-bar">
        <button type="button" class="theme-toggle" :aria-label="theme === 'dark' ? 'Включить светлую тему' : 'Включить тёмную тему'" :title="theme === 'dark' ? 'Светлая тема' : 'Тёмная тема'" @click="toggleTheme">{{ theme === 'dark' ? '☀' : '☾' }}</button>
        <template v-if="playerState.loaded && playerState.player">
            <a class="player-chip" href="/profile" :title="`${playerState.player.points} очков`">
                <PersonAvatar v-if="playerState.player.detective" class="player-chip-avatar" :name="playerState.player.detective.name" :avatar="playerState.player.detective.avatar" />
                <span class="player-chip-text">
                    <strong>{{ playerState.player.detective?.name ?? 'Создать детектива' }}</strong>
                    <small>{{ playerState.player.rank.title }} · {{ playerState.player.points }}</small>
                </span>
            </a>
            <button type="button" class="quiet-link" @click="signOut">Выйти</button>
        </template>
        <TelegramLogin v-else-if="playerState.loaded" />
    </div>
</template>
