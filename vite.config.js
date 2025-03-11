import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        host: "0.0.0.0",
        port: 3000,
        origin: 'https://feedcanary.ddev.site:3000',
        strictPort: true,
        cors: {
            origin: 'https://feedcanary.ddev.site'
        }
    },
});
