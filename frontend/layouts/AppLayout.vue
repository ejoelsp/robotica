<template>
  <div class="min-h-screen bg-[#f5f7fb] flex flex-col">
    <!-- NAVBAR -->
    <header class="border-b bg-white">
      <div class="max-w-7xl mx-auto flex items-center justify-between py-4 px-6">
        <!-- Logo + título -->
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

        <!-- Menú -->
        <nav class="hidden md:flex items-center gap-6 text-sm text-slate-600">
          <Link href="/" class="hover:text-slate-900">Inicio</Link>
          <Link href="/competencias" class="hover:text-slate-900">Competencias</Link>
          <Link href="/resultados" class="hover:text-slate-900">Resultados</Link>
          <Link href="/contacto" class="hover:text-slate-900">Contacto</Link>
        </nav>

        <!-- Botones -->
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

    <!-- CONTENIDO DE CADA PANTALLA -->
    <main class="flex-1">
      <slot />
    </main>

    <!-- FOOTER -->
    <footer class="border-t bg-white mt-10">
      <div class="max-w-7xl mx-auto px-6 py-4 text-xs text-slate-500 flex flex-col md:flex-row justify-between gap-2">
        <span>© {{ year }} Club de Robótica ESPOCH</span>
        <span>Proyecto de Titulación – Ingeniería en Software – ESPOCH</span>
      </div>
    </footer>
  </div>
</template>


<script setup>
import { computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import logo from '../assets/logos/robotica-logo.png';

const year = new Date().getFullYear();

const logout = () => {
  router.post(route('logout'));
};

// props globales de Inertia
const page = usePage();
const flash = computed(() => page.props.flash || {});


</script>
