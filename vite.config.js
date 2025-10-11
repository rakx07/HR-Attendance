import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    base: '', // <-- keep paths relative so /hr-attendance works
    build: {
        assetsDir: 'build/assets', // ensure all assets go under /build/assets
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
