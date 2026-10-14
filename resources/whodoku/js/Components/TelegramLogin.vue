<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { playerState, signInForTesting, signInWithTelegram } from '../composables/usePlayer';

const emit = defineEmits<{ signedIn: [] }>();

const host = ref<HTMLElement | null>(null);
const error = ref('');

async function handle(user: Record<string, unknown>): Promise<void> {
    error.value = await signInWithTelegram(user) ?? '';
    if (error.value === '') emit('signedIn');
}

async function testLogin(): Promise<void> {
    error.value = await signInForTesting() ?? '';
    if (error.value === '') emit('signedIn');
}

onMounted(() => {
    const bot = playerState.login.bot_username;
    if (playerState.login.dev || !bot || host.value === null) {
        return;
    }
    // The widget calls this global function with the signed data of the user.
    (globalThis as unknown as Record<string, unknown>).onTelegramAuth = (user: Record<string, unknown>) => { void handle(user); };
    const script = document.createElement('script');
    script.async = true;
    script.src = 'https://telegram.org/js/telegram-widget.js?22';
    script.dataset.telegramLogin = bot;
    script.dataset.size = 'medium';
    script.dataset.radius = '8';
    script.dataset.onauth = 'onTelegramAuth(user)';
    script.dataset.requestAccess = 'write';
    host.value.appendChild(script);
});

onBeforeUnmount(() => {
    delete (globalThis as unknown as Record<string, unknown>).onTelegramAuth;
});
</script>

<template>
    <span class="telegram-login">
        <span v-if="!playerState.login.dev" ref="host" />
        <button v-if="playerState.login.dev" type="button" class="secondary-action" @click="testLogin">Войти (тестовый вход)</button>
        <small v-if="!playerState.login.bot_username && !playerState.login.dev" class="quiet-note">Вход через Telegram пока не настроен.</small>
        <small v-if="error" class="login-error" role="alert">{{ error }}</small>
    </span>
</template>
