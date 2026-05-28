<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'

// Heroicons para los ítems
import {
  TrophyIcon,
  ClipboardDocumentListIcon,
  PresentationChartBarIcon,
  ChartPieIcon,
  BuildingOfficeIcon,
  TagIcon,
  BellAlertIcon,
  DocumentCheckIcon,
  DocumentTextIcon,
  UserCircleIcon,
  ArrowRightStartOnRectangleIcon,
  ShieldCheckIcon,
  Bars3Icon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'

const page = usePage()
const user = page.props.auth?.user ?? { name: 'Administrador' }
const photoUrl = computed(() => {
  if (user.photo_url) return user.photo_url
  if (user.photo_path) return `/storage/${user.photo_path}`
  return null
})
const contadorNoLeidas = ref(null)
const notificacionesNoLeidas = computed(() =>
  Number(
    contadorNoLeidas.value
      ?? page.props.stats?.noLeidas
      ?? page.props.notificacionesNoLeidas
      ?? page.props.adminNotificaciones?.noLeidas
      ?? page.props["adminNotificaciones.noLeidas"]
      ?? 0
  )
)

const actualizarContadorNotificaciones = async () => {
  try {
    const response = await fetch('/admin/notificaciones/contador', {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })

    if (!response.ok) return

    const data = await response.json()
    contadorNoLeidas.value = Number(data.no_leidas ?? 0)
  } catch {
    contadorNoLeidas.value = null
  }
}

const sincronizarContadorNotificaciones = (event) => {
  if (event?.detail?.noLeidas !== undefined) {
    contadorNoLeidas.value = Number(event.detail.noLeidas ?? 0)
    return
  }

  actualizarContadorNotificaciones()
}

// Logout
const logout = () => {
  router.post('/logout')
}

const isMobileMenuOpen = ref(false)

watch(
  () => page.url,
  () => {
    isMobileMenuOpen.value = false
  }
)

onMounted(() => {
  actualizarContadorNotificaciones()
  window.addEventListener('admin-notificaciones:actualizar-contador', sincronizarContadorNotificaciones)
})

onUnmounted(() => {
  window.removeEventListener('admin-notificaciones:actualizar-contador', sincronizarContadorNotificaciones)
})
</script>

<template>
  <!-- Sidebar fijo, contenido con scroll -->
  <div class="flex min-h-screen bg-slate-100 lg:h-screen lg:overflow-hidden">
    <!-- SIDEBAR -->
    <aside
      class="hidden w-60 bg-white border-r border-slate-200 px-5 py-8 lg:flex lg:flex-col"
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
          <img
            v-if="photoUrl"
            :src="photoUrl"
            :alt="`Foto de ${user.name}`"
            class="h-9 w-9 rounded-full object-cover ring-2 ring-blue-100"
          />
          <div
            v-else
            class="h-9 w-9 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-semibold"
          >
            {{ user.name.charAt(0).toUpperCase() }}
          </div>
          <div>
            <p class="text-xs font-semibold text-blue-700">
              Administrador
            </p>
          </div>
        </div>
      </div>
      <!-- Menú principal -->
      <nav class="space-y-1 text-sm font-medium">
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
          <BuildingOfficeIcon class="w-10 h-10" />
          <span>Asignación de jueces y gestión de rondas</span>
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
          <BellAlertIcon class="w-5 h-5 shrink-0" />
          <span class="min-w-0 flex-1">Notificaciones</span>
          <span
            v-if="notificacionesNoLeidas > 0"
            class="ml-auto inline-flex min-w-6 items-center justify-center rounded-full bg-red-600 px-2 py-0.5 text-xs font-bold leading-none text-white"
          >
            {{ notificacionesNoLeidas > 99 ? '99+' : notificacionesNoLeidas }}
          </span>
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

        <!-- Reportes -->
        <Link
          href="/admin/reportes"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-xl',
            page.url.startsWith('/admin/reportes')
              ? 'bg-blue-50 text-blue-700'
              : 'text-slate-600 hover:bg-slate-50'
          ]"
        >
          <DocumentTextIcon class="w-5 h-5" />
          <span>Reportes</span>
        </Link>

        <!-- Certificados -->
        <Link
          href="/admin/certificados"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-xl',
            page.url.startsWith('/admin/certificados')
              ? 'bg-blue-50 text-blue-700'
              : 'text-slate-600 hover:bg-slate-50'
          ]"
        >
          <DocumentCheckIcon class="w-5 h-5" />
          <span>Certificados</span>
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
            page.url.startsWith('/admin/profile')
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

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="border-b border-slate-200 bg-white lg:hidden">
        <div class="flex h-[72px] items-center justify-between px-3 sm:px-4">
          <div class="flex min-w-0 items-center gap-3">
            <button
              type="button"
              class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-700"
              aria-label="Abrir menú del administrador"
              @click="isMobileMenuOpen = true"
            >
              <Bars3Icon class="h-6 w-6" />
            </button>
            <div class="h-9 w-9 rounded-xl bg-gradient-to-tr from-purple-500 to-blue-500 text-white flex items-center justify-center text-base">
              🏆
            </div>
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-slate-900">Club Robótica</p>
              <p class="text-xs font-medium text-blue-600">ESPOCH</p>
            </div>
          </div>

          <Link href="/admin/profile" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-600">
            <img
              v-if="photoUrl"
              :src="photoUrl"
              :alt="`Foto de ${user.name}`"
              class="h-10 w-10 rounded-full object-cover"
            />
            <UserCircleIcon v-else class="h-6 w-6" />
          </Link>
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
          class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] border-r border-slate-200 bg-white px-4 py-5 lg:hidden"
        >
          <div class="mb-5 flex items-center justify-between">
            <div class="flex min-w-0 items-center gap-3">
              <div class="h-9 w-9 rounded-xl bg-gradient-to-tr from-purple-500 to-blue-500 text-white flex items-center justify-center text-base">
                🏆
              </div>
              <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-900">Club Robótica</p>
                <p class="text-xs font-medium text-blue-600">ESPOCH</p>
              </div>
            </div>
            <button
              type="button"
              class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-700"
              aria-label="Cerrar menú del administrador"
              @click="isMobileMenuOpen = false"
            >
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>

          <div class="mb-5 rounded-2xl bg-blue-50 px-4 py-3 flex items-center gap-3">
            <img
              v-if="photoUrl"
              :src="photoUrl"
              :alt="`Foto de ${user.name}`"
              class="h-9 w-9 rounded-full object-cover ring-2 ring-blue-100"
            />
            <div
              v-else
              class="h-9 w-9 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-semibold"
            >
              {{ user.name.charAt(0).toUpperCase() }}
            </div>
            <div>
              <p class="text-xs font-semibold text-blue-700">Administrador</p>
            </div>
          </div>

          <nav class="space-y-1 text-sm font-medium max-h-[calc(100vh-280px)] overflow-y-auto pr-1">
            <Link href="/admin/competencias" :class="['flex items-center gap-3 px-3 py-2 rounded-xl', page.url.startsWith('/admin/competencias') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50']">
              <TrophyIcon class="w-5 h-5" />
              <span>Competencias</span>
            </Link>
            <Link href="/admin/categorias" :class="['flex items-center gap-3 px-3 py-2 rounded-xl', page.url.startsWith('/admin/categorias') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50']">
              <TagIcon class="w-5 h-5" />
              <span>Categorías</span>
            </Link>
            <Link href="/admin/inscripciones" :class="['flex items-center gap-3 px-3 py-2 rounded-xl', page.url.startsWith('/admin/inscripciones') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50']">
              <ClipboardDocumentListIcon class="w-5 h-5" />
              <span>Inscripciones</span>
            </Link>
            <Link href="/admin/asignacion-jueces" :class="['flex items-center gap-3 px-3 py-2 rounded-xl', page.url.startsWith('/admin/asignacion-jueces') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50']">
              <BuildingOfficeIcon class="w-5 h-5 shrink-0" />
              <span>Asignación de jueces y gestión de rondas</span>
            </Link>
            <Link href="/admin/notificaciones" :class="['flex items-center gap-3 px-3 py-2 rounded-xl', page.url.startsWith('/admin/notificaciones') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50']">
              <BellAlertIcon class="w-5 h-5 shrink-0" />
              <span class="min-w-0 flex-1">Notificaciones</span>
              <span
                v-if="notificacionesNoLeidas > 0"
                class="ml-auto inline-flex min-w-6 items-center justify-center rounded-full bg-red-600 px-2 py-0.5 text-xs font-bold leading-none text-white"
              >
                {{ notificacionesNoLeidas > 99 ? '99+' : notificacionesNoLeidas }}
              </span>
            </Link>
            <Link href="/admin/resultados" :class="['flex items-center gap-3 px-3 py-2 rounded-xl', page.url.startsWith('/admin/resultados') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50']">
              <PresentationChartBarIcon class="w-5 h-5" />
              <span>Resultados</span>
            </Link>
            <Link href="/admin/reportes" :class="['flex items-center gap-3 px-3 py-2 rounded-xl', page.url.startsWith('/admin/reportes') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50']">
              <DocumentTextIcon class="w-5 h-5" />
              <span>Reportes</span>
            </Link>
            <Link href="/admin/certificados" :class="['flex items-center gap-3 px-3 py-2 rounded-xl', page.url.startsWith('/admin/certificados') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50']">
              <DocumentCheckIcon class="w-5 h-5" />
              <span>Certificados</span>
            </Link>
            <Link href="/admin/analisis-historico" :class="['flex items-center gap-3 px-3 py-2 rounded-xl', page.url.startsWith('/admin/analisis-historico') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50']">
              <ChartPieIcon class="w-5 h-5" />
              <span>Análisis Histórico</span>
            </Link>
            <Link href="/admin/control-acceso" :class="['flex items-center gap-3 px-3 py-2 rounded-xl', page.url.startsWith('/admin/control-acceso') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50']">
              <ShieldCheckIcon class="w-5 h-5" />
              <span>Control de Acceso</span>
            </Link>
          </nav>

          <div class="mt-4 border-t border-slate-200 pt-4 space-y-2">
            <Link
              href="/admin/profile"
              :class="[
                'flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium',
                page.url.startsWith('/admin/profile')
                  ? 'bg-blue-600 text-white'
                  : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700'
              ]"
            >
              <UserCircleIcon class="w-5 h-5" />
              <span>Mi Perfil</span>
            </Link>

            <button
              @click="logout"
              class="flex w-full items-center gap-3 px-3 py-2 text-sm font-semibold text-red-600 hover:text-red-700"
            >
              <ArrowRightStartOnRectangleIcon class="w-5 h-5" />
              <span>Cerrar Sesión</span>
            </button>
          </div>
        </aside>
      </transition>

      <!-- CONTENIDO -->
      <main class="flex-1 overflow-y-auto px-3 py-4 sm:px-6 sm:py-6 lg:px-10">
        <slot />
      </main>
    </div>
  </div>
</template>
