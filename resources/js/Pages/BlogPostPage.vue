<script setup>
import { ref, onMounted } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({ slug: String });

const post = ref(null);
const loading = ref(true);
const error = ref('');

onMounted(async () => {
    try {
        const res = await fetch(`/api/blog/${props.slug}`);
        if (!res.ok) throw new Error(trans('Post not found'));
        post.value = await res.json();
    } catch (e) {
        error.value = e.message || trans('Loading error');
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div
        class="min-h-screen bg-gradient-to-br from-gray-950 via-gray-900 to-gray-800 px-4 py-14 flex flex-col items-center">
        <div
            class="w-full max-w-2xl relative rounded-3xl shadow-2xl border border-gray-800 bg-gradient-to-br from-gray-800/90 to-gray-900/90 px-8 py-10 flex flex-col gap-6">
            <!-- Accent blur highlight -->
            <div
                class="absolute -inset-3 pointer-events-none blur-2xl rounded-3xl bg-gradient-to-r from-indigo-500/30 via-purple-500/10 to-pink-500/15 opacity-70"
                aria-hidden="true"
            ></div>
            <div v-if="loading" class="text-lg text-gray-300 text-center z-10">{{ trans('Loading...') }}</div>
            <div v-else-if="error" class="text-lg text-red-400 text-center z-10">{{ error }}</div>
            <div v-else-if="post" class="flex flex-col gap-4 relative z-10">
                <h1 class="text-3xl sm:text-4xl font-bold text-white mb-1 text-center leading-tight">{{
                        post.title
                    }}</h1>
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
                <div class="text-gray-100 leading-relaxed text-lg max-w-none custom-content"
                     v-html="post.body"></div>
            </div>
            <div v-else class="text-gray-400 text-center z-10">{{ trans('Post not found.') }}</div>
        </div>
    </div>
</template>

<style scoped>
.custom-content {
    color: #f3f4f6;
    line-height: 1.75;
}

.custom-content :deep(p) {
    color: #f3f4f6;
    margin-bottom: 1rem;
}

.custom-content :deep(h1),
.custom-content :deep(h2),
.custom-content :deep(h3),
.custom-content :deep(h4),
.custom-content :deep(h5),
.custom-content :deep(h6) {
    color: #ffffff;
    font-weight: 600;
    margin-top: 2rem;
    margin-bottom: 1rem;
}

.custom-content :deep(h1) { font-size: 2.25rem; }
.custom-content :deep(h2) { font-size: 1.875rem; }
.custom-content :deep(h3) { font-size: 1.5rem; }
.custom-content :deep(h4) { font-size: 1.25rem; }

.custom-content :deep(ul),
.custom-content :deep(ol) {
    margin: 1rem 0;
    padding-left: 1.5rem;
}

.custom-content :deep(li) {
    color: #f3f4f6;
    margin-bottom: 0.5rem;
}

.custom-content :deep(blockquote) {
    color: #e5e7eb;
    border-left: 4px solid #6366f1;
    padding-left: 1rem;
    margin: 1.5rem 0;
    font-style: italic;
}

.custom-content :deep(code) {
    color: #fbbf24;
    background-color: rgba(55, 65, 81, 0.5);
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-family: ui-monospace, SFMono-Regular, monospace;
}

.custom-content :deep(a) {
    color: #60a5fa;
    text-decoration: underline;
}

.custom-content :deep(strong) {
    color: #ffffff;
    font-weight: 600;
}
</style>
