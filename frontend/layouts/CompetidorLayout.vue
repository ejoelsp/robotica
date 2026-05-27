<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'

// ICONOS
import {
  BellAlertIcon,
  ClipboardDocumentListIcon,
  DocumentCheckIcon,
  DocumentTextIcon,
  PresentationChartBarIcon,
  UserCircleIcon,
  ArrowRightStartOnRectangleIcon,
} from '@heroicons/vue/24/outline'

const page = usePage()
const user = page.props.auth?.user ?? { name: 'Usuario' }

const logout = () => {
  router.post('/logout')
}
</script>

<template>
  <!-- Sidebar fijo, contenido con scroll -->
  <div class="flex h-screen bg-slate-100 overflow-hidden">
    <!-- SIDEBAR -->
    <aside
      class="w-72 bg-white border-r border-slate-200 px-5 py-8 flex flex-col"
    >
      <!-- Logo (como la imagen: gradiente + trofeo) -->
      <div class="flex items-center gap-3 mb-8">
        <div
          class="h-10 w-10 rounded-2xl bg-gradient-to-tr from-purple-500 to-blue-500 flex items-center justify-center text-white text-xl"
        >
          🏆
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
            <p class="text-xs text-slate-500">
              Hola, {{ user.name.split(' ')[0] }}!
            </p>
            <p class="text-xs font-semibold text-blue-700">
              Competidor
            </p>
          </div>
        </div>
      </div>

      <!-- Menú principal -->
      <nav class="space-y-1 text-sm font-medium">
        <!-- Mis Inscripciones -->
        <Link
          href="/competidor/mis-inscripciones"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-xl',
            page.url.startsWith('/competidor/mis-inscripciones')
              ? 'bg-blue-600 text-white'
              : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700'
          ]"
        >
          <ClipboardDocumentListIcon class="w-5 h-5" />
          <span>Mis Inscripciones</span>
        </Link>

        <!-- Notificaciones -->
        <Link
          href="/competidor/notificaciones"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-xl',
            page.url.startsWith('/competidor/notificaciones')
              ? 'bg-blue-600 text-white'
              : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700'
          ]"
        >
          <BellAlertIcon class="w-5 h-5" />
          <span>Notificaciones</span>
        </Link>

        <!-- Resultados -->
        <Link
          href="/competidor/resultados"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-xl',
            page.url.startsWith('/competidor/resultados')
              ? 'bg-blue-600 text-white'
              : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700'
          ]"
        >
          <PresentationChartBarIcon class="w-5 h-5" />
          <span>Resultados</span>
        </Link>

        <!-- Certificados -->
        <Link
          href="/competidor/certificados"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-xl',
            page.url.startsWith('/competidor/certificados')
              ? 'bg-blue-600 text-white'
              : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700'
          ]"
        >
          <DocumentCheckIcon class="w-5 h-5" />
          <span>Certificados</span>
        </Link>

        <!-- Reclamos -->
        <Link
          href="/competidor/reclamos"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-xl',
            page.url.startsWith('/competidor/reclamos')
              ? 'bg-blue-600 text-white'
              : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700'
          ]"
        >
          <DocumentTextIcon class="w-5 h-5" />
          <span>Reclamos</span>
        </Link>
      </nav>

      <!-- Zona inferior fija -->
      <div class="mt-auto pt-4 border-t border-slate-200">
        <!-- Mi Perfil -->
        <Link
          href="/profile"
          :class="[
            'mb-3 flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium',
            page.url.startsWith('/profile')
              ? 'bg-blue-600 text-white'
              : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700'
          ]"
        >
          <UserCircleIcon class="w-5 h-5" />
          <span>Mi Perfil</span>
        </Link>

        <!-- Cerrar sesión -->
        <button
          @click="logout"
          class="flex items-center gap-3 px-3 text-sm font-semibold text-red-600 hover:text-red-700"
        >
          <ArrowRightStartOnRectangleIcon class="w-5 h-5" />
          <span>Cerrar Sesión</span>
        </button>
      </div>
    </aside>

    <!-- CONTENIDO -->
    <main class="flex-1 px-10 py-8 overflow-y-auto">
      <slot />
    </main>
  </div>
</template>
