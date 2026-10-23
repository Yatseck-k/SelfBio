<script setup>
import {Head} from '@inertiajs/vue3';
import {computed} from 'vue';
import {trans} from 'laravel-vue-i18n';
import {usePage} from '@inertiajs/vue3';
import SiteShell from '../Components/SiteShell.vue';

const props = defineProps({
    profile: {type: Object, required: true},
    contact: {type: Object, default: null},
    posts: {type: Array, default: () => []},
});
const locale = computed(() => usePage().props.locale || 'ru');
const link = (page) => `${locale.value === 'en' ? '/en' : ''}/${page}`;
const orderedSections = computed(() => ['focus', 'stack', 'writing', 'contact']
    .sort((first, second) => Number(props.profile[`${first}_order`]) - Number(props.profile[`${second}_order`])));
const visibleSections = computed(() => orderedSections.value.filter((section) => props.profile[`${section}_enabled`]));
const sectionNumber = (section) => String(visibleSections.value.indexOf(section) + 1).padStart(2, '0');
</script>

<template>
    <Head :title="`${profile.name} — ${profile.role}`" />
    <SiteShell active="home">
        <section class="hero" aria-labelledby="hero-title">
            <div>
                <p class="eyebrow">{{ profile.hero_eyebrow }}</p>
                <h1 id="hero-title" class="hero-title">{{ profile.name }}<span>{{ profile.role }}</span></h1>
                <p class="hero-lead">{{ profile.lead }}</p>
                <div class="hero-actions">
                    <a class="site-button" :href="link('about')">{{ profile.about_button }}</a>
                    <a class="site-button site-button-secondary" :href="link('contacts')">{{ profile.contact_button }}</a>
                </div>
            </div>
            <div v-if="profile.hero_tags.length" class="hero-aside" :aria-label="trans('Technology')">
                <span v-for="tag in profile.hero_tags" :key="tag">{{ tag }}</span>
            </div>
        </section>

        <template v-for="section in visibleSections" :key="section">
        <section v-if="section === 'focus'" class="portfolio-section" aria-labelledby="focus-title">
            <div class="section-heading"><p class="eyebrow">{{ sectionNumber(section) }} / {{ profile.focus_eyebrow }}</p><h2 id="focus-title">{{ profile.focus_title }}</h2></div>
            <div class="focus-grid">
                <article v-for="item in profile.focus" :key="item.title" class="paper-card focus-card">
                    <h3>{{ item.title }}</h3><p>{{ item.text }}</p>
                </article>
            </div>
        </section>

        <section v-if="section === 'stack'" class="portfolio-section" aria-labelledby="stack-title">
            <div class="section-heading"><p class="eyebrow">{{ sectionNumber(section) }} / {{ profile.stack_eyebrow }}</p><h2 id="stack-title">{{ profile.stack_title }}</h2></div>
            <div class="stack-grid">
                <div v-for="group in profile.stack" :key="group.title" class="stack-group">
                    <h3>{{ group.title }}</h3>
                    <ul class="tag-list"><li v-for="item in group.items" :key="item">{{ item }}</li></ul>
                </div>
            </div>
        </section>

        <section v-if="section === 'writing'" class="portfolio-section" aria-labelledby="writing-title">
            <div class="section-heading"><p class="eyebrow">{{ sectionNumber(section) }} / {{ profile.writing_eyebrow }}</p><h2 id="writing-title">{{ profile.writing_title }}</h2></div>
            <div v-if="posts.length" class="writing-grid">
                <article v-for="post in posts" :key="post.slug" class="paper-card writing-card">
                    <h3><a :href="`/blog/${post.slug}`">{{ post.title }}</a></h3>
                    <p>{{ post.preview }}</p>
                </article>
            </div>
            <p v-else class="section-empty">{{ trans('No posts yet') }}</p>
            <a class="text-link section-link" href="/blog">{{ profile.articles_button }} →</a>
        </section>

        <section v-if="section === 'contact'" class="portfolio-section contact-banner" aria-labelledby="contact-title">
            <div><p class="eyebrow">{{ sectionNumber(section) }} / {{ profile.contact_eyebrow }}</p><h2 id="contact-title">{{ profile.contact_title }}</h2><p>{{ profile.contact_text }}</p></div>
            <a class="site-button" :href="link('contacts')">{{ profile.contact_button }} →</a>
        </section>
        </template>
    </SiteShell>
</template>
