<script setup>
import {Head, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import {trans} from 'laravel-vue-i18n';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import SiteShell from '../Components/SiteShell.vue';

defineProps({profile: {type: Object, required: true}});
const locale = computed(() => usePage().props.locale || 'ru');
</script>

<template>
    <Head :title="`${profile.about_title} — ${profile.name}`" />
    <SiteShell active="about">
        <div class="page-intro">
            <Breadcrumbs :items="[{label: profile.about_title}]" />
            <p class="eyebrow">{{ profile.about_eyebrow }}</p>
            <h1 class="page-title">{{ profile.about_title }}</h1>
            <p class="page-lead">{{ profile.about_lead }}</p>
        </div>
        <div class="about-layout">
            <section class="paper-card about-copy" :aria-label="trans('Professional profile')">
                <p v-for="paragraph in profile.about" :key="paragraph">{{ paragraph }}</p>
            </section>
            <aside class="about-sidebar">
                <div v-if="profile.focus_enabled" class="paper-card"><h2>{{ profile.focus_title }}</h2><ul><li v-for="item in profile.focus" :key="item.title">{{ item.title }}</li></ul></div>
                <a class="site-button" :href="`${locale === 'en' ? '/en' : ''}/contacts`">{{ profile.contact_button }}</a>
            </aside>
        </div>
    </SiteShell>
</template>
