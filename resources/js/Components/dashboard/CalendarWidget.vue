<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
  isDarkMode: {
    type: Boolean,
    default: false
  }
});

const currentDate = ref(new Date());
const events = ref([]);
const showAddForm = ref(false);
const loading = ref(true);
const newEvent = ref({
  title: '',
  description: '',
  start_date: '',
  end_date: '',
  color: '#3B82F6',
  all_day: false
});

const monthNames = [
  'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
  'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
];

const weekDays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

const calendarDays = computed(() => {
  const year = currentDate.value.getFullYear();
  const month = currentDate.value.getMonth();
  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);
  
  // Adjust for Monday start (Russian convention)
  const startDate = new Date(firstDay);
  const dayOfWeek = (firstDay.getDay() + 6) % 7; // Convert to Monday = 0
  startDate.setDate(firstDay.getDate() - dayOfWeek);
  
  const days = [];
  const current = new Date(startDate);
  
  // Generate 42 days (6 weeks)
  for (let i = 0; i < 42; i++) {
    const dayEvents = events.value.filter(event => {
      const eventDate = new Date(event.start_date);
      return eventDate.toDateString() === current.toDateString();
    });
    
    days.push({
      date: new Date(current),
      day: current.getDate(),
      isCurrentMonth: current.getMonth() === month,
      isToday: current.toDateString() === new Date().toDateString(),
      events: dayEvents
    });
    
    current.setDate(current.getDate() + 1);
  }
  
  return days;
});

const upcomingEvents = computed(() => {
  const today = new Date();
  return events.value
    .filter(event => new Date(event.start_date) >= today)
    .sort((a, b) => new Date(a.start_date) - new Date(b.start_date))
    .slice(0, 3);
});

const fetchEvents = async () => {
  try {
    loading.value = true;
    const response = await axios.get('/api/calendar-events', {
      params: {
        start: currentDate.value.toISOString().split('T')[0],
        end: new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 0).toISOString().split('T')[0]
      }
    });
    events.value = response.data;
  } catch (err) {
    console.error('Events fetch error:', err);
  } finally {
    loading.value = false;
  }
};

const addEvent = async () => {
  if (!newEvent.value.title.trim() || !newEvent.value.start_date) return;
  
  try {
    const response = await axios.post('/api/calendar-events', {
      ...newEvent.value,
      end_date: newEvent.value.end_date || newEvent.value.start_date
    });
    
    events.value.push(response.data);
    newEvent.value = {
      title: '',
      description: '',
      start_date: '',
      end_date: '',
      color: '#3B82F6',
      all_day: false
    };
    showAddForm.value = false;
  } catch (err) {
    console.error('Add event error:', err);
  }
};

const deleteEvent = async (eventId) => {
  if (!confirm('Удалить это событие?')) return;
  
  try {
    await axios.delete(`/api/calendar-events/${eventId}`);
    events.value = events.value.filter(e => e.id !== eventId);
  } catch (err) {
    console.error('Delete event error:', err);
  }
};

const previousMonth = () => {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1);
  fetchEvents();
};

const nextMonth = () => {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1);
  fetchEvents();
};

const formatTime = (dateString) => {
  return new Date(dateString).toLocaleTimeString('ru-RU', {
    hour: '2-digit',
    minute: '2-digit'
  });
};

const formatEventDate = (startDate, endDate) => {
  const start = new Date(startDate);
  const end = new Date(endDate);
  
  if (start.toDateString() === end.toDateString()) {
    return start.toLocaleDateString('ru-RU', {
      month: 'short',
      day: 'numeric'
    });
  }
  
  return `${start.toLocaleDateString('ru-RU', {
    month: 'short',
    day: 'numeric'
  })} - ${end.toLocaleDateString('ru-RU', {
    month: 'short',
    day: 'numeric'
  })}`;
};

onMounted(fetchEvents);
</script>

<template>
  <div class="p-6 h-full flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center">
        📅 Календарь
      </h2>
      <button
        @click="showAddForm = !showAddForm"
        class="p-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200 transition-colors"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
      </button>
    </div>

    <!-- Add Event Form -->
    <div v-if="showAddForm" class="mb-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
      <input
        v-model="newEvent.title"
        placeholder="Название события..."
        class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-lg mb-3 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
      />
      <div class="grid grid-cols-2 gap-2 mb-3">
        <input
          v-model="newEvent.start_date"
          type="datetime-local"
          class="p-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
        />
        <input
          v-model="newEvent.end_date"
          type="datetime-local"
          class="p-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
        />
      </div>
      <div class="flex gap-2">
        <button
          @click="addEvent"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        >
          Добавить
        </button>
        <button
          @click="showAddForm = false"
          class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors"
        >
          Отмена
        </button>
      </div>
    </div>

    <!-- Calendar Navigation -->
    <div class="flex items-center justify-between mb-4">
      <button
        @click="previousMonth"
        class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <h3 class="text-lg font-medium text-gray-900 dark:text-white">
        {{ monthNames[currentDate.getMonth()] }} {{ currentDate.getFullYear() }}
      </h3>
      <button
        @click="nextMonth"
        class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </div>

    <!-- Calendar Grid -->
    <div class="flex-1 flex flex-col">
      <!-- Week Days -->
      <div class="grid grid-cols-7 gap-1 mb-2">
        <div
          v-for="day in weekDays"
          :key="day"
          class="text-center text-xs font-medium text-gray-500 dark:text-gray-400 p-2"
        >
          {{ day }}
        </div>
      </div>

      <!-- Calendar Days -->
      <div class="grid grid-cols-7 gap-1 flex-1">
        <div
          v-for="(day, index) in calendarDays"
          :key="index"
          :class="[
            'p-1 text-center text-sm relative cursor-pointer rounded transition-colors',
            day.isCurrentMonth
              ? 'text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700'
              : 'text-gray-400 dark:text-gray-600',
            day.isToday
              ? 'bg-blue-100 dark:bg-blue-900 text-blue-900 dark:text-blue-100 font-bold'
              : ''
          ]"
        >
          <div class="relative">
            {{ day.day }}
            <div v-if="day.events.length > 0" class="flex justify-center mt-1">
              <div
                v-for="(event, eventIndex) in day.events.slice(0, 2)"
                :key="eventIndex"
                class="w-1 h-1 rounded-full mr-1"
                :style="{ backgroundColor: event.color }"
              />
              <div v-if="day.events.length > 2" class="w-1 h-1 rounded-full bg-gray-400" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Upcoming Events -->
    <div v-if="upcomingEvents.length > 0" class="mt-4">
      <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
        Ближайшие события
      </h4>
      <div class="space-y-2 max-h-32 overflow-y-auto">
        <div
          v-for="event in upcomingEvents"
          :key="event.id"
          class="flex items-center gap-3 p-2 bg-gray-50 dark:bg-gray-700 rounded-lg"
        >
          <div
            class="w-3 h-3 rounded-full flex-shrink-0"
            :style="{ backgroundColor: event.color }"
          />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
              {{ event.title }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              {{ formatEventDate(event.start_date, event.end_date) }}
              <span v-if="!event.all_day">{{ formatTime(event.start_date) }}</span>
            </p>
          </div>
          <button
            @click="deleteEvent(event.id)"
            class="p-1 text-gray-400 hover:text-red-500 transition-colors flex-shrink-0"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>