<script setup>
import {ref, onMounted} from 'vue';

const contact = ref(null);
const loading = ref(true);
const error = ref('');

onMounted(async () => {
    try {
        const res = await fetch('/api/contacts');
        if (!res.ok) throw new Error('Ошибка загрузки: ' + res.status);
        contact.value = await res.json();
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="min-h-screen bg-gray-900 flex items-center justify-center px-4 py-20">
        <section class="
            w-full max-w-lg mx-auto rounded-3xl shadow-2xl relative
            bg-gradient-to-b from-gray-800/90 to-gray-900/90
            px-8 py-10 flex flex-col gap-6
            border border-gray-700
            ">
            <div
                class="absolute -inset-1 rounded-3xl blur-2xl bg-gradient-to-r from-indigo-500/25 via-purple-500/10 to-pink-500/15 opacity-70 pointer-events-none"
                aria-hidden="true"
            ></div>
            <h1 class="text-3xl sm:text-4xl font-bold text-white mb-2 tracking-tight text-center relative z-10">
                Контактная информация
            </h1>

            <div v-if="loading" class="text-center text-gray-300 text-lg font-medium relative z-10">Загрузка...</div>
            <div v-else-if="error" class="text-center text-red-500 font-semibold relative z-10">{{ error }}</div>

            <div v-else-if="contact" class="space-y-6 relative z-10">
                <ul class="divide-y divide-gray-700">
                    <li v-if="contact.name" class="py-3 flex items-center gap-4">
                        <span class="text-gray-400 w-32 shrink-0 flex items-center">👤 Имя:</span>
                        <span class="text-gray-100 font-semibold">{{ contact.name }}</span>
                    </li>
                    <li v-if="contact.phone" class="py-3 flex items-center gap-4">
                        <span class="text-gray-400 w-32 shrink-0 flex items-center">📞 Телефон:</span>
                        <span class="text-gray-100 font-mono">{{ contact.phone }}</span>
                    </li>
                    <li v-if="contact.email" class="py-3 flex items-center gap-4">
                        <span class="text-gray-400 w-32 shrink-0 flex items-center">📧 Email:</span>
                        <a :href="`mailto:${contact.email}`" class="text-blue-400 hover:underline">
                            {{ contact.email }}
                        </a>
                    </li>
                    <li v-if="contact.telegram" class="py-3 flex items-center gap-4">
                        <span class="text-gray-400 w-32 shrink-0 flex items-center">💬 Telegram:</span>
                        <a
                            :href="`https://t.me/${contact.telegram.replace('@','')}`"
                            class="text-blue-400 hover:underline"
                            target="_blank" rel="noopener"
                        >
                            {{ contact.telegram }}
                        </a>
                    </li>
                    <li v-if="contact.github" class="py-3 flex items-center gap-4">
                        <span class="text-gray-400 w-32 shrink-0 flex items-center">🐙 GitHub:</span>
                        <a :href="`https://github.com/${contact.github}`" class="text-blue-400 hover:underline"
                           target="_blank">
                            {{ contact.github }}
                        </a>
                    </li>
                </ul>
                <div v-if="contact.socials && contact.socials.length" class="pt-4">
                    <h3 class="text-lg font-bold text-gray-200 mb-2">Другие соцсети:</h3>
                    <ul class="flex flex-col gap-3">
                        <li v-for="(soc, idx) in contact.socials" :key="idx" class="flex items-center gap-3">
                            <span class="inline-block bg-gray-700 text-gray-300 rounded px-2 py-1 text-xs uppercase">
                                {{ soc.type }}
                            </span>
                            <a :href="soc.url" target="_blank" rel="noopener"
                               class="text-blue-400 hover:underline break-all flex items-center gap-1">
                                🔗 <span>{{ soc.url }}</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div v-else class="text-center text-gray-400 relative z-10">Нет данных для отображения.</div>
        </section>
    </div>
</template>

<style scoped>
section {
    box-shadow: 0 4px 40px #6b21a822;
}
</style>
