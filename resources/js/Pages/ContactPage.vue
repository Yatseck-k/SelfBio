<script setup>
import {Head} from '@inertiajs/vue3';
import {trans} from 'laravel-vue-i18n';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import SiteShell from '../Components/SiteShell.vue';

defineProps({contact: {type: Object, default: null}, embedded: {type: Boolean, default: false}});
</script>

<template>
    <Head v-if="!embedded" :title="trans('Contacts')" />
    <component :is="embedded ? 'div' : SiteShell" v-bind="embedded ? {} : {active: 'contacts'}">
        <div class="page-intro">
            <Breadcrumbs :items="[{label: trans('Contacts')}]" />
            <h1 class="page-title">{{ $page.props.pageTexts?.contacts?.title || trans('Contact Information') }}</h1>
            <p v-if="$page.props.pageTexts?.contacts?.lead" class="page-lead">{{ $page.props.pageTexts.contacts.lead }}</p>
        </div>
        <section class="paper-card" style="max-width: 46rem">
            <dl v-if="contact" class="contact-list">
                <div v-if="contact.name"><dt>{{ trans('Name') }}</dt><dd>{{ contact.name }}</dd></div>
                <div v-if="contact.phone"><dt>{{ trans('Phone') }}</dt><dd><a class="text-link" :href="`tel:${contact.phone}`">{{ contact.phone }}</a></dd></div>
                <div v-if="contact.email"><dt>{{ trans('Email') }}</dt><dd><a class="text-link" :href="`mailto:${contact.email}`">{{ contact.email }}</a></dd></div>
                <div v-if="contact.telegram"><dt>{{ trans('Telegram') }}</dt><dd><a class="text-link" :href="`https://t.me/${contact.telegram.replace('@', '')}`" target="_blank" rel="noopener noreferrer">{{ contact.telegram }}</a></dd></div>
                <div v-if="contact.github"><dt>{{ trans('GitHub') }}</dt><dd><a class="text-link" :href="`https://github.com/${contact.github}`" target="_blank" rel="noopener noreferrer">{{ contact.github }}</a></dd></div>
                <div v-for="(soc, idx) in contact.socials || []" :key="idx"><dt>{{ soc.type }}</dt><dd><a class="text-link" :href="soc.url" target="_blank" rel="noopener noreferrer">{{ soc.url }}</a></dd></div>
            </dl>
            <p v-else style="margin: 0; color: var(--muted)">{{ trans('No data to display') }}.</p>
        </section>
    </component>
</template>

<style scoped>
.contact-list { margin: 0; }
.contact-list > div { display: grid; grid-template-columns: minmax(7rem, 11rem) minmax(0, 1fr); gap: .5rem 1rem; padding: .9rem 0; border-bottom: 1px solid var(--line); }
.contact-list > div:last-child { border-bottom: 0; }
.contact-list dt { color: var(--muted); font-size: .9rem; }
.contact-list dd { min-width: 0; margin: 0; font-weight: 650; overflow-wrap: anywhere; }
@media (max-width: 480px) { .contact-list > div { grid-template-columns: 1fr; gap: .15rem; } }
</style>
