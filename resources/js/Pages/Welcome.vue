<script setup>
import { Head, router } from '@inertiajs/vue3';

function goToContacts() {
    router.visit('/contacts');
}
function goToBlog() {
    router.visit('/blog');
}
</script>

<template>
    <Head title="Главная" />
    <div class="min-h-screen flex items-center justify-center bg-gray-900">
        <div class="w-full max-w-xl mx-auto p-8 rounded-2xl shadow-xl flex flex-col items-center gap-10">
            <div class="text-center space-y-6">
                <h1 class="text-4xl sm:text-5xl font-extrabold text-white mb-2">Привет, это я 👋</h1>
                <p class="text-xl text-gray-200 font-medium tracking-wide">
                    Я&nbsp;— backend-разработчик.<br>
                    Люблю чистый код, Laravel, автоматизацию и API.<br>
                    Помогаю запускать быстрые, удобные и устойчивые сервисы.<br>
                    Веду блог о технологиях.
                </p>
            </div>
            <div class="flex gap-6 justify-center">
                <button
                    @click="goToContacts"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-7 rounded-lg shadow transition"
                >
                    Мои контакты
                </button>
                <button
                    @click="goToBlog"
                    class="bg-white/20 hover:bg-white/30 text-white font-bold py-3 px-7 rounded-lg shadow transition border border-white/10"
                >
                    Мой блог
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>

section {
    box-shadow: 0 4px 40px #00f2ff2a;
}

</style>
