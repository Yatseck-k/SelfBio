<script setup>
import {Head, Link, useForm, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import {trans} from 'laravel-vue-i18n';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import SiteShell from '../Components/SiteShell.vue';

const form = useForm({email: ''});
const status = computed(() => usePage().props.status);
function submit() { form.post('/forgot-password'); }
</script>

<template>
    <Head :title="trans('Forgot password')" />
    <SiteShell active="account">
        <div class="page-intro">
            <Breadcrumbs :items="[{label: trans('Sign in'), href: '/login'}, {label: trans('Forgot password')}]" />
            <h1 class="page-title">{{ trans('Forgot password') }}</h1>
            <p class="page-lead">{{ trans('Enter the email of your account and we will send a link to set a new password.') }}</p>
        </div>
        <p v-if="status" class="paper-card form-status" role="status">{{ status }}</p>
        <form v-else class="paper-card form-card" @submit.prevent="submit">
            <div><label for="email" class="site-label">{{ trans('Email') }}</label><input id="email" v-model="form.email" type="email" autocomplete="username" required class="site-input" /><p v-if="form.errors.email" class="site-error">{{ form.errors.email }}</p></div>
            <button type="submit" :disabled="form.processing" class="site-button">{{ trans('Send link') }}</button>
        </form>
        <p class="form-back"><Link href="/login" class="text-link">← {{ trans('Back to sign in') }}</Link></p>
    </SiteShell>
</template>

<style scoped>
.form-card, .form-status { display: grid; gap: 1.25rem; width: min(100%, 28rem); }
.form-card .site-button { width: 100%; }
.form-status { margin: 0; line-height: 1.6; }
.form-back { margin-top: 1.25rem; }
</style>
