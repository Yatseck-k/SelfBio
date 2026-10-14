<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import BuyHintsDialog from '../Components/BuyHintsDialog.vue';
import PlayerBar from '../Components/PlayerBar.vue';
import TelegramLogin from '../Components/TelegramLogin.vue';
import { loadPlayer, playerState } from '../composables/usePlayer';
import { loadOffer } from '../composables/useShop';
import type { ShopOffer } from '../types/puzzle';

const offer = ref<ShopOffer | null>(null);
const loading = ref(true);
const buying = ref(false);

const product = computed(() => offer.value?.products[0] ?? null);
const hints = computed(() => playerState.player?.hints ?? null);

async function load(): Promise<void> {
    loading.value = true;
    await loadPlayer();
    offer.value = await loadOffer();
    loading.value = false;
}

onMounted(load);
</script>

<template>
    <main class="public-page shop-page">
        <header class="public-header">
            <a href="/" class="site-home-link">SelfBio</a>
            <a href="/puzzles" class="brand">Whodoku</a>
            <span class="header-links"><a href="/puzzles" class="quiet-link">К делам</a> <a href="/rating" class="quiet-link">Рейтинг</a> <a href="/rules" class="quiet-link">Правила</a> <PlayerBar /></span>
        </header>

        <section class="catalog-intro">
            <p class="eyebrow">Магазин</p>
            <h1>Подсказки</h1>
            <p>Подсказка помогает, когда дело не складывается: она называет гостя, комнату или клетку. Подсказки зарабатываются в игре; здесь их можно докупить.</p>
        </section>

        <p v-if="loading" class="state-message" role="status">Загружаем магазин…</p>

        <section v-else-if="!product" class="state-card">
            <h2>Магазин пока недоступен</h2>
            <p>Попробуйте зайти позже.</p>
        </section>

        <section v-else class="state-card shop-card" aria-labelledby="shop-product">
            <h2 id="shop-product">{{ product.title }} — {{ product.price }} ₽</h2>
            <p v-if="offer?.test_mode" class="shop-note shop-alpha">Альфа-тестирование: реальная оплата сейчас не применяется, деньги не списываются, подсказки выдаются бесплатно.</p>
            <p v-if="hints && !hints.locked" class="quiet-note">В запасе сейчас: <strong>{{ hints.available }}</strong>.</p>

            <template v-if="offer?.can_buy">
                <p>Купленные подсказки не сгорают и работают так же, как заработанные.</p>
                <button type="button" class="primary-action" @click="buying = true">Купить за {{ product.price }} ₽</button>
            </template>
            <template v-else-if="offer?.reason === 'login_required'">
                <p>Покупать могут вошедшие игроки.</p>
                <TelegramLogin @signed-in="load" />
            </template>
            <template v-else>
                <p>Подсказки открывает навык «Внимательность»: вложите очко развития в <a href="/profile">профиле</a>, и пакет можно будет купить. Купленные подсказки появятся в запасе сразу после оплаты.</p>
                <a class="primary-action" href="/profile">Развитие детектива →</a>
            </template>
            <p class="quiet-note"><a href="/offer">Оферта</a> · <a href="/privacy">Политика конфиденциальности</a></p>
        </section>

        <BuyHintsDialog v-model="buying" />
    </main>
</template>
