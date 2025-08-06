<script setup>
import { ref, onMounted } from 'vue';

// Используем defineProps для slug
const props = defineProps({ slug: String });

const post = ref(null);
const loading = ref(true);
const error = ref('');

onMounted(async () => {
    try {
        // Лучше относительный путь (бэкенд сам поймёт url, без localhost)
        const res = await fetch(`/api/blog/${props.slug}`);
        if (!res.ok) throw new Error('Пост не найден');
        post.value = await res.json();
    } catch (e) {
        error.value = e.message || 'Ошибка загрузки';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-gray-950 via-gray-900 to-gray-800 px-4 py-14 flex flex-col items-center">
        <div class="w-full max-w-2xl relative rounded-3xl shadow-2xl border border-gray-800 bg-gradient-to-br from-gray-800/90 to-gray-900/90 px-8 py-10 flex flex-col gap-6">
            <!-- Accent blur highlight -->
            <div
                class="absolute -inset-3 pointer-events-none blur-2xl rounded-3xl bg-gradient-to-r from-indigo-500/30 via-purple-500/10 to-pink-500/15 opacity-70"
                aria-hidden="true"
            ></div>
            <div v-if="loading" class="text-lg text-gray-300 text-center z-10">Загрузка...</div>
            <div v-else-if="error" class="text-lg text-red-400 text-center z-10">{{ error }}</div>
            <div v-else-if="post" class="flex flex-col gap-4 relative z-10">
                <h1 class="text-3xl sm:text-4xl font-bold text-white mb-1 text-center leading-tight">{{ post.title }}</h1>
                <div class="text-gray-400 text-sm mb-2 text-center">
                    {{ post.published_at ? new Date(post.published_at).toLocaleDateString('ru-RU') : '' }}
                </div>
                <div v-if="post.image" class="flex justify-center">
                    <img
                        :src="`/storage/blog/${post.image}`"
                        :alt="post.title"
                        class="w-full max-h-80 object-cover rounded-xl border border-gray-700 shadow-md"
                        loading="lazy"
                    >
                </div>
                <div class="text-gray-200 leading-relaxed text-lg prose prose-invert max-w-none"
                     v-html="post.body"></div>
            </div>
            <div v-else class="text-gray-400 text-center z-10">Пост не найден.</div>
        </div>
    </div>
</template>

<style scoped>
/* Для поддержки типографики — если вы используете @tailwindcss/typography */
.prose {
    /* Tailwind Typography Base */
    color: inherit;
}
</style>
