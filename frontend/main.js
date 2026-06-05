import { createApp, h } from "vue";
import { createInertiaApp, router } from "@inertiajs/vue3";
import axios from "axios";
import "../frontend/styles/app.css";
import "./lib/echo";

// layout global
import AppLayout from "./layouts/AppLayout.vue";

const SESSION_EXPIRED_MESSAGE = "Tu sesión ha expirado por inactividad. Inicia sesión nuevamente.";
const PROTECTED_PATH_PREFIXES = ["/admin", "/juez", "/competidor", "/profile"];

let handlingSessionExpiration = false;

function isProtectedPath() {
  if (typeof window === "undefined") return false;

  return PROTECTED_PATH_PREFIXES.some((prefix) => window.location.pathname.startsWith(prefix));
}

function redirectToLogin(message = SESSION_EXPIRED_MESSAGE) {
  if (typeof window === "undefined" || handlingSessionExpiration) return;

  handlingSessionExpiration = true;
  window.sessionStorage.setItem("session-expired-message", message);
  window.location.assign("/login?expired=1");
}

function handleSessionStatus(status, message) {
  if (![401, 419].includes(Number(status)) || typeof window === "undefined") {
    return false;
  }

  if (window.location.pathname === "/login") {
    return true;
  }

  if (isProtectedPath()) {
    redirectToLogin(message);
  } else if (Number(status) === 419) {
    window.location.reload();
  }

  return true;
}

axios.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status;
    const message = error.response?.data?.message || SESSION_EXPIRED_MESSAGE;

    handleSessionStatus(status, message);

    return Promise.reject(error);
  }
);

if (typeof router?.on === "function") {
  router.on("invalid", (event) => {
    const response = event.detail?.response;
    const message = response?.data?.message || SESSION_EXPIRED_MESSAGE;

    if (handleSessionStatus(response?.status, message)) {
      event.preventDefault();
    }
  });
}

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob("./pages/**/*.vue", { eager: true });

    let page = pages[`./pages/${name}.vue`];
    if (!page) {
      throw new Error(`Página no encontrada: ${name}`);
    }

    // Solo usar AppLayout por defecto si la página no definió layout
    if (page.default.layout === undefined) {
      page.default.layout = AppLayout;
    }

    return page;
  },

  setup({ el, App, props, plugin }) {
    createApp({
      render: () => h(App, props),
    })
      .use(plugin)
      .mount(el);
  },
});
