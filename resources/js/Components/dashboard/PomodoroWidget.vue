<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
  isDarkMode: {
    type: Boolean,
    default: false
  }
});

const isActive = ref(false);
const currentSession = ref(null);
const timeLeft = ref(0);
const sessionType = ref('work'); // work, short_break, long_break
const duration = ref(25); // minutes
const stats = ref(null);
const loading = ref(false);

let interval = null;

const sessionTypes = {
  work: { label: 'Работа', duration: 25, emoji: '🍅' },
  short_break: { label: 'Короткий перерыв', duration: 5, emoji: '☕' },
  long_break: { label: 'Длинный перерыв', duration: 15, emoji: '🛋️' }
};

const formatTime = (seconds) => {
  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
};

const progress = computed(() => {
  if (!currentSession.value) return 0;
  const totalSeconds = duration.value * 60;
  const elapsed = totalSeconds - timeLeft.value;
  return (elapsed / totalSeconds) * 100;
});

const fetchStats = async () => {
  try {
    const response = await axios.get('/api/pomodoro/stats');
    stats.value = response.data;
  } catch (err) {
    console.error('Stats fetch error:', err);
  }
};

const checkActiveSession = async () => {
  try {
    const response = await axios.get('/api/pomodoro/active');
    const activeSession = response.data.active_session;
    
    if (activeSession) {
      currentSession.value = activeSession;
      isActive.value = true;
      duration.value = activeSession.duration;
      sessionType.value = activeSession.type;
      
      const now = new Date();
      const expiresAt = new Date(activeSession.expires_at);
      const secondsLeft = Math.max(0, Math.floor((expiresAt - now) / 1000));
      
      if (secondsLeft > 0) {
        timeLeft.value = secondsLeft;
        startCountdown();
      } else {
        // Session expired
        completeSession();
      }
    }
  } catch (err) {
    console.error('Check active session error:', err);
  }
};

const startSession = async () => {
  if (isActive.value) return;
  
  try {
    loading.value = true;
    const response = await axios.post('/api/pomodoro', {
      duration: duration.value,
      type: sessionType.value
    });
    
    currentSession.value = response.data;
    isActive.value = true;
    timeLeft.value = duration.value * 60;
    startCountdown();
    
    // Request notification permission
    if ('Notification' in window && Notification.permission === 'default') {
      Notification.requestPermission();
    }
  } catch (err) {
    console.error('Start session error:', err);
  } finally {
    loading.value = false;
  }
};

const pauseSession = () => {
  isActive.value = false;
  if (interval) {
    clearInterval(interval);
    interval = null;
  }
};

const resumeSession = () => {
  if (timeLeft.value > 0) {
    isActive.value = true;
    startCountdown();
  }
};

const completeSession = async () => {
  if (!currentSession.value) return;
  
  try {
    await axios.patch(`/api/pomodoro/${currentSession.value.id}/complete`);
    
    // Show notification
    if ('Notification' in window && Notification.permission === 'granted') {
      const sessionInfo = sessionTypes[sessionType.value];
      new Notification('Pomodoro завершен!', {
        body: `${sessionInfo.emoji} ${sessionInfo.label} завершен`,
        icon: '/favicon.ico'
      });
    }
    
    resetTimer();
    await fetchStats();
    
    // Auto-suggest next session
    suggestNextSession();
  } catch (err) {
    console.error('Complete session error:', err);
  }
};

const cancelSession = async () => {
  if (!currentSession.value) return;
  
  if (!confirm('Отменить текущую сессию?')) return;
  
  try {
    await axios.delete(`/api/pomodoro/${currentSession.value.id}/cancel`);
    resetTimer();
  } catch (err) {
    console.error('Cancel session error:', err);
  }
};

const resetTimer = () => {
  isActive.value = false;
  currentSession.value = null;
  timeLeft.value = 0;
  if (interval) {
    clearInterval(interval);
    interval = null;
  }
};

const startCountdown = () => {
  if (interval) clearInterval(interval);
  
  interval = setInterval(() => {
    if (timeLeft.value > 0) {
      timeLeft.value--;
    } else {
      completeSession();
    }
  }, 1000);
};

const suggestNextSession = () => {
  const workSessions = stats.value?.today?.work_sessions || 0;
  
  if (sessionType.value === 'work') {
    // After work session, suggest break
    if (workSessions % 4 === 0) {
      sessionType.value = 'long_break';
      duration.value = sessionTypes.long_break.duration;
    } else {
      sessionType.value = 'short_break';
      duration.value = sessionTypes.short_break.duration;
    }
  } else {
    // After break, suggest work
    sessionType.value = 'work';
    duration.value = sessionTypes.work.duration;
  }
};

const changeSessionType = (type) => {
  if (isActive.value) return;
  
  sessionType.value = type;
  duration.value = sessionTypes[type].duration;
};

onMounted(() => {
  fetchStats();
  checkActiveSession();
});

onUnmounted(() => {
  if (interval) {
    clearInterval(interval);
  }
});
</script>

<template>
  <div class="p-6 h-full flex flex-col">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center">
        🍅 Pomodoro
      </h2>
      <div class="text-sm text-gray-500 dark:text-gray-400">
        {{ stats?.today?.completed_sessions || 0 }} сегодня
      </div>
    </div>

    <!-- Session Type Selector -->
    <div v-if="!isActive" class="flex gap-2 mb-6">
      <button
        v-for="(type, key) in sessionTypes"
        :key="key"
        @click="changeSessionType(key)"
        :class="[
          'flex-1 p-3 rounded-lg text-sm font-medium transition-colors',
          sessionType === key
            ? 'bg-blue-600 text-white'
            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
        ]"
      >
        <span class="block">{{ type.emoji }}</span>
        <span class="block text-xs mt-1">{{ type.label }}</span>
      </button>
    </div>

    <!-- Timer Display -->
    <div class="flex-1 flex flex-col items-center justify-center">
      <div class="relative w-48 h-48 mb-8">
        <!-- Progress Ring -->
        <svg class="w-48 h-48 transform -rotate-90" viewBox="0 0 200 200">
          <circle
            cx="100"
            cy="100"
            r="90"
            stroke="currentColor"
            :class="isDarkMode ? 'text-gray-700' : 'text-gray-200'"
            stroke-width="8"
            fill="none"
          />
          <circle
            cx="100"
            cy="100"
            r="90"
            stroke="currentColor"
            :class="isActive ? 'text-blue-600' : 'text-gray-400'"
            stroke-width="8"
            fill="none"
            stroke-linecap="round"
            :stroke-dasharray="`${2 * Math.PI * 90}`"
            :stroke-dashoffset="`${2 * Math.PI * 90 * (1 - progress / 100)}`"
            class="transition-all duration-1000"
          />
        </svg>
        
        <!-- Time Display -->
        <div class="absolute inset-0 flex flex-col items-center justify-center">
          <div class="text-4xl mb-2">
            {{ sessionTypes[sessionType].emoji }}
          </div>
          <div class="text-3xl font-mono font-bold text-gray-900 dark:text-white">
            {{ formatTime(timeLeft || duration * 60) }}
          </div>
          <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            {{ sessionTypes[sessionType].label }}
          </div>
        </div>
      </div>

      <!-- Control Buttons -->
      <div class="flex gap-3">
        <button
          v-if="!currentSession"
          @click="startSession"
          :disabled="loading"
          class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
        >
          {{ loading ? 'Запуск...' : 'Начать' }}
        </button>
        
        <template v-else>
          <button
            v-if="isActive"
            @click="pauseSession"
            class="px-6 py-3 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors"
          >
            Пауза
          </button>
          
          <button
            v-else
            @click="resumeSession"
            class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
          >
            Продолжить
          </button>
          
          <button
            @click="cancelSession"
            class="px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
          >
            Стоп
          </button>
        </template>
      </div>
    </div>

    <!-- Stats -->
    <div v-if="stats" class="mt-6 grid grid-cols-2 gap-4 text-center">
      <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
          {{ stats.today.work_sessions }}
        </div>
        <div class="text-xs text-gray-600 dark:text-gray-400">
          Рабочих сессий сегодня
        </div>
      </div>
      <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
        <div class="text-2xl font-bold text-green-600 dark:text-green-400">
          {{ stats.today.total_minutes }}
        </div>
        <div class="text-xs text-gray-600 dark:text-gray-400">
          Минут сегодня
        </div>
      </div>
    </div>
  </div>
</template>