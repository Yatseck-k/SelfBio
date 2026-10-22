<script setup>
import {Link, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import {trans} from 'laravel-vue-i18n';

defineProps({active: {type: String, default: ''}});
const signedIn = computed(() => Boolean(usePage().props.signedIn));
const brand = computed(() => usePage().props.brand ?? 'SelfBio');
</script>

<template>
    <div class="site-frame">
        <a href="#main" class="skip-link">{{ trans('Skip to content') }}</a>
        <header class="site-header">
            <Link href="/" class="site-brand">{{ brand }}</Link>
            <nav class="site-nav" :aria-label="trans('Home')">
                <a href="/#contacts" :class="{'is-active': active === 'contacts'}" :aria-current="active === 'contacts' ? 'page' : undefined">{{ trans('Contacts') }}</a>
                <a href="/#blog" :class="{'is-active': active === 'blog'}" :aria-current="active === 'blog' ? 'page' : undefined">{{ trans('Blog') }}</a>
                <a href="/puzzles" :class="{'is-active': active === 'game'}" :aria-current="active === 'game' ? 'page' : undefined">Whodoku</a>
                <a :href="signedIn ? '/account' : '/login'" :class="{'is-active': active === 'account'}" class="site-nav-action">{{ signedIn ? trans('Personal account') : trans('Sign in') }}</a>
                <Link v-if="signedIn" href="/logout" method="post" as="button">{{ trans('Sign out') }}</Link>
            </nav>
        </header>
        <main id="main" class="site-content"><slot /></main>
    </div>
</template>
