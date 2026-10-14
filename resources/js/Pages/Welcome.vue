<script setup>
import {Head} from '@inertiajs/vue3';
import {onMounted, onUnmounted, ref} from 'vue';
import {trans} from 'laravel-vue-i18n';
import SiteShell from '../Components/SiteShell.vue';
import BlogPage from './BlogPage.vue';
import ContactPage from './ContactPage.vue';

defineProps({contact: {type: Object, default: null}, welcome: {type: Object, default: null}});
const activeTab = ref('contacts');
const syncTab = () => { activeTab.value = window.location.hash === '#blog' ? 'blog' : 'contacts'; };
onMounted(() => { syncTab(); window.addEventListener('hashchange', syncTab); });
onUnmounted(() => window.removeEventListener('hashchange', syncTab));
</script>

<template>
    <Head :title="activeTab === 'contacts' ? trans('Contacts') : trans('Blog')" />
    <SiteShell :active="activeTab">
        <ContactPage v-if="activeTab === 'contacts'" :contact="contact" :welcome="welcome" embedded />
        <BlogPage v-else embedded />
    </SiteShell>
</template>
