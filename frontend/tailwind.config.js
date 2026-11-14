import adminTheme from './styles/admin-theme.cjs';

export default {
  content: [
    "../backend/resources/views/**/*.blade.php",
    "./pages/**/*.{vue,js}",
    "./components/**/*.{vue,js}",
    "./layouts/**/*.{vue,js}",
  ],
  theme: { extend: {} },
  plugins: [require("@tailwindcss/line-clamp"), adminTheme],
};

