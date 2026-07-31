import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // Les feuilles de styles sont importées depuis les entrées JS
            // (voir chat.js) : déclarées ici en entrées .scss, elles seraient
            // servies en <link> par le serveur de dev, avec un type MIME que
            // le navigateur refuse.
            input: [
                'resources/js/app.js',
                'resources/js/chat.js',
                'resources/js/cortex-styles.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
