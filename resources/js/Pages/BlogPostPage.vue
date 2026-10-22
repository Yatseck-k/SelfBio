<script setup>
import {onMounted, ref} from 'vue';
import {Head} from '@inertiajs/vue3';
import {trans} from 'laravel-vue-i18n';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import SiteShell from '../Components/SiteShell.vue';

const props = defineProps({slug: String});
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
    <Head :title="post?.title || trans('Blog')" />
    <SiteShell active="blog">
        <Breadcrumbs :items="[{label: trans('Blog'), href: '/blog'}, {label: post?.title || '…'}]" />
        <p v-if="loading" role="status">{{ trans('Loading...') }}</p>
        <p v-else-if="error" class="site-error" role="alert">{{ error }}</p>
        <article v-else-if="post" class="paper-card article-card">
            <p class="eyebrow">{{ post.published_at ? new Date(post.published_at).toLocaleDateString('ru-RU') : '' }}</p>
            <h1 class="page-title">{{ post.title }}</h1>
            <img v-if="post.image_url" :src="post.image_url" :alt="post.title" loading="lazy" />
            <div class="article-body" v-html="post.body" />
        </article>
        <p v-else>{{ trans('Post not found.') }}</p>
    </SiteShell>
</template>

<style scoped>
.article-card { max-width: 52rem; margin-top: 1.25rem; }
.article-card img { width: 100%; max-height: 26rem; margin-top: 1.5rem; object-fit: cover; border-radius: .5rem; }
.article-body { max-width: 44rem; margin-top: 1.5rem; font-size: 1.06rem; line-height: 1.8; overflow-wrap: anywhere; }
.article-body :deep(img) { max-width: 100%; height: auto; border-radius: .5rem; }
.article-body :deep(p) { margin: 0 0 1rem; }
.article-body :deep(h2), .article-body :deep(h3) { margin: 1.9rem 0 .8rem; font-family: var(--font-heading); font-weight: 700; line-height: 1.25; }
.article-body :deep(ul), .article-body :deep(ol) { padding-left: 1.5rem; }
.article-body :deep(a) { color: var(--accent-hover); text-underline-offset: .2em; }
.article-body :deep(blockquote) { margin: 1.5rem 0; padding-left: 1rem; border-left: 4px solid var(--accent); color: var(--muted); }
.article-body :deep(code) { padding: .1rem .3rem; border-radius: .25rem; background: #374151; }
</style>
