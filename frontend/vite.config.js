import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import laravel from "laravel-vite-plugin";
import path from "path";

const isProdServer = process.env.NODE_ENV === "production";
const backendPublic = isProdServer
  ? "/var/www/robotica/backend/public"
  : path.resolve(__dirname, "../backend/public");

export default defineConfig({
  envDir: path.resolve(__dirname, "../backend"),
  plugins: [
    vue(),
    laravel({
      input: ["main.js", "styles/app.css"],
      publicDirectory: backendPublic,
      buildDirectory: "build",
      refresh: [path.resolve(__dirname, "../backend/resources/views/**/*.blade.php")],
    }),
  ],
  server: { hmr: { host: "localhost" } },
  resolve: { alias: { "@": path.resolve(__dirname, "./") } },
});