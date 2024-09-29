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
        hmr: {
            host: 'feedcanary.ddev.site',
            protocol: 'wss',
        },
        host: "0.0.0.0",
        port: 3000,
        strictPort: true,
    },
});
