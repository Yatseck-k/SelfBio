<script setup>
import {ref, onMounted} from 'vue';
import {router} from '@inertiajs/vue3';
import {trans} from 'laravel-vue-i18n';

const posts = ref([]);
const loading = ref(true);
const error = ref('');
const pagination = ref({});

const fetchPosts = async (page = 1) => {
    loading.value = true;
    error.value = '';
    try {
        const res = await fetch(`/api/blog?page=${page}`);
        if (!res.ok) throw new Error(trans('Loading error') + ': ' + res.status);
        const data = await res.json();
        posts.value = data.data || [];
        pagination.value = {
            current: data.current_page,
            last: data.last_page,
            next: data.next_page_url,
            prev: data.prev_page_url,
        };
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
};

onMounted(() => fetchPosts());
</script>

<template>
    <div
        class="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#151624] via-[#24263a] to-[#171720] px-2 py-16">
        <section
            class="w-full max-w-4xl min-h-[320px] mx-auto px-12 py-10 rounded-2xl border border-gray-700 bg-gradient-to-r from-[#222230f5] via-[#191a23f8] to-[#232334e2] flex flex-col gap-10 flat-modern"
        >
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-black text-white mb-8 tracking-tight text-left drop-shadow">
                <span>{{ trans('Blog') }}</span>
            </h1>
            <div v-if="loading" class="text-lg text-gray-300 my-10 animate-pulse">{{ trans('Loading...') }}</div>
            <div v-else-if="error" class="text-lg text-red-400 my-10">{{ error }}</div>
            <div v-else>
                <div v-if="posts && posts.length" class="flex flex-col gap-8">
                    <div
                        v-for="post in posts" :key="post.id"
                        class="flex flex-row items-stretch gap-7 px-7 py-6 bg-gradient-to-r from-gray-800/80 via-gray-900/80 to-gray-800/80 border border-gray-700 rounded-xl hover:border-indigo-500/80 ring-0 hover:ring-2 ring-indigo-500/30 transition group hover:scale-[1.02]"
                    >
                        <div v-if="post.image" class="flex-none w-40 sm:w-56">
                            <img
                                :src="`/storage/blog/${post.image}`"
                                :alt="post.title"
                                class="w-full h-40 sm:h-44 object-cover rounded-lg border border-gray-700 shadow-sm group-hover:shadow-md group-hover:border-indigo-500/70 transition"
                                loading="lazy"
                            >
                        </div>
                        <div class="flex flex-col flex-1 justify-between min-w-0">
                            <div>
                                <h2 class="text-xl sm:text-2xl font-bold text-white mb-1 truncate group-hover:text-indigo-300 transition-colors">
                                    {{ post.title }}
                                </h2>
                                <div class="text-gray-400 text-sm mb-3">
                                    {{
                                        post.published_at ? new Date(post.published_at).toLocaleDateString('ru-RU') : '—'
                                    }}
                                </div>
                                <p class="text-gray-100 leading-relaxed mb-3 min-h-[44px] line-clamp-3">
                                    {{ post.preview || '...' }}
                                </p>
                            </div>
                            <div>
                                <button
                                    class="bg-gradient-to-r from-indigo-600/20 via-purple-600/20 to-pink-600/20 hover:from-indigo-600/30 hover:to-pink-600/30 text-white font-bold py-3 px-7 rounded-lg border border-white/10 shadow-lg transition"
                                    @click="router.visit(`/blog/${post.slug}`)"
                                >
                                    {{ trans('Read more') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-gray-400 text-center mt-8">{{ trans('No posts yet') }}</div>

                <!-- Пагинация -->
                <div v-if="pagination.last > 1" class="flex gap-3 justify-center mt-10 pb-2">
                    <button
                        :disabled="!pagination.prev"
                        @click="fetchPosts(pagination.current - 1)"
                        class="bg-gradient-to-r from-indigo-600/20 via-purple-600/20 to-pink-600/20 hover:from-indigo-600/30 hover:to-pink-600/30 text-white font-bold py-3 px-7 rounded-lg border border-white/10 shadow-lg transition disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        {{ trans('Previous') }}
                    </button>
                    <span class="text-gray-300 px-3 text-lg font-semibold select-none">
                    {{ trans('Page') }} {{ pagination.current }} / {{ pagination.last }}
                </span>
                    <button
                        :disabled="!pagination.next"
                        @click="fetchPosts(pagination.current + 1)"
                        class="bg-gradient-to-r from-indigo-600/20 via-purple-600/20 to-pink-600/20 hover:from-indigo-600/30 hover:to-pink-600/30 text-white font-bold py-3 px-7 rounded-lg border border-white/10 shadow-lg transition disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        {{ trans('Next') }}
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>
