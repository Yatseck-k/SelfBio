import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import i18n from 'laravel-vue-i18n/vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/whodoku/js/app.ts',
                'resources/whodoku/js/orchid-map-editor.ts',
            ],
            refresh: true,
        }),
        vue(),
        i18n(),
        tailwindcss(),
    ],
});
