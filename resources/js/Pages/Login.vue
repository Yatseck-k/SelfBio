<script setup>
import {Head, useForm} from '@inertiajs/vue3';
import {trans} from 'laravel-vue-i18n';
import SiteShell from '../Components/SiteShell.vue';

const form = useForm({email: '', password: ''});
function submit() { form.post('/login', {onFinish: () => form.reset('password')}); }
</script>

<template>
    <Head :title="trans('Sign in')" />
    <SiteShell active="repair">
        <div class="page-intro">
            <p class="eyebrow">SelfBio / {{ trans('Renovation materials') }}</p>
            <h1 class="page-title">{{ trans('Sign in') }}</h1>
            <p class="page-lead">{{ trans('Sign in to view renovation materials') }}</p>
        </div>
        <form class="paper-card login-card" @submit.prevent="submit">
            <div><label for="email" class="site-label">{{ trans('Email') }}</label><input id="email" v-model="form.email" type="email" autocomplete="username" required class="site-input" /><p v-if="form.errors.email" class="site-error">{{ form.errors.email }}</p></div>
            <div><label for="password" class="site-label">{{ trans('Password') }}</label><input id="password" v-model="form.password" type="password" autocomplete="current-password" required class="site-input" /><p v-if="form.errors.password" class="site-error">{{ form.errors.password }}</p></div>
            <button type="submit" :disabled="form.processing" class="site-button">{{ trans('Sign in') }}</button>
        </form>
    </SiteShell>
</template>

<style scoped>
.login-card { display: grid; gap: 1.25rem; width: min(100%, 28rem); }
.login-card .site-button { width: 100%; }
</style>
