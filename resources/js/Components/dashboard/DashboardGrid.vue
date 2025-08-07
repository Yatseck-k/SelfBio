<script setup>
import { ref, nextTick } from 'vue';

const props = defineProps({
  widgets: {
    type: Array,
    required: true
  },
  isDarkMode: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['layout-updated']);

const gridRef = ref(null);
const draggedWidget = ref(null);
const isDragging = ref(false);

const startDrag = (widget, event) => {
  draggedWidget.value = widget;
  isDragging.value = true;
  
  const rect = event.target.getBoundingClientRect();
  const offsetX = event.clientX - rect.left;
  const offsetY = event.clientY - rect.top;
  
  const dragGhost = document.createElement('div');
  dragGhost.style.position = 'absolute';
  dragGhost.style.pointerEvents = 'none';
  dragGhost.style.zIndex = '1000';
  dragGhost.style.opacity = '0.8';
  dragGhost.classList.add('drag-ghost');
  
  const moveHandler = (e) => {
    if (dragGhost.parentNode) {
      dragGhost.style.left = (e.clientX - offsetX) + 'px';
      dragGhost.style.top = (e.clientY - offsetY) + 'px';
    }
  };
  
  const upHandler = () => {
    document.removeEventListener('mousemove', moveHandler);
    document.removeEventListener('mouseup', upHandler);
    if (dragGhost.parentNode) {
      dragGhost.parentNode.removeChild(dragGhost);
    }
    isDragging.value = false;
    draggedWidget.value = null;
  };
  
  document.addEventListener('mousemove', moveHandler);
  document.addEventListener('mouseup', upHandler);
  
  // Prevent default drag behavior
  event.preventDefault();
};

const getWidgetClasses = (widget) => {
  const baseClasses = [
    'bg-white dark:bg-gray-800',
    'rounded-xl shadow-lg',
    'border border-gray-200 dark:border-gray-700',
    'transition-all duration-300',
    'hover:shadow-xl hover:-translate-y-1',
    'cursor-move overflow-hidden'
  ];
  
  if (isDragging.value && draggedWidget.value?.id === widget.id) {
    baseClasses.push('opacity-50');
  }
  
  return baseClasses.join(' ');
};

const getGridStyle = (position) => {
  return {
    gridColumn: `span ${position.w}`,
    gridRow: `span ${position.h}`,
    minHeight: `${position.h * 80}px`
  };
};
</script>

<template>
  <div
    ref="gridRef"
    class="grid grid-cols-12 gap-6 auto-rows-fr"
    style="grid-auto-rows: minmax(80px, auto);"
  >
    <div
      v-for="widget in widgets"
      :key="widget.id"
      :class="getWidgetClasses(widget)"
      :style="getGridStyle(widget.position)"
      @mousedown="startDrag(widget, $event)"
    >
      <component :is="widget.component" :isDarkMode="isDarkMode" />
    </div>
  </div>
</template>

<style scoped>
.drag-ghost {
  background: rgba(59, 130, 246, 0.1);
  border: 2px dashed #3B82F6;
  border-radius: 12px;
}

@media (max-width: 768px) {
  .grid {
    grid-template-columns: 1fr;
  }
  
  .grid > div {
    grid-column: 1 !important;
  }
}
</style>