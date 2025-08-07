import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// CSRF токен для Inertia.js
const token = document.querySelector('meta[name="csrf-token"]');

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
    
    // Глобальная настройка CSRF для всех Inertia запросов
    setup({ app, el, props, plugin }) {
        const vueApp = createApp({ render: () => h(app, props) })
            .use(plugin);

        // Настройка глобальных заголовков для Inertia
        if (token) {
            vueApp.config.globalProperties.$inertia.defaults = {
                ...vueApp.config.globalProperties.$inertia.defaults,
                headers: {
                    'X-CSRF-TOKEN': token.content,
                }
            };
        }

        return vueApp.mount(el);
    },
});
