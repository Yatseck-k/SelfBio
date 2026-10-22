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
    build: {
        rolldownOptions: {
            // Without it Rolldown may put shared code (its runtime helpers) into the SelfBio entry chunk, and the game
            // entry imports that chunk for them. Importing a chunk runs its top level: a second Inertia app started on
            // every game page («Page not found: ./Pages/PuzzleCatalogPage.vue»). 'strict' keeps entry chunks to their
            // own exports, so shared code goes to a chunk of its own.
            preserveEntrySignatures: 'strict',
        },
    },
    // `make dev` runs Vite in the node container: listen on all its interfaces, the browser connects through localhost.
    server: {
        host: '0.0.0.0',
        port: Number(process.env.VITE_PORT ?? 5173),
        strictPort: true,
        hmr: { host: 'localhost' },
    },
});
