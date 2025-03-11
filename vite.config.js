import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

const originUrl = "https://feedcanary.ddev.site";
const port = 3000;

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
    ],
    server: {
        host: "0.0.0.0",
        port: port,
        origin: `${originUrl}:${port}`,
        strictPort: true,
        cors: {
            origin: originUrl,
        },
    },
});
