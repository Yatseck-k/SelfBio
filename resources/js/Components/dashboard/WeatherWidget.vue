<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  isDarkMode: {
    type: Boolean,
    default: false
  }
});

const weather = ref(null);
const loading = ref(true);
const error = ref(null);
const forecast = ref([]);

const fetchWeather = async () => {
  try {
    loading.value = true;
    error.value = null;
    
    const [currentResponse, forecastResponse] = await Promise.all([
      axios.get('/api/weather/current?city=Краснодар'),
      axios.get('/api/weather/forecast?city=Краснодар')
    ]);
    
    weather.value = currentResponse.data;
    forecast.value = forecastResponse.data.forecast || [];
  } catch (err) {
    error.value = 'Не удалось загрузить данные о погоде';
    console.error('Weather fetch error:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchWeather();
  // Обновляем каждые 30 минут
  setInterval(fetchWeather, 30 * 60 * 1000);
});

const getWeatherIcon = (iconCode) => {
  const iconMap = {
    '01d': '☀️', '01n': '🌙',
    '02d': '⛅', '02n': '☁️',
    '03d': '☁️', '03n': '☁️',
    '04d': '☁️', '04n': '☁️',
    '09d': '🌧️', '09n': '🌧️',
    '10d': '🌦️', '10n': '🌧️',
    '11d': '⛈️', '11n': '⛈️',
    '13d': '❄️', '13n': '❄️',
    '50d': '🌫️', '50n': '🌫️'
  };
  return iconMap[iconCode] || '🌤️';
};

const getWindDirection = (degrees) => {
  const directions = ['С', 'СВ', 'В', 'ЮВ', 'Ю', 'ЮЗ', 'З', 'СЗ'];
  const index = Math.round(degrees / 45) % 8;
  return directions[index];
};
</script>

<template>
  <div class="p-6 h-full flex flex-col">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center">
        🌤️ Погода
      </h2>
      <button
        @click="fetchWeather"
        :disabled="loading"
        class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
      >
        <svg :class="['w-5 h-5', loading ? 'animate-spin' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
      </button>
    </div>

    <div v-if="loading" class="flex-1 flex items-center justify-center">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
    </div>

    <div v-else-if="error" class="flex-1 flex items-center justify-center text-center">
      <div class="text-red-500 dark:text-red-400">
        <p>⚠️</p>
        <p class="text-sm mt-2">{{ error }}</p>
      </div>
    </div>

    <div v-else-if="weather" class="flex-1 flex flex-col">
      <!-- Current Weather -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <div class="flex items-center mb-2">
            <span class="text-4xl mr-3">{{ getWeatherIcon(weather.icon) }}</span>
            <div>
              <p class="text-3xl font-bold text-gray-900 dark:text-white">
                {{ weather.temperature }}°
              </p>
              <p class="text-sm text-gray-600 dark:text-gray-300">
                Ощущается {{ weather.feels_like }}°
              </p>
            </div>
          </div>
          <p class="text-lg text-gray-800 dark:text-gray-200 capitalize">
            {{ weather.description }}
          </p>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            📍 {{ weather.city }}
          </p>
        </div>
      </div>

      <!-- Weather Details -->
      <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
          <p class="text-gray-500 dark:text-gray-400 mb-1">Влажность</p>
          <p class="font-semibold text-gray-900 dark:text-white">{{ weather.humidity }}%</p>
        </div>
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
          <p class="text-gray-500 dark:text-gray-400 mb-1">Давление</p>
          <p class="font-semibold text-gray-900 dark:text-white">{{ weather.pressure }} мбар</p>
        </div>
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
          <p class="text-gray-500 dark:text-gray-400 mb-1">Ветер</p>
          <p class="font-semibold text-gray-900 dark:text-white">{{ weather.wind_speed }} м/с</p>
        </div>
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
          <p class="text-gray-500 dark:text-gray-400 mb-1">Видимость</p>
          <p class="font-semibold text-gray-900 dark:text-white">{{ weather.visibility }} км</p>
        </div>
      </div>

      <!-- Forecast -->
      <div v-if="forecast.length > 0" class="mt-auto">
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Прогноз на сегодня</p>
        <div class="flex space-x-2 overflow-x-auto pb-2">
          <div
            v-for="item in forecast.slice(0, 4)"
            :key="item.time"
            class="flex-shrink-0 bg-gray-50 dark:bg-gray-700 rounded-lg p-2 text-center min-w-16"
          >
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">{{ item.time }}</p>
            <p class="text-lg">{{ getWeatherIcon(item.icon) }}</p>
            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ item.temperature }}°</p>
          </div>
        </div>
      </div>

      <!-- Mock Data Notice -->
      <div v-if="weather.is_mock" class="mt-2">
        <p class="text-xs text-amber-600 dark:text-amber-400 text-center">
          ⚠️ Тестовые данные (настройте API ключ)
        </p>
      </div>
    </div>
  </div>
</template>