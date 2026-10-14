<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { loadOffer, startPurchase } from '../composables/useShop';
import type { ShopOffer } from '../types/puzzle';

/** The window of buying a hint pack: the price, the consent to the offer, then the page of the payment system. */
const open = defineModel<boolean>({ default: false });

const dialog = ref<HTMLDialogElement | null>(null);
const offer = ref<ShopOffer | null>(null);
const consent = ref(false);
const busy = ref(false);
const error = ref('');

const product = computed(() => offer.value?.products[0] ?? null);

watch(open, async (value) => {
    if (!value) {
        dialog.value?.close?.();

        return;
    }
    error.value = '';
    consent.value = false;
    offer.value = await loadOffer();
    await nextTick();
    dialog.value?.showModal?.();
});

function close(): void {
    open.value = false;
}

function backdropClick(event: MouseEvent): void {
    if (event.target === dialog.value) close();
}

async function pay(): Promise<void> {
    if (product.value === null || !consent.value || busy.value) return;
    busy.value = true;
    error.value = '';
    const result = await startPurchase(product.value.code);
    busy.value = false;
    if ('error' in result) {
        error.value = result.error;

        return;
    }
    globalThis.location.assign(result.url);
}
</script>

<template>
    <dialog v-if="open" ref="dialog" class="achievement-dialog shop-dialog" aria-labelledby="shop-title" @click="backdropClick" @close="open = false">
        <div class="achievement-body">
            <p class="eyebrow">Подсказки</p>
            <h2 id="shop-title" class="achievement-rank">{{ product ? `${product.title} — ${product.price} ₽` : 'Пакет подсказок' }}</h2>

            <p v-if="offer && !offer.can_buy" class="shop-note">
                <template v-if="offer.reason === 'hints_locked'">Подсказки открывает навык «Внимательность»: вложите очко развития в <a href="/profile">профиле</a>, и пакет можно будет купить.</template>
                <template v-else>Покупать подсказки могут вошедшие игроки.</template>
            </p>
            <template v-else-if="product">
                <p v-if="offer?.test_mode" class="shop-note shop-alpha">Альфа-тестирование: реальная оплата сейчас не применяется, деньги не списываются.</p>
                <p>Подсказки работают так же, как заработанные: каждая открывает подсказку к делу.</p>
                <label class="shop-consent">
                    <input v-model="consent" type="checkbox">
                    <span>Я согласен с <a href="/offer" target="_blank" rel="noopener">офертой</a> и <a href="/privacy" target="_blank" rel="noopener">политикой конфиденциальности</a></span>
                </label>
            </template>
            <p v-else class="shop-note">Загружаем…</p>
            <p v-if="error" class="shop-error" role="alert">{{ error }}</p>

            <div class="achievement-actions">
                <button v-if="offer?.can_buy && product" type="button" class="primary-action" :disabled="!consent || busy" @click="pay">{{ busy ? 'Переходим к оплате…' : `Оплатить ${product.price} ₽` }}</button>
                <button type="button" class="secondary-action" @click="close">Закрыть</button>
            </div>
        </div>
    </dialog>
</template>
