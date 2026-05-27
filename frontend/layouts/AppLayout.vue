<template>
  <div class="min-h-screen bg-[#f5f7fb] flex flex-col">
    <div
      v-if="toastMessage"
      class="fixed top-4 right-4 z-50 rounded-lg px-4 py-3 shadow-lg text-sm flex items-start gap-2 text-white"
      :class="toastType === 'success' ? 'bg-green-500' : 'bg-red-500'"
    >
      <span>{{ toastMessage }}</span>
    </div>

    <header class="border-b bg-white">
      <div class="max-w-7xl mx-auto flex items-center justify-between py-4 px-6">
        <div class="flex items-center gap-3">
          <img
            :src="logo"
            class="w-10 h-10 object-contain"
            alt="Logo"
          />
          <span class="font-semibold text-lg text-slate-900">
            Club de Robótica ESPOCH
          </span>
        </div>

        <nav class="hidden md:flex items-center gap-6 text-sm text-slate-600">
          <Link href="/" class="hover:text-slate-900">Inicio</Link>
          <Link href="/competencias" class="hover:text-slate-900">Competencias</Link>
          <div class="group relative">
            <button
              type="button"
              class="hover:text-slate-900"
            >
              Resultados
            </button>
            <div class="invisible absolute left-0 top-full z-40 min-w-48 rounded-xl border border-slate-200 bg-white py-2 opacity-0 shadow-lg transition group-hover:visible group-hover:opacity-100">
              <Link
                href="/resultados"
                class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900"
              >
                Resultados publicados
              </Link>
              <Link
                href="/resultados-en-vivo"
                class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900"
              >
                En vivo
              </Link>
              <Link
                href="/sorteos"
                class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900"
              >
                Sorteos
              </Link>
            </div>
          </div>
          <Link href="/contacto" class="hover:text-slate-900">Contacto</Link>
        </nav>

        <div class="flex items-center gap-3">
          <Link
            href="/login"
            class="px-4 py-2 rounded-md border border-slate-300 text-sm text-slate-700 hover:bg-slate-50"
          >
            Iniciar sesión
          </Link>
          <Link
            href="/register"
            class="px-4 py-2 rounded-md text-sm font-semibold bg-black text-white hover:bg-slate-900"
          >
            Registrarme
          </Link>
        </div>
      </div>
    </header>

    <main class="flex-1">
      <slot />
    </main>

    <footer class="border-t bg-white mt-10">
      <div class="max-w-7xl mx-auto px-6 py-4 text-xs text-slate-500 flex flex-col md:flex-row justify-between gap-2">
        <span>© {{ year }} Club de Robótica ESPOCH</span>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import logo from "../assets/logos/robotica-logo.png";

const year = new Date().getFullYear();
const page = usePage();

const logout = () => {
  router.post(route("logout"));
};

const baseMessage = computed(() => {
  const props = page.props;

  if (props.registerSuccess) {
    return props.registerSuccess;
  }

  if (props.flash?.success) {
    return props.flash.success;
  }

  if (props.flash?.error) {
    return props.flash.error;
  }

  return "";
});

const baseType = computed(() => {
  const props = page.props;

  if (props.flash?.error) {
    return "error";
  }

  return "success";
});

const toastMessage = ref("");
const toastType = ref("success");
let hideTimeout = null;

watch(
  () => baseMessage.value,
  (val) => {
    if (!val) return;

    toastMessage.value = val;
    toastType.value = baseType.value;

    if (hideTimeout) clearTimeout(hideTimeout);
    hideTimeout = setTimeout(() => {
      toastMessage.value = "";
    }, 4000);
  },
  { immediate: true }
);
</script>
