<template>
    <div class="language-switcher">
        <div class="switch-container" @click="toggleLanguage">
            <div class="switch-track" :class="{ 'active': currentLocale === 'en' }">
                <div class="switch-thumb" :class="{ 'active': currentLocale === 'en' }"></div>
                <span class="lang-label left" :class="{ 'active': currentLocale === 'ru' }">RU</span>
                <span class="lang-label right" :class="{ 'active': currentLocale === 'en' }">EN</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const currentLocale = ref('ru');

const pageLocale = computed(() => page.props.locale || 'ru');

onMounted(() => {
    currentLocale.value = pageLocale.value;
});

const toggleLanguage = async () => {
    const newLocale = currentLocale.value === 'ru' ? 'en' : 'ru';

    try {
        await fetch('/api/language', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ locale: newLocale })
        });

        currentLocale.value = newLocale;

        window.location.reload();

    } catch (error) {
        console.error('Ошибка при смене языка:', error);
    }
};
</script>

<style scoped>
.language-switcher {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
}

.switch-container {
    cursor: pointer;
    user-select: none;
}

.switch-track {
    position: relative;
    width: 80px;
    height: 36px;
    background: rgba(55, 65, 81, 0.9);
    border-radius: 18px;
    border: 1px solid rgba(156, 163, 175, 0.3);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 2px;
    backdrop-filter: blur(10px);
}

.switch-track.active {
    background: rgba(79, 70, 229, 0.2);
    border-color: rgba(129, 140, 248, 0.5);
}

.switch-thumb {
    position: absolute;
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    left: 2px;
    top: 2px;
}

.switch-thumb.active {
    transform: translateX(44px);
    background: linear-gradient(135deg, #10b981, #059669);
}

.lang-label {
    position: absolute;
    font-size: 11px;
    font-weight: 600;
    color: rgba(156, 163, 175, 0.8);
    transition: all 0.3s ease;
    z-index: 10;
}

.lang-label.left {
    left: 8px;
}

.lang-label.right {
    right: 8px;
}

.lang-label.active {
    color: #ffffff;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

/* Анимация при наведении */
.switch-container:hover .switch-track {
    box-shadow: 0 0 0 2px rgba(129, 140, 248, 0.2);
}

.switch-container:hover .switch-thumb {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
}
</style>
