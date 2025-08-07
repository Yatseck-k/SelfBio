<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
  isDarkMode: {
    type: Boolean,
    default: false
  }
});

const currentTime = ref(new Date());
const greeting = ref('');

const updateTime = () => {
  currentTime.value = new Date();
  
  const hour = currentTime.value.getHours();
  if (hour < 12) {
    greeting.value = 'Доброе утро';
  } else if (hour < 18) {
    greeting.value = 'Добрый день';
  } else {
    greeting.value = 'Добрый вечер';
  }
};

onMounted(() => {
  updateTime();
  setInterval(updateTime, 1000);
});

const formatTime = (date) => {
  return date.toLocaleTimeString('ru-RU', {
    hour: '2-digit',
    minute: '2-digit'
  });
};

const formatDate = (date) => {
  return date.toLocaleDateString('ru-RU', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
};
</script>

<template>
  <div class="p-6 flex items-center justify-between h-full">
    <div>
      <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
        {{ greeting }}, User! 👋
      </h1>
      <p class="text-lg text-gray-600 dark:text-gray-300">
        {{ formatDate(currentTime) }}
      </p>
    </div>
    
    <div class="text-right">
      <div class="text-4xl font-mono font-bold text-blue-600 dark:text-blue-400">
        {{ formatTime(currentTime) }}
      </div>
      <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
        Текущее время
      </div>
    </div>
  </div>
</template>