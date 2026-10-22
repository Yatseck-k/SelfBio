import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18nVue } from 'laravel-vue-i18n';

// The name of the site is sent by the server with every page (APP_NAME); the build-time value is only the fallback.
let appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        appName = props.initialPage.props.brand ?? appName;
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18nVue, {
                lang: import.meta.env.VITE_APP_LOCALE || 'ru',
                resolve: async lang => {
                    const langs = import.meta.glob('../../lang/*.json')
                    return await langs[`../../lang/${lang}.json`]()
                }
            })
            // .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
