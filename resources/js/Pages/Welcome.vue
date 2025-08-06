<script setup>
import {Head, router} from '@inertiajs/vue3';

function goToContacts() {
    router.visit('/contacts');
}

function goToBlog() {
    router.visit('/blog');
}
</script>

<template>
    <Head title="Главная"/>
    <div
        class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-950 via-gray-900 to-gray-800 relative overflow-hidden">
        <div
            class="absolute -inset-0 z-0 pointer-events-none flex items-center justify-center"
            aria-hidden="true"
        >
            <div
                class="w-[480px] h-[480px] rounded-3xl blur-[80px] bg-gradient-to-r from-indigo-500/30 via-purple-500/20 to-pink-500/20"
            ></div>
        </div>
        <div
            class="relative z-10 w-full max-w-xl mx-auto px-8 py-12 rounded-3xl shadow-2xl bg-gray-900/90 border border-gray-800 flex flex-col items-center gap-10 inner-shadow">
            <div class="shine"></div>
            <div class="text-center space-y-6">
                <h1 class="text-4xl sm:text-5xl font-extrabold text-white mb-2 drop-shadow-lg">
                    Привет, это я <span>👋</span>
                </h1>
                <p class="text-xl text-gray-200 font-medium tracking-wide">
                    Я — backend-разработчик.<br>
                    Люблю чистый код, Laravel, автоматизацию и API.<br>
                    Помогаю запускать быстрые, удобные и устойчивые сервисы.<br>
                    Веду блог о технологиях.
                </p>
            </div>
            <div class="flex gap-6 justify-center w-full">
                <button
                    @click="goToContacts"
                    class="bg-gradient-to-r from-indigo-600/20 via-purple-600/20 to-pink-600/20 hover:from-indigo-600/30 hover:to-pink-600/30 text-white font-bold py-3 px-7 rounded-lg border border-white/10 shadow-lg transition"
                >
                    Мои контакты
                </button>
                <button
                    @click="goToBlog"
                    class="bg-gradient-to-r from-indigo-600/20 via-purple-600/20 to-pink-600/20 hover:from-indigo-600/30 hover:to-pink-600/30 text-white font-bold py-3 px-7 rounded-lg border border-white/10 shadow-lg transition"
                >
                    Мой блог
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.inner-shadow {
    box-shadow: 0 4px 40px 0 #6b21a855,
    inset 0 1.5px 32px #000a,
    inset 0 0 18px 2px #6366f144;
    position: relative;
}
</style>

