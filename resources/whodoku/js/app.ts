import '../css/app.css';
import '../css/dark.css';
import '../css/selfbio.css';
import { initTheme } from './composables/theme';

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';
import { loadAppearance } from './composables/appearance';

const pages = import.meta.glob<{ default: DefineComponent }>('./Pages/**/*.vue', {
    eager: true,
});

initTheme();
void loadAppearance();

void createInertiaApp({
    resolve: (name) => {
        const page = pages[`./Pages/${name}.vue`];

        if (!page) {
            throw new Error(`Unknown page: ${name}`);
        }

        return page.default;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
});
