<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
  isDarkMode: {
    type: Boolean,
    default: false
  }
});

// Mock email data since we don't have actual email integration
const emailStats = ref({
  unread: 5,
  total: 127,
  today: 12,
  important: 3
});

const recentEmails = ref([
  {
    id: 1,
    from: 'team@company.com',
    subject: 'Еженедельный отчет готов',
    preview: 'Команда подготовила еженедельный отчет по проекту...',
    time: '10:30',
    unread: true,
    important: false
  },
  {
    id: 2,
    from: 'notifications@service.com',
    subject: 'Обновление безопасности',
    preview: 'Важное обновление системы безопасности было установлено...',
    time: '09:45',
    unread: true,
    important: true
  },
  {
    id: 3,
    from: 'admin@project.com',
    subject: 'Новые задачи назначены',
    preview: 'Вам назначены новые задачи в проекте Dashboard...',
    time: '09:15',
    unread: false,
    important: false
  }
]);

const markAsRead = (emailId) => {
  const email = recentEmails.value.find(e => e.id === emailId);
  if (email && email.unread) {
    email.unread = false;
    emailStats.value.unread -= 1;
  }
};

const toggleImportant = (emailId) => {
  const email = recentEmails.value.find(e => e.id === emailId);
  if (email) {
    email.important = !email.important;
    emailStats.value.important += email.important ? 1 : -1;
  }
};

const openEmail = (email) => {
  // In a real implementation, this would open the email client or navigate to email detail
  console.log('Opening email:', email.subject);
  markAsRead(email.id);
};

const refreshEmails = () => {
  // Mock refresh - in real implementation would fetch from email API
  console.log('Refreshing emails...');
  
  // Simulate new email
  const newEmail = {
    id: Date.now(),
    from: 'new@sender.com',
    subject: 'Новое сообщение',
    preview: 'У вас есть новое сообщение в системе...',
    time: new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' }),
    unread: true,
    important: false
  };
  
  recentEmails.value.unshift(newEmail);
  emailStats.value.unread += 1;
  emailStats.value.total += 1;
  
  if (recentEmails.value.length > 5) {
    recentEmails.value.pop();
  }
};

onMounted(() => {
  // Auto-refresh every 5 minutes
  setInterval(() => {
    if (Math.random() < 0.3) { // 30% chance of new email
      refreshEmails();
    }
  }, 5 * 60 * 1000);
});
</script>

<template>
  <div class="p-6 h-full flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center">
        📧 Почта
        <span v-if="emailStats.unread > 0" class="ml-2 bg-red-500 text-white text-xs px-2 py-1 rounded-full">
          {{ emailStats.unread }}
        </span>
      </h2>
      <button
        @click="refreshEmails"
        class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
        title="Обновить"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
      </button>
    </div>

    <!-- Email Stats -->
    <div class="grid grid-cols-3 gap-2 mb-4">
      <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-center">
        <div class="text-lg font-bold text-blue-600 dark:text-blue-400">
          {{ emailStats.unread }}
        </div>
        <div class="text-xs text-gray-600 dark:text-gray-400">Непрочитано</div>
      </div>
      <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-center">
        <div class="text-lg font-bold text-green-600 dark:text-green-400">
          {{ emailStats.today }}
        </div>
        <div class="text-xs text-gray-600 dark:text-gray-400">Сегодня</div>
      </div>
      <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-center">
        <div class="text-lg font-bold text-orange-600 dark:text-orange-400">
          {{ emailStats.important }}
        </div>
        <div class="text-xs text-gray-600 dark:text-gray-400">Важных</div>
      </div>
    </div>

    <!-- Recent Emails -->
    <div class="flex-1 overflow-y-auto">
      <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">
        Последние сообщения
      </h3>
      
      <div class="space-y-2">
        <div
          v-for="email in recentEmails"
          :key="email.id"
          :class="[
            'p-3 rounded-lg cursor-pointer transition-all hover:bg-gray-100 dark:hover:bg-gray-700',
            email.unread 
              ? 'bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500'
              : 'bg-gray-50 dark:bg-gray-700/50'
          ]"
          @click="openEmail(email)"
        >
          <div class="flex items-start gap-3">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <p :class="[
                  'text-sm truncate',
                  email.unread 
                    ? 'font-semibold text-gray-900 dark:text-white' 
                    : 'font-medium text-gray-700 dark:text-gray-300'
                ]">
                  {{ email.from }}
                </p>
                <span class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">
                  {{ email.time }}
                </span>
                <button
                  @click.stop="toggleImportant(email.id)"
                  :class="[
                    'text-sm transition-colors flex-shrink-0',
                    email.important 
                      ? 'text-orange-500 hover:text-orange-600' 
                      : 'text-gray-300 hover:text-orange-500 dark:text-gray-600 dark:hover:text-orange-500'
                  ]"
                  title="Пометить как важное"
                >
                  ⭐
                </button>
              </div>
              <h4 :class="[
                'text-sm mb-1 truncate',
                email.unread 
                  ? 'font-semibold text-gray-900 dark:text-white' 
                  : 'font-medium text-gray-800 dark:text-gray-200'
              ]">
                {{ email.subject }}
              </h4>
              <p class="text-xs text-gray-600 dark:text-gray-400 line-clamp-2">
                {{ email.preview }}
              </p>
            </div>
            
            <!-- Unread indicator -->
            <div v-if="email.unread" class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0 mt-2" />
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="recentEmails.length === 0" class="flex flex-col items-center justify-center text-center py-8">
        <div class="text-4xl mb-3">📭</div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Нет сообщений</h3>
        <p class="text-gray-600 dark:text-gray-400">Все письма прочитаны</p>
      </div>

      <!-- Quick Actions -->
      <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
        <div class="flex gap-2">
          <button class="flex-1 px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors">
            Написать
          </button>
          <button class="px-3 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 transition-colors">
            Все
          </button>
        </div>
      </div>
    </div>

    <!-- Integration Notice -->
    <div class="mt-2">
      <p class="text-xs text-amber-600 dark:text-amber-400 text-center">
        💡 Демо-данные (настройте интеграцию с почтой)
      </p>
    </div>
  </div>
</template>