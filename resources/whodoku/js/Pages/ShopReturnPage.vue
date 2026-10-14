<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import PlayerBar from '../Components/PlayerBar.vue';
import { loadOrder, type OrderState } from '../composables/useShop';
import { loadPlayer } from '../composables/usePlayer';

const props = defineProps<{ order: string }>();

const POLL_MS = 3000;
const GIVE_UP_AFTER = 20;

const state = ref<OrderState | null>(null);
const waited = ref(0);
const unknown = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;

async function poll(): Promise<void> {
    const result = await loadOrder(props.order);
    if (result === null) {
        unknown.value = true;

        return;
    }
    state.value = result;
    if (result.status === 'paid') {
        await loadPlayer(true);

        return;
    }
    if (result.status === 'pending' && waited.value < GIVE_UP_AFTER) {
        waited.value += 1;
        timer = setTimeout(() => { void poll(); }, POLL_MS);
    }
}

onMounted(async () => { await loadPlayer(); await poll(); });
onBeforeUnmount(() => { if (timer) clearTimeout(timer); });
</script>

<template>
    <main class="public-page">
        <header class="public-header">
            <a href="/" class="site-home-link">SelfBio</a>
            <a href="/puzzles" class="brand">Whodoku</a>
            <span class="header-links"><PlayerBar /></span>
        </header>

        <section class="state-card" role="status">
            <template v-if="unknown">
                <h1>Заказ не найден</h1>
                <p>Войдите под тем же детективом, которым покупали. Если деньги списаны, а подсказок нет, напишите нам: мы найдём платёж.</p>
            </template>
            <template v-else-if="state?.status === 'paid'">
                <h1>Оплата прошла</h1>
                <p>Подсказки добавлены: {{ state.quantity }}. Они уже в запасе.</p>
            </template>
            <template v-else-if="state?.status === 'canceled'">
                <h1>Оплата отменена</h1>
                <p>Деньги не списаны. Пакет можно купить снова из профиля или со страницы дела.</p>
            </template>
            <template v-else-if="state?.status === 'refunded' || state?.status === 'failed'">
                <h1>Заказ не выполнен</h1>
                <p>Платёж не подтвердился или был возвращён. Если это ошибка, напишите нам.</p>
            </template>
            <template v-else-if="waited >= GIVE_UP_AFTER">
                <h1>Платёж ещё обрабатывается</h1>
                <p>Подсказки появятся сами, как только платёжная система подтвердит оплату. Обычно это занимает несколько минут.</p>
            </template>
            <template v-else>
                <h1>Проверяем оплату…</h1>
                <p>Это займёт несколько секунд.</p>
            </template>
            <p><a class="primary-action" href="/profile">В профиль</a> <a class="secondary-action" href="/puzzles">К делам</a></p>
        </section>
    </main>
</template>
