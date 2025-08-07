<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import DashboardGrid from '@/Components/dashboard/DashboardGrid.vue';
import HeaderWidget from '@/Components/dashboard/HeaderWidget.vue';
import WeatherWidget from '@/Components/dashboard/WeatherWidget.vue';
import CalendarWidget from '@/Components/dashboard/CalendarWidget.vue';
import TodoListWidget from '@/Components/dashboard/TodoListWidget.vue';
import PomodoroWidget from '@/Components/dashboard/PomodoroWidget.vue';
import QuickLinksWidget from '@/Components/dashboard/QuickLinksWidget.vue';
import EmailWidget from '@/Components/dashboard/EmailWidget.vue';

// Theme management
const isDarkMode = ref(false);
const toggleTheme = () => {
  isDarkMode.value = !isDarkMode.value;
  document.documentElement.classList.toggle('dark', isDarkMode.value);
  localStorage.setItem('theme', isDarkMode.value ? 'dark' : 'light');
};

// Widget configuration
const widgets = ref([
  { id: 'header', component: HeaderWidget, enabled: true, position: { x: 0, y: 0, w: 12, h: 2 } },
  { id: 'weather', component: WeatherWidget, enabled: true, position: { x: 0, y: 2, w: 6, h: 4 } },
  { id: 'quicklinks', component: QuickLinksWidget, enabled: true, position: { x: 6, y: 2, w: 6, h: 4 } },
  { id: 'calendar', component: CalendarWidget, enabled: true, position: { x: 0, y: 6, w: 6, h: 6 } },
  { id: 'email', component: EmailWidget, enabled: true, position: { x: 6, y: 6, w: 6, h: 3 } },
  { id: 'todo', component: TodoListWidget, enabled: true, position: { x: 0, y: 12, w: 6, h: 8 } },
  { id: 'pomodoro', component: PomodoroWidget, enabled: true, position: { x: 6, y: 9, w: 6, h: 8 } }
]);

const enabledWidgets = computed(() => widgets.value.filter(w => w.enabled));

// WebSocket connection for real-time updates
onMounted(() => {
  // Load theme from localStorage
  const savedTheme = localStorage.getItem('theme');
  if (savedTheme === 'dark') {
    isDarkMode.value = true;
    document.documentElement.classList.add('dark');
  }

  // Setup WebSocket listeners with proper authentication
  setupWebSocketListeners();
  
  // Request notification permission
  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
  }
});

const setupWebSocketListeners = () => {
  if (window.Echo && window.Laravel?.user?.id) {
    const userId = window.Laravel.user.id;
    
    window.Echo.private(`user.${userId}`)
      .listen('TaskCreated', (e) => {
        console.log('New task created:', e.task);
        showNotification(`Новая задача: ${e.task.title}`);
      })
      .listen('PomodoroCompleted', (e) => {
        console.log('Pomodoro completed:', e.session);
        const sessionType = e.session.type === 'work' ? 'Рабочая сессия' : 'Перерыв';
        showNotification(`${sessionType} завершена!`);
      })
      .listen('CalendarEventReminder', (e) => {
        showNotification(e.message);
      })
      .listen('DashboardStatsUpdated', (e) => {
        console.log('Stats updated:', e.stats);
      });
  }
};

const showNotification = (message) => {
  if ('Notification' in window && Notification.permission === 'granted') {
    new Notification('Dashboard Notification', {
      body: message,
      icon: '/favicon.ico'
    });
  } else if ('Notification' in window && Notification.permission !== 'denied') {
    Notification.requestPermission().then(permission => {
      if (permission === 'granted') {
        new Notification('Dashboard Notification', {
          body: message,
          icon: '/favicon.ico'
        });
      }
    });
  }
};

const settingsOpen = ref(false);
const toggleSettings = () => {
  settingsOpen.value = !settingsOpen.value;
};

const toggleWidget = (widgetId) => {
  const widget = widgets.value.find(w => w.id === widgetId);
  if (widget) {
    widget.enabled = !widget.enabled;
  }
};

const resetLayout = () => {
  // Reset to default positions
  widgets.value.forEach(widget => {
    switch (widget.id) {
      case 'header':
        widget.position = { x: 0, y: 0, w: 12, h: 2 };
        break;
      case 'weather':
        widget.position = { x: 0, y: 2, w: 6, h: 4 };
        break;
      case 'quicklinks':
        widget.position = { x: 6, y: 2, w: 6, h: 4 };
        break;
      case 'calendar':
        widget.position = { x: 0, y: 6, w: 6, h: 6 };
        break;
      case 'email':
        widget.position = { x: 6, y: 6, w: 6, h: 3 };
        break;
      case 'todo':
        widget.position = { x: 0, y: 12, w: 6, h: 8 };
        break;
      case 'pomodoro':
        widget.position = { x: 6, y: 9, w: 6, h: 8 };
        break;
    }
  });
};
</script>

<template>
  <Head title="Дашборд" />
  
  <div :class="['min-h-screen', isDarkMode ? 'dark bg-gray-900' : 'bg-gray-50']">
    <!-- Settings Panel -->
    <div v-if="settingsOpen" class="fixed inset-0 z-50 flex">
      <div class="fixed inset-0 bg-black/50" @click="settingsOpen = false"></div>
      <div class="relative ml-auto h-full w-80 bg-white dark:bg-gray-800 shadow-xl">
        <div class="p-6">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Настройки дашборда</h2>
            <button @click="settingsOpen = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>

          <!-- Theme Toggle -->
          <div class="mb-8">
            <h3 class="text-lg font-medium mb-4 text-gray-900 dark:text-white">Тема</h3>
            <button
              @click="toggleTheme"
              class="flex items-center justify-between w-full p-3 bg-gray-100 dark:bg-gray-700 rounded-lg transition-colors"
            >
              <span class="text-gray-900 dark:text-white">
                {{ isDarkMode ? 'Темная тема' : 'Светлая тема' }}
              </span>
              <div class="relative">
                <div :class="['w-12 h-6 bg-gray-300 rounded-full transition-colors', isDarkMode ? 'bg-blue-600' : '']">
                  <div :class="['absolute w-5 h-5 bg-white rounded-full shadow-md transform transition-transform top-0.5', isDarkMode ? 'translate-x-6' : 'translate-x-0.5']"></div>
                </div>
              </div>
            </button>
          </div>

          <!-- Widget Toggle -->
          <div class="mb-8">
            <h3 class="text-lg font-medium mb-4 text-gray-900 dark:text-white">Виджеты</h3>
            <div class="space-y-2">
              <label v-for="widget in widgets" :key="widget.id" class="flex items-center justify-between">
                <span class="text-sm text-gray-900 dark:text-white capitalize">{{ widget.id }}</span>
                <input
                  type="checkbox"
                  :checked="widget.enabled"
                  @change="toggleWidget(widget.id)"
                  class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                />
              </label>
            </div>
          </div>

          <!-- Reset Button -->
          <button
            @click="resetLayout"
            class="w-full py-2 px-4 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
          >
            Сбросить макет
          </button>
        </div>
      </div>
    </div>

    <!-- Settings Button -->
    <button
      @click="toggleSettings"
      class="fixed top-4 right-4 z-40 p-3 bg-white dark:bg-gray-800 rounded-full shadow-lg hover:shadow-xl transition-shadow"
    >
      <svg class="w-6 h-6 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
      </svg>
    </button>

    <!-- Main Dashboard -->
    <div class="p-6">
      <DashboardGrid 
        :widgets="enabledWidgets" 
        :isDarkMode="isDarkMode"
        @layout-updated="(layout) => console.log('Layout updated:', layout)"
      />
    </div>
  </div>
</template>

<style scoped>
/* Custom scrollbar for dark mode */
.dark ::-webkit-scrollbar {
  width: 8px;
}

.dark ::-webkit-scrollbar-track {
  background: #374151;
}

.dark ::-webkit-scrollbar-thumb {
  background: #6b7280;
  border-radius: 4px;
}

.dark ::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}
</style>