import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import "../frontend/styles/app.css";
import "./lib/echo";

// layout global
import AppLayout from "./layouts/AppLayout.vue";

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
