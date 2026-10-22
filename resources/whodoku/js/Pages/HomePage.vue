<script setup lang="ts">
import { useBrand } from '../composables/useBrand';

const brand = useBrand();
</script>

<template>
    <main class="page-shell">
        <div class="page-card">
            <a class="quiet-link" href="/">← {{ brand }}</a>
            <p class="eyebrow">Whodoku</p>
            <h1>Детективные головоломки</h1>
            <p class="intro">
                Логические детективные дела с картой, показаниями и одним
                правильным ответом.
            </p>
            <a class="admin-link" href="/puzzles">Открыть каталог дел</a>
            <p class="legal-links"><a href="/offer">Оферта</a> · <a href="/privacy">Политика конфиденциальности</a></p>
        </div>
    </main>
</template>
