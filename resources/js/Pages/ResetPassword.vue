<script setup>
import {Head, Link, useForm} from '@inertiajs/vue3';
import {trans} from 'laravel-vue-i18n';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import SiteShell from '../Components/SiteShell.vue';

const props = defineProps({token: {type: String, required: true}, email: {type: String, default: ''}});
const form = useForm({token: props.token, email: props.email, password: '', password_confirmation: ''});
function submit() { form.post('/reset-password', {onFinish: () => form.reset('password', 'password_confirmation')}); }
</script>

<template>
    <Head :title="trans('New password')" />
    <SiteShell active="account">
        <div class="page-intro">
            <Breadcrumbs :items="[{label: trans('Sign in'), href: '/login'}, {label: trans('New password')}]" />
            <h1 class="page-title">{{ trans('New password') }}</h1>
            <p class="page-lead">{{ trans('At least 12 characters.') }}</p>
        </div>
        <form class="paper-card form-card" @submit.prevent="submit">
            <div><label for="email" class="site-label">{{ trans('Email') }}</label><input id="email" v-model="form.email" type="email" autocomplete="username" required class="site-input" /><p v-if="form.errors.email" class="site-error">{{ form.errors.email }}</p></div>
            <div><label for="password" class="site-label">{{ trans('New password') }}</label><input id="password" v-model="form.password" type="password" autocomplete="new-password" minlength="12" required class="site-input" /><p v-if="form.errors.password" class="site-error">{{ form.errors.password }}</p></div>
            <div><label for="password_confirmation" class="site-label">{{ trans('Repeat password') }}</label><input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" minlength="12" required class="site-input" /></div>
            <button type="submit" :disabled="form.processing" class="site-button">{{ trans('Save password') }}</button>
        </form>
        <p class="form-back"><Link href="/forgot-password" class="text-link">{{ trans('Request a new link') }}</Link></p>
    </SiteShell>
</template>

<style scoped>
.form-card { display: grid; gap: 1.25rem; width: min(100%, 28rem); }
.form-card .site-button { width: 100%; }
.form-back { margin-top: 1.25rem; }
</style>
