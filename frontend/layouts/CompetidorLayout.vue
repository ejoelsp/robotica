<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = page.props.auth?.user ?? { name: 'Usuario' };

// URL actual, por ejemplo "/dashboard" o "/profile"
const currentUrl = page.url;

// Logout: usamos directamente la URL, sin route()
const logout = () => {
  router.post('/logout');
};
</script>

<template>
  <div class="flex min-h-screen bg-slate-100">
    <!-- SIDEBAR -->
    <aside class="w-68 bg-white border-r border-slate-200 px-5 py-8 flex flex-col">
      <!-- Logo -->
      <div class="flex items-center gap-3 mb-8">
        <div
          class="h-10 w-10 rounded-2xl bg-blue-600 flex items-center justify-center text-white text-xl font-bold"
        >
          🤖
        </div>
        <div>
          <p class="text-sm font-semibold text-slate-900 leading-tight">
            Club Robótica
          </p>
          <p class="text-xs font-medium text-blue-600">
            ESPOCH
          </p>
        </div>
      </div>

      <!-- Tarjeta mini usuario -->
      <div class="mb-6">
        <div class="rounded-2xl bg-blue-50 px-4 py-3 flex items-center gap-3">
          <div
            class="h-9 w-9 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-semibold"
          >
            {{ user.name.charAt(0).toUpperCase() }}
          </div>
          <div>
            <p class="text-xs text-slate-500">Hola, {{ user.name.split(' ')[0] }}!</p>
            <p class="text-xs font-semibold text-blue-700">Competidor</p>
          </div>
        </div>
      </div>

      <!-- Menú -->
      <nav class="flex-1 space-y-1 text-sm font-medium">
        <!-- Dashboard -->
        <Link
          href="/dashboard"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-xl',
            currentUrl.startsWith('/dashboard')
              ? 'bg-blue-50 text-blue-700'
              : 'text-slate-600 hover:bg-slate-50'
          ]"
        >
          Dashboard
        </Link>

        <!-- Próximas Competencias -->
        <Link
          href="#"
          class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-600 hover:bg-slate-50"
        >
          Próximas Competencias
        </Link>

        <!-- Mis Inscripciones -->
        <Link
          href="#"
          class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-600 hover:bg-slate-50"
        >
          Mis Inscripciones
        </Link>

        <!-- Resultados -->
        <Link
          href="#"
          class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-600 hover:bg-slate-50"
        >
          Resultados
        </Link>

        <!-- Mi Perfil -->
        <Link
          href="/profile"
          :class="[
            'mt-4 flex items-center gap-3 px-3 py-2 rounded-xl',
            currentUrl.startsWith('/profile')
              ? 'bg-slate-900 text-white'
              : 'text-slate-600 hover:bg-slate-900 hover:text-white'
          ]"
        >
          Mi Perfil
        </Link>
      </nav>

      <!-- Cerrar sesión abajo -->
      <button
        @click="logout"
        class="mt-8 flex items-center gap-2 px-3 text-sm font-semibold text-red-600 hover:text-red-700"
      >
        Cerrar Sesión
      </button>
    </aside>

    <!-- CONTENIDO -->
    <main class="flex-1 px-10 py-8 overflow-y-auto">
      <slot />
    </main>
  </div>
</template>
