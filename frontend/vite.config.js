import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import laravel from "laravel-vite-plugin";
import path from "path";

const backendPath = path.resolve(__dirname, "../backend");

export default defineConfig({
  plugins: [
    vue(),
    laravel({
      input: ["main.js", "styles/app.css"],
        //input: ["../frontend/main.js", "../frontend/styles/app.css"],
      publicDirectory: `${backendPath}/public`,
      buildDirectory: "build",
      refresh: [`${backendPath}/resources/views/**/*.blade.php`],
    }),
  ],
  server: { hmr: { host: "localhost" } },
  resolve: { alias: { "@": path.resolve(__dirname, "./") } },
});
