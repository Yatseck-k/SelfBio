<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
  isDarkMode: {
    type: Boolean,
    default: false
  }
});

const tasks = ref([]);
const loading = ref(true);
const error = ref(null);
const showAddForm = ref(false);
const newTask = ref({
  title: '',
  description: '',
  priority: 'medium',
  due_date: ''
});

const fetchTasks = async () => {
  try {
    loading.value = true;
    error.value = null;
    
    const response = await axios.get('/api/tasks');
    tasks.value = response.data;
  } catch (err) {
    error.value = 'Не удалось загрузить задачи';
    console.error('Tasks fetch error:', err);
  } finally {
    loading.value = false;
  }
};

const addTask = async () => {
  if (!newTask.value.title.trim()) return;
  
  try {
    const response = await axios.post('/api/tasks', {
      title: newTask.value.title,
      description: newTask.value.description,
      priority: newTask.value.priority,
      due_date: newTask.value.due_date || null
    });
    
    tasks.value.unshift(response.data);
    newTask.value = {
      title: '',
      description: '',
      priority: 'medium',
      due_date: ''
    };
    showAddForm.value = false;
  } catch (err) {
    console.error('Add task error:', err);
  }
};

const toggleTask = async (task) => {
  try {
    const response = await axios.patch(`/api/tasks/${task.id}/toggle`);
    const index = tasks.value.findIndex(t => t.id === task.id);
    if (index !== -1) {
      tasks.value[index] = response.data;
    }
  } catch (err) {
    console.error('Toggle task error:', err);
  }
};

const deleteTask = async (taskId) => {
  if (!confirm('Удалить эту задачу?')) return;
  
  try {
    await axios.delete(`/api/tasks/${taskId}`);
    tasks.value = tasks.value.filter(t => t.id !== taskId);
  } catch (err) {
    console.error('Delete task error:', err);
  }
};

const pendingTasks = computed(() => tasks.value.filter(t => !t.completed));
const completedTasks = computed(() => tasks.value.filter(t => t.completed));

const getPriorityColor = (priority) => {
  const colors = {
    high: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    low: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
  };
  return colors[priority] || colors.medium;
};

const getPriorityIcon = (priority) => {
  const icons = {
    high: '🔴',
    medium: '🟡',
    low: '🟢'
  };
  return icons[priority] || icons.medium;
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('ru-RU', {
    month: 'short',
    day: 'numeric'
  });
};

const isOverdue = (dateString) => {
  if (!dateString) return false;
  return new Date(dateString) < new Date() && !tasks.value.find(t => t.due_date === dateString)?.completed;
};

onMounted(fetchTasks);
</script>

<template>
  <div class="p-6 h-full flex flex-col">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center">
        ✅ Задачи
        <span class="ml-2 text-sm bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 px-2 py-1 rounded-full">
          {{ pendingTasks.length }}
        </span>
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

    <!-- Add Task Form -->
    <div v-if="showAddForm" class="mb-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
      <input
        v-model="newTask.title"
        placeholder="Название задачи..."
        class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-lg mb-3 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
        @keydown.enter="addTask"
      />
      <div class="flex gap-2">
        <select
          v-model="newTask.priority"
          class="flex-1 p-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
        >
          <option value="low">Низкий</option>
          <option value="medium">Средний</option>
          <option value="high">Высокий</option>
        </select>
        <input
          v-model="newTask.due_date"
          type="date"
          class="flex-1 p-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
        />
      </div>
      <div class="flex gap-2 mt-3">
        <button
          @click="addTask"
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

    <div v-if="loading" class="flex-1 flex items-center justify-center">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
    </div>

    <div v-else-if="error" class="flex-1 flex items-center justify-center text-center">
      <div class="text-red-500 dark:text-red-400">
        <p>⚠️</p>
        <p class="text-sm mt-2">{{ error }}</p>
      </div>
    </div>

    <div v-else class="flex-1 overflow-y-auto">
      <!-- Pending Tasks -->
      <div v-if="pendingTasks.length > 0" class="mb-6">
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">Активные задачи</h3>
        <div class="space-y-2">
          <div
            v-for="task in pendingTasks"
            :key="task.id"
            class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
          >
            <input
              type="checkbox"
              :checked="task.completed"
              @change="toggleTask(task)"
              class="mt-1 w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
            />
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-sm">{{ getPriorityIcon(task.priority) }}</span>
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                  {{ task.title }}
                </p>
                <span
                  v-if="task.due_date"
                  :class="['text-xs px-2 py-1 rounded-full', isOverdue(task.due_date) ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-300']"
                >
                  {{ formatDate(task.due_date) }}
                </span>
              </div>
              <p v-if="task.description" class="text-xs text-gray-600 dark:text-gray-300">
                {{ task.description }}
              </p>
            </div>
            <button
              @click="deleteTask(task.id)"
              class="p-1 text-gray-400 hover:text-red-500 transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Completed Tasks -->
      <div v-if="completedTasks.length > 0">
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">
          Выполненные задачи ({{ completedTasks.length }})
        </h3>
        <div class="space-y-2">
          <div
            v-for="task in completedTasks.slice(0, 3)"
            :key="task.id"
            class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg opacity-60"
          >
            <input
              type="checkbox"
              :checked="task.completed"
              @change="toggleTask(task)"
              class="mt-1 w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
            />
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 dark:text-white line-through">
                {{ task.title }}
              </p>
            </div>
            <button
              @click="deleteTask(task.id)"
              class="p-1 text-gray-400 hover:text-red-500 transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="tasks.length === 0" class="flex-1 flex flex-col items-center justify-center text-center py-8">
        <div class="text-6xl mb-4">📝</div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Пока нет задач</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">Добавьте первую задачу, чтобы начать</p>
        <button
          @click="showAddForm = true"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        >
          Создать задачу
        </button>
      </div>
    </div>
  </div>
</template>