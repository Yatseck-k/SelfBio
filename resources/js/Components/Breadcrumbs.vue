<script setup>
import {Link, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import {trans} from 'laravel-vue-i18n';

// items: [{label, href?}] after the name of the site; the last one is the page itself and has no link.
const props = defineProps({items: {type: Array, default: () => []}});
const brand = computed(() => usePage().props.brand ?? 'SelfBio');
const trail = computed(() => [{label: brand.value, href: '/'}, ...props.items]);
</script>

<template>
    <nav class="breadcrumbs" :aria-label="trans('Breadcrumbs')">
        <ol>
            <li v-for="(item, index) in trail" :key="index">
                <Link v-if="index < trail.length - 1 && item.href" :href="item.href">{{ item.label }}</Link>
                <span v-else :aria-current="index === trail.length - 1 ? 'page' : undefined">{{ item.label }}</span>
            </li>
        </ol>
    </nav>
</template>
