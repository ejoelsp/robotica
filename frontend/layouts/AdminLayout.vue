<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'

// Heroicons para los ítems
import {
  Squares2X2Icon,
  TrophyIcon,
  ClipboardDocumentListIcon,
  PresentationChartBarIcon,
  ChartPieIcon,
  BuildingOfficeIcon,
  TagIcon,
  BellAlertIcon,
  UserCircleIcon,
  ArrowRightStartOnRectangleIcon,
  ShieldCheckIcon,
} from '@heroicons/vue/24/outline'

const page = usePage()
const user = page.props.auth?.user ?? { name: 'Administrador' }

// Logout
const logout = () => {
  router.post('/logout')
}
</script>

<template>
  <!-- Sidebar fijo, contenido con scroll -->
  <div class="flex h-screen bg-slate-100 overflow-hidden">
    <!-- SIDEBAR -->
    <aside
      class="w-60 bg-white border-r border-slate-200 px-5 py-8 flex flex-col"
    >
      <!-- Logo -->
      <div class="flex items-center gap-3 mb-5">
        <div
          class="h-10 w-10 rounded-2xl bg-gradient-to-tr from-purple-500 to-blue-500 flex items-center justify-center text-white text-xl font-bold"
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
      <div class="mb-3">
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
              Administrador
            </p>
          </div>
        </div>
      </div>
      <!-- Menú principal -->
      <nav class="space-y-1 text-sm font-medium">
        <!-- Dashboard -->
        <Link
          href="/admin/dashboard"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-xl',
            page.url.startsWith('/admin/dashboard')
              ? 'bg-blue-600 text-white'
              : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700'
          ]"
        >
          <Squares2X2Icon class="w-5 h-5" />
          <span>Dashboard</span>
        </Link>

        <!-- Competencias -->
        <Link
          href="/admin/competencias"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-xl',
            page.url.startsWith('/admin/competencias')
              ? 'bg-blue-50 text-blue-700'
              : 'text-slate-600 hover:bg-slate-50'
          ]"
        >
          <TrophyIcon class="w-5 h-5" />
          <span>Competencias</span>
        </Link>

        <!-- Categorías -->
        <Link
          href="/admin/categorias"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-xl',
            page.url.startsWith('/admin/categorias')
              ? 'bg-blue-50 text-blue-700'
              : 'text-slate-600 hover:bg-slate-50'
          ]"
        >
          <TagIcon class="w-5 h-5" />
          <span>Categorías</span>
        </Link>

        <!-- Inscripciones -->
        <Link
          href="/admin/inscripciones"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-xl',
            page.url.startsWith('/admin/inscripciones')
              ? 'bg-blue-50 text-blue-700'
              : 'text-slate-600 hover:bg-slate-50'
          ]"
        >
          <ClipboardDocumentListIcon class="w-5 h-5" />
          <span>Inscripciones</span>
        </Link>

        <!-- Asignación de jueces -->
        <Link
          href="/admin/asignacion-jueces"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-xl',
            page.url.startsWith('/admin/asignacion-jueces')
              ? 'bg-blue-50 text-blue-700'
              : 'text-slate-600 hover:bg-slate-50'
          ]"
        >
          <BuildingOfficeIcon class="w-5 h-5" />
          <span>Asignación de jueces</span>
        </Link>


        <!-- Notificaciones -->
        <Link
          href="/admin/notificaciones"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-xl',
            page.url.startsWith('/admin/notificaciones')
              ? 'bg-blue-50 text-blue-700'
              : 'text-slate-600 hover:bg-slate-50'
          ]"
        >
          <BellAlertIcon class="w-5 h-5" />
          <span>Notificaciones</span>
        </Link>

        <!-- Resultados -->
        <Link
          href="/admin/resultados"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-xl',
            page.url.startsWith('/admin/resultados')
              ? 'bg-blue-50 text-blue-700'
              : 'text-slate-600 hover:bg-slate-50'
          ]"
        >
          <PresentationChartBarIcon class="w-5 h-5" />
          <span>Resultados</span>
        </Link>

        <!-- Análisis Histórico -->
        <Link
          href="/admin/analisis-historico"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-xl',
            page.url.startsWith('/admin/analisis-historico')
              ? 'bg-blue-50 text-blue-700'
              : 'text-slate-600 hover:bg-slate-50'
          ]"
        >
          <ChartPieIcon class="w-5 h-5" />
          <span>Análisis Histórico</span>
        </Link>

        <!-- Control de Acceso -->
        <Link
          href="/admin/control-acceso"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-xl',
            page.url.startsWith('/admin/control-acceso')
              ? 'bg-blue-50 text-blue-700'
              : 'text-slate-600 hover:bg-slate-50'
          ]"
        >
          <ShieldCheckIcon class="w-5 h-5" />
          <span>Control de Acceso</span>
        </Link>


      </nav>

      <!-- Zona inferior: Mi perfil + Cerrar sesión -->
      <div class="mt-auto pt-1 border-t border-slate-200">
        <!-- Mi Perfil -->
        <Link
          href="/admin/profile"
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
