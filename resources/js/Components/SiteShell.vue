<script setup>
import {Link, usePage} from '@inertiajs/vue3';
import {computed, onMounted, ref} from 'vue';
import {trans} from 'laravel-vue-i18n';

defineProps({active: {type: String, default: ''}});
const page = usePage();
const signedIn = computed(() => Boolean(page.props.signedIn));
const brand = computed(() => page.props.brand ?? 'SelfBio');
const locale = computed(() => page.props.locale || 'ru');
const prefix = computed(() => locale.value === 'en' ? '/en' : '');
const homePath = computed(() => locale.value === 'en' ? '/en' : '/');
const theme = ref('dark');

function setTheme(value) {
    theme.value = value;
    document.documentElement.dataset.theme = value;
    localStorage.setItem('selfbio-theme', value);
}
onMounted(() => { theme.value = document.documentElement.dataset.theme || 'dark'; });
</script>

<template>
    <div class="site-frame">
        <a href="#main" class="skip-link">{{ trans('Skip to content') }}</a>
        <header class="site-header">
            <a :href="homePath" class="site-brand">{{ brand }}</a>
            <nav class="site-nav" :aria-label="trans('Main navigation')">
                <a :href="homePath" :class="{'is-active': active === 'home'}" :aria-current="active === 'home' ? 'page' : undefined">{{ trans('Home') }}</a>
                <a :href="`${prefix}/about`" :class="{'is-active': active === 'about'}" :aria-current="active === 'about' ? 'page' : undefined">{{ trans('About me') }}</a>
                <a href="/blog" :class="{'is-active': active === 'blog'}" :aria-current="active === 'blog' ? 'page' : undefined">{{ trans('Blog') }}</a>
                <a :href="`${prefix}/contacts`" :class="{'is-active': active === 'contacts'}" :aria-current="active === 'contacts' ? 'page' : undefined">{{ trans('Contacts') }}</a>
                <a href="/puzzles" :class="{'is-active': active === 'game'}" :aria-current="active === 'game' ? 'page' : undefined">Whodoku</a>
                <a :href="signedIn ? '/account' : '/login'" :class="{'is-active': active === 'account'}" class="site-nav-action">{{ signedIn ? trans('Personal account') : trans('Sign in') }}</a>
                <Link v-if="signedIn" href="/logout" method="post" as="button">{{ trans('Sign out') }}</Link>
            </nav>
            <div class="site-controls">
                <a v-if="['home', 'about', 'contacts'].includes(active)" :href="locale === 'en' ? (active === 'about' ? '/about' : active === 'contacts' ? '/contacts' : '/') : (active === 'about' ? '/en/about' : active === 'contacts' ? '/en/contacts' : '/en')" class="locale-switch" :hreflang="locale === 'en' ? 'ru' : 'en'" :aria-label="trans('Switch language')">{{ locale === 'en' ? 'RU' : 'EN' }}</a>
                <button type="button" class="theme-switch" :aria-label="trans('Switch theme')" @click="setTheme(theme === 'dark' ? 'light' : 'dark')">{{ theme === 'dark' ? '☀' : '☾' }}</button>
            </div>
        </header>
        <main id="main" class="site-content"><slot /></main>
    </div>
</template>
