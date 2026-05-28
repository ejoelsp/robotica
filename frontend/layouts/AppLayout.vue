<template>
  <div class="min-h-screen bg-[#f5f7fb] flex flex-col">
    <div
      v-if="toastMessage"
      class="fixed right-4 top-4 z-50 flex items-start gap-2 rounded-lg px-4 py-3 text-sm text-white shadow-lg"
      :class="toastType === 'success' ? 'bg-green-500' : 'bg-red-500'"
    >
      <span>{{ toastMessage }}</span>
    </div>

    <header class="border-b bg-white">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6">
        <div class="flex min-w-0 items-center gap-3">
          <button
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-700 lg:hidden"
            aria-label="Abrir menu"
            @click="isMobileMenuOpen = true"
          >
            <Bars3Icon class="h-6 w-6" />
          </button>

          <img :src="logo" class="h-10 w-10 object-contain" alt="Logo" />
          <span class="truncate text-base font-semibold text-slate-900 sm:text-lg">
            Club de Robótica ESPOCH
          </span>
        </div>

        <nav class="hidden items-center gap-6 text-sm text-slate-600 lg:flex">
          <Link href="/" class="hover:text-slate-900">Inicio</Link>
          <Link href="/competencias" class="hover:text-slate-900">Competencias</Link>
          <div class="group relative">
            <button type="button" class="hover:text-slate-900">Resultados</button>
            <div
              class="invisible absolute left-0 top-full z-40 min-w-48 rounded-xl border border-slate-200 bg-white py-2 opacity-0 shadow-lg transition group-hover:visible group-hover:opacity-100"
            >
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

        <div class="hidden items-center gap-3 lg:flex">
          <Link
            href="/login"
            class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
          >
            Iniciar sesión
          </Link>
          <Link
            href="/register"
            class="rounded-md bg-black px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900"
          >
            Registrarme
          </Link>
        </div>
      </div>
    </header>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isMobileMenuOpen"
        class="fixed inset-0 z-40 bg-slate-950/40 lg:hidden"
        @click="isMobileMenuOpen = false"
      />
    </transition>

    <transition
      enter-active-class="transform transition duration-200 ease-out"
      enter-from-class="-translate-x-full"
      enter-to-class="translate-x-0"
      leave-active-class="transform transition duration-150 ease-in"
      leave-from-class="translate-x-0"
      leave-to-class="-translate-x-full"
    >
      <aside
        v-if="isMobileMenuOpen"
        class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] border-r border-slate-200 bg-white p-5 lg:hidden"
      >
        <div class="mb-6 flex items-center justify-between">
          <div class="flex min-w-0 items-center gap-3">
            <img :src="logo" class="h-9 w-9 object-contain" alt="Logo" />
            <span class="truncate text-sm font-semibold text-slate-900">
              Club de Robótica ESPOCH
            </span>
          </div>
          <button
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-700"
            aria-label="Cerrar menu"
            @click="isMobileMenuOpen = false"
          >
            <XMarkIcon class="h-5 w-5" />
          </button>
        </div>

        <nav class="space-y-1 text-sm font-medium text-slate-700">
          <Link href="/" class="block rounded-lg px-3 py-2 hover:bg-slate-100" @click="isMobileMenuOpen = false">Inicio</Link>
          <Link href="/competencias" class="block rounded-lg px-3 py-2 hover:bg-slate-100" @click="isMobileMenuOpen = false">Competencias</Link>
          <Link href="/resultados" class="block rounded-lg px-3 py-2 hover:bg-slate-100" @click="isMobileMenuOpen = false">Resultados publicados</Link>
          <Link href="/resultados-en-vivo" class="block rounded-lg px-3 py-2 hover:bg-slate-100" @click="isMobileMenuOpen = false">En vivo</Link>
          <Link href="/sorteos" class="block rounded-lg px-3 py-2 hover:bg-slate-100" @click="isMobileMenuOpen = false">Sorteos</Link>
          <Link href="/contacto" class="block rounded-lg px-3 py-2 hover:bg-slate-100" @click="isMobileMenuOpen = false">Contacto</Link>
        </nav>

        <div class="mt-6 space-y-2 border-t border-slate-200 pt-5">
          <Link
            href="/login"
            class="block rounded-lg border border-slate-300 px-3 py-2 text-center text-sm text-slate-700 hover:bg-slate-50"
            @click="isMobileMenuOpen = false"
          >
            Iniciar sesión
          </Link>
          <Link
            href="/register"
            class="block rounded-lg bg-black px-3 py-2 text-center text-sm font-semibold text-white hover:bg-slate-900"
            @click="isMobileMenuOpen = false"
          >
            Registrarme
          </Link>
        </div>
      </aside>
    </transition>

    <main class="flex-1">
      <slot />
    </main>

    <footer class="mt-10 border-t bg-white">
      <div class="mx-auto flex max-w-7xl flex-col justify-between gap-2 px-6 py-4 text-xs text-slate-500 md:flex-row">
        <span>&copy; {{ year }} Club de Robótica ESPOCH</span>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import { Bars3Icon, XMarkIcon } from "@heroicons/vue/24/outline";
import logo from "../assets/logos/robotica-logo.png";

const year = new Date().getFullYear();
const page = usePage();
const isMobileMenuOpen = ref(false);

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

watch(
  () => page.url,
  () => {
    isMobileMenuOpen.value = false;
  }
);
</script>
