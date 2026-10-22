<script setup>
import {onMounted, ref} from 'vue';
import {Head, Link} from '@inertiajs/vue3';
import {trans} from 'laravel-vue-i18n';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import SiteShell from '../Components/SiteShell.vue';

defineProps({embedded: {type: Boolean, default: false}});
const posts = ref([]);
const loading = ref(true);
const error = ref('');
const pagination = ref({});

async function fetchPosts(page = 1) {
    loading.value = true;
    error.value = '';
    try {
        const res = await fetch(`/api/blog?page=${page}`);
        if (!res.ok) throw new Error(`${trans('Loading error')}: ${res.status}`);
        const data = await res.json();
        posts.value = data.data || [];
        pagination.value = {current: data.current_page, last: data.last_page, next: data.next_page_url, prev: data.prev_page_url};
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}
onMounted(fetchPosts);
</script>

<template>
    <Head v-if="!embedded" :title="trans('Blog')" />
    <component :is="embedded ? 'div' : SiteShell" v-bind="embedded ? {} : {active: 'blog'}">
        <div class="page-intro">
            <Breadcrumbs :items="[{label: trans('Blog')}]" />
            <h1 class="page-title">{{ $page.props.pageTexts?.blog?.title || trans('Blog') }}</h1>
            <p v-if="$page.props.pageTexts?.blog?.lead" class="page-lead">{{ $page.props.pageTexts.blog.lead }}</p>
        </div>
        <p v-if="loading" role="status">{{ trans('Loading...') }}</p>
        <div v-else-if="error" class="paper-card site-error" role="alert">{{ error }} <button class="site-button site-button-secondary" type="button" @click="fetchPosts()">{{ trans('Retry') }}</button></div>
        <template v-else>
            <div v-if="posts.length" class="post-list">
                <article v-for="post in posts" :key="post.id" class="paper-card post-card">
                    <img v-if="post.image_url" :src="post.image_url" :alt="post.title" loading="lazy" />
                    <div>
                        <p class="eyebrow">{{ post.published_at ? new Date(post.published_at).toLocaleDateString('ru-RU') : '—' }}</p>
                        <h2><Link :href="`/blog/${post.slug}`">{{ post.title }}</Link></h2>
                        <p>{{ post.preview || '…' }}</p>
                        <Link :href="`/blog/${post.slug}`" class="text-link">{{ trans('Read more') }} →</Link>
                    </div>
                </article>
            </div>
            <div v-else class="paper-card" style="max-width: 46rem">{{ trans('No posts yet') }}</div>
            <nav v-if="pagination.last > 1" class="pagination" :aria-label="trans('Page')">
                <button type="button" class="site-button site-button-secondary" :disabled="!pagination.prev" @click="fetchPosts(pagination.current - 1)">{{ trans('Previous') }}</button>
                <span>{{ trans('Page') }} {{ pagination.current }} / {{ pagination.last }}</span>
                <button type="button" class="site-button site-button-secondary" :disabled="!pagination.next" @click="fetchPosts(pagination.current + 1)">{{ trans('Next') }}</button>
            </nav>
        </template>
    </component>
</template>

<style scoped>
.post-list { display: grid; gap: 1rem; }
.post-card { display: grid; grid-template-columns: minmax(0, 12rem) minmax(0, 1fr); gap: 1.5rem; }
.post-card:not(:has(img)) { grid-template-columns: 1fr; }
.post-card img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: .55rem; }
.post-card h2 { margin: 0 0 .55rem; font-family: var(--font-heading); font-size: clamp(1.3rem, 3vw, 1.8rem); font-weight: 700; line-height: 1.25; }
.post-card h2 a { text-decoration: none; }
.post-card h2 a:hover { color: var(--accent-hover); }
.post-card p:not(.eyebrow) { color: var(--muted); line-height: 1.6; }
.pagination { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 1rem; margin-top: 1.5rem; }
@media (max-width: 600px) { .post-card { grid-template-columns: 1fr; } .post-card img { max-height: 15rem; } }
</style>
