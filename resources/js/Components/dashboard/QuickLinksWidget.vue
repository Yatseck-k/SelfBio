<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  isDarkMode: {
    type: Boolean,
    default: false
  }
});

const links = ref([]);
const showAddForm = ref(false);
const loading = ref(true);
const newLink = ref({
  title: '',
  url: '',
  icon: '🔗',
  category: '',
  color: '#3B82F6'
});

const predefinedIcons = [
  '🔗', '🌐', '📧', '💼', '📱', '⚙️', 
  '📊', '🎵', '🎬', '📚', '🛍️', '💰',
  '🏠', '🚗', '✈️', '🍕', '☕', '🎮'
];

const categories = ref([]);

const fetchLinks = async () => {
  try {
    loading.value = true;
    const [linksResponse, categoriesResponse] = await Promise.all([
      axios.get('/api/quick-links'),
      axios.get('/api/quick-links-categories')
    ]);
    
    links.value = linksResponse.data;
    categories.value = categoriesResponse.data;
  } catch (err) {
    console.error('Links fetch error:', err);
  } finally {
    loading.value = false;
  }
};

const addLink = async () => {
  if (!newLink.value.title.trim() || !newLink.value.url.trim()) return;
  
  try {
    // Ensure URL has protocol
    if (!newLink.value.url.startsWith('http')) {
      newLink.value.url = 'https://' + newLink.value.url;
    }
    
    const response = await axios.post('/api/quick-links', newLink.value);
    links.value.push(response.data);
    
    newLink.value = {
      title: '',
      url: '',
      icon: '🔗',
      category: '',
      color: '#3B82F6'
    };
    showAddForm.value = false;
  } catch (err) {
    console.error('Add link error:', err);
  }
};

const deleteLink = async (linkId) => {
  if (!confirm('Удалить эту ссылку?')) return;
  
  try {
    await axios.delete(`/api/quick-links/${linkId}`);
    links.value = links.value.filter(l => l.id !== linkId);
  } catch (err) {
    console.error('Delete link error:', err);
  }
};

const openLink = (link) => {
  const url = link.url.startsWith('http') ? link.url : `https://${link.url}`;
  window.open(url, '_blank', 'noopener,noreferrer');
};

const getDomainFromUrl = (url) => {
  try {
    const domain = new URL(url.startsWith('http') ? url : `https://${url}`).hostname;
    return domain.replace('www.', '');
  } catch {
    return url;
  }
};

const groupedLinks = ref({});

const groupLinksByCategory = () => {
  const grouped = {};
  
  links.value.forEach(link => {
    const category = link.category || 'Общие';
    if (!grouped[category]) {
      grouped[category] = [];
    }
    grouped[category].push(link);
  });
  
  groupedLinks.value = grouped;
};

onMounted(() => {
  fetchLinks().then(() => {
    groupLinksByCategory();
  });
});
</script>

<template>
  <div class="p-6 h-full flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center">
        🔗 Быстрые ссылки
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

    <!-- Add Link Form -->
    <div v-if="showAddForm" class="mb-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
      <input
        v-model="newLink.title"
        placeholder="Название ссылки..."
        class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-lg mb-3 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
      />
      <input
        v-model="newLink.url"
        placeholder="https://example.com"
        class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-lg mb-3 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
      />
      <div class="grid grid-cols-2 gap-2 mb-3">
        <input
          v-model="newLink.category"
          placeholder="Категория (опционально)"
          class="p-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
        />
        <input
          v-model="newLink.color"
          type="color"
          class="p-1 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 h-10"
        />
      </div>
      
      <!-- Icon Selector -->
      <div class="mb-3">
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Выберите иконку:</p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="icon in predefinedIcons"
            :key="icon"
            @click="newLink.icon = icon"
            :class="[
              'p-2 rounded-lg text-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors',
              newLink.icon === icon ? 'bg-blue-100 dark:bg-blue-900' : 'bg-gray-100 dark:bg-gray-700'
            ]"
          >
            {{ icon }}
          </button>
        </div>
      </div>
      
      <div class="flex gap-2">
        <button
          @click="addLink"
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

    <div v-else-if="links.length === 0" class="flex-1 flex flex-col items-center justify-center text-center">
      <div class="text-6xl mb-4">🔗</div>
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Нет ссылок</h3>
      <p class="text-gray-600 dark:text-gray-400 mb-4">Добавьте первую быструю ссылку</p>
      <button
        @click="showAddForm = true"
        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
      >
        Добавить ссылку
      </button>
    </div>

    <div v-else class="flex-1 overflow-y-auto">
      <!-- Links Grid -->
      <div class="grid grid-cols-2 gap-3">
        <div
          v-for="link in links"
          :key="link.id"
          class="group relative bg-gray-50 dark:bg-gray-700 rounded-lg p-4 hover:bg-gray-100 dark:hover:bg-gray-600 transition-all cursor-pointer"
          @click="openLink(link)"
        >
          <!-- Link Icon and Info -->
          <div class="flex items-start gap-3">
            <div
              class="w-10 h-10 rounded-full flex items-center justify-center text-white text-lg flex-shrink-0"
              :style="{ backgroundColor: link.color }"
            >
              {{ link.icon }}
            </div>
            <div class="flex-1 min-w-0">
              <h4 class="font-medium text-gray-900 dark:text-white text-sm truncate">
                {{ link.title }}
              </h4>
              <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                {{ getDomainFromUrl(link.url) }}
              </p>
              <p v-if="link.category" class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                {{ link.category }}
              </p>
            </div>
          </div>
          
          <!-- Delete Button -->
          <button
            @click.stop="deleteLink(link.id)"
            class="absolute top-2 right-2 p-1 text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-all"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Popular Sites Suggestions (if no links exist) -->
      <div v-if="links.length === 0" class="mt-6">
        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">
          Популярные сайты:
        </h4>
        <div class="grid grid-cols-3 gap-2">
          <button
            v-for="site in [
              { name: 'Gmail', url: 'gmail.com', icon: '📧' },
              { name: 'YouTube', url: 'youtube.com', icon: '🎬' },
              { name: 'GitHub', url: 'github.com', icon: '💼' }
            ]"
            :key="site.name"
            @click="() => {
              newLink.title = site.name;
              newLink.url = site.url;
              newLink.icon = site.icon;
              showAddForm = true;
            }"
            class="p-3 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors text-center"
          >
            <div class="text-2xl mb-1">{{ site.icon }}</div>
            <div class="text-xs text-gray-600 dark:text-gray-400">{{ site.name }}</div>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>