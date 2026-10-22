<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import { checkBotLogin, playerState, signInForTesting, startBotLogin } from '../composables/usePlayer';
import type { BotLoginRequest } from '../composables/usePlayer';

const emit = defineEmits<{ signedIn: [] }>();

const error = ref('');
const waiting = ref(false);
const starting = ref(false);
const link = ref('');
let timer: ReturnType<typeof setTimeout> | null = null;

function stop(): void {
    if (timer !== null) clearTimeout(timer);
    timer = null;
    waiting.value = false;
}

async function poll(request: BotLoginRequest, until: number): Promise<void> {
    if (Date.now() > until) {
        stop();
        error.value = 'Время входа истекло. Попробуйте ещё раз.';

        return;
    }
    const state = await checkBotLogin(request);
    if (state === 'signed-in') {
        stop();
        emit('signedIn');
    } else if (state === 'pending') {
        timer = setTimeout(() => { void poll(request, until); }, 2000);
    } else {
        stop();
        error.value = state;
    }
}

async function signIn(): Promise<void> {
    // A double tap must not start two requests (two windows, two polling chains).
    if (starting.value || waiting.value) return;
    starting.value = true;
    error.value = '';
    // Safari lets a window open only inside the tap itself: open it now and point it at the bot once the link is known.
    // If the browser refuses, the «Открыть бота» link below stays as the way in.
    const tab = globalThis.open('', '_blank');
    const request = await startBotLogin();
    starting.value = false;
    if (typeof request === 'string') {
        tab?.close();
        error.value = request;

        return;
    }
    link.value = request.link;
    waiting.value = true;
    if (tab !== null && tab !== undefined) {
        tab.opener = null;
        tab.location.href = request.link;
    }
    void poll(request, Date.now() + request.expires_in * 1000);
}

async function testLogin(): Promise<void> {
    error.value = await signInForTesting() ?? '';
    if (error.value === '') emit('signedIn');
}

onBeforeUnmount(stop);
</script>

<template>
    <span class="telegram-login">
        <button v-if="playerState.login.dev" type="button" class="secondary-action" @click="testLogin">Войти (тестовый вход)</button>
        <template v-else-if="playerState.login.bot">
            <button v-if="!waiting" type="button" class="secondary-action" :disabled="starting" @click="signIn">Войти через Telegram</button>
            <small v-else class="quiet-note">
                Нажмите Start в боте, страница обновится сама.
                <a :href="link" target="_blank" rel="noopener">Открыть бота</a>
            </small>
        </template>
        <small v-else class="quiet-note">Вход через Telegram пока не настроен.</small>
        <small v-if="error" class="login-error" role="alert">{{ error }}</small>
    </span>
</template>
