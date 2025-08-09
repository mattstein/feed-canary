import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), "");
  const originUrl = env.VITE_DEV_ORIGIN ?? "https://feedcanary.ddev.site";
  const port = Number(env.VITE_DEV_PORT ?? 3000);
  
  return {
    plugins: [
      laravel({
        input: ["resources/css/app.css", "resources/js/app.js"],
        refresh: true,
      }),
    ],
    server: {
      host: "0.0.0.0",
      port,
      origin: `${originUrl}:${port}`,
      strictPort: true,
      cors: {
        origin: originUrl,
      },
    },
  };
});
