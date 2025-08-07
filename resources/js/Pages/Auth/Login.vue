<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
  email: 'test@test.com',
  password: 'password',
  remember: false,
});

const submit = () => {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  });
};
</script>

<template>
  <Head title="Вход в систему" />
  
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 via-white to-purple-50">
    <div class="max-w-md w-full mx-auto">
      <div class="bg-white rounded-2xl shadow-xl p-8">
        <div class="text-center mb-8">
          <h1 class="text-3xl font-bold text-gray-900 mb-2">
            Добро пожаловать! 👋
          </h1>
          <p class="text-gray-600">
            Войдите в свой дашборд
          </p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
              Электронная почта
            </label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              required
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              :class="{ 'border-red-500': form.errors.email }"
            />
            <div v-if="form.errors.email" class="mt-1 text-sm text-red-600">
              {{ form.errors.email }}
            </div>
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
              Пароль
            </label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              required
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              :class="{ 'border-red-500': form.errors.password }"
            />
            <div v-if="form.errors.password" class="mt-1 text-sm text-red-600">
              {{ form.errors.password }}
            </div>
          </div>

          <div class="flex items-center justify-between">
            <label class="flex items-center">
              <input
                v-model="form.remember"
                type="checkbox"
                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
              />
              <span class="ml-2 text-sm text-gray-600">Запомнить меня</span>
            </label>
          </div>

          <button
            type="submit"
            :disabled="form.processing"
            class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium"
          >
            <span v-if="form.processing">Вход...</span>
            <span v-else>Войти в дашборд</span>
          </button>
        </form>

        <!-- Demo Credentials -->
        <div class="mt-6 p-4 bg-blue-50 rounded-lg">
          <h3 class="text-sm font-medium text-blue-900 mb-2">Тестовые данные:</h3>
          <p class="text-sm text-blue-800">
            <strong>Email:</strong> test@test.com<br>
            <strong>Пароль:</strong> password
          </p>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center">
          <p class="text-sm text-gray-500">
            Современный дашборд с Laravel + Vue 3 + Inertia.js
          </p>
        </div>
      </div>
    </div>
  </div>
</template>