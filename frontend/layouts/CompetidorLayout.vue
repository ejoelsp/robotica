<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

// ICONOS
import {
  BellAlertIcon,
  ClipboardDocumentListIcon,
  DocumentCheckIcon,
  DocumentTextIcon,
  PresentationChartBarIcon,
  UserCircleIcon,
  ArrowRightStartOnRectangleIcon,
  Bars3Icon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'

const page = usePage()
const user = page.props.auth?.user ?? { name: 'Usuario' }
const photoUrl = computed(() => {
  if (user.photo_url) return user.photo_url
  if (user.photo_path) return `/storage/${user.photo_path}`
  return null
})

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
</script>

<template>
  <div class="flex min-h-screen bg-slate-100 lg:h-screen lg:overflow-hidden">
    <aside class="hidden w-72 flex-col border-r border-slate-200 bg-white px-5 py-8 lg:flex">
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

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="border-b border-slate-200 bg-white lg:hidden">
        <div class="flex h-[72px] items-center justify-between px-3 sm:px-4">
          <div class="flex min-w-0 items-center gap-3">
            <button
              type="button"
              class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-700"
              aria-label="Abrir menú del competidor"
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

          <Link href="/profile" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-600">
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
              aria-label="Cerrar menú del competidor"
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
              <p class="text-xs font-semibold text-blue-700">Competidor</p>
            </div>
          </div>

          <nav class="space-y-1 text-sm font-medium">
            <Link href="/competidor/mis-inscripciones" :class="['flex items-center gap-3 px-3 py-2 rounded-xl', page.url.startsWith('/competidor/mis-inscripciones') ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700']">
              <ClipboardDocumentListIcon class="w-5 h-5" />
              <span>Mis Inscripciones</span>
            </Link>
            <Link href="/competidor/notificaciones" :class="['flex items-center gap-3 px-3 py-2 rounded-xl', page.url.startsWith('/competidor/notificaciones') ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700']">
              <BellAlertIcon class="w-5 h-5" />
              <span>Notificaciones</span>
            </Link>
            <Link href="/competidor/resultados" :class="['flex items-center gap-3 px-3 py-2 rounded-xl', page.url.startsWith('/competidor/resultados') ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700']">
              <PresentationChartBarIcon class="w-5 h-5" />
              <span>Resultados</span>
            </Link>
            <Link href="/competidor/certificados" :class="['flex items-center gap-3 px-3 py-2 rounded-xl', page.url.startsWith('/competidor/certificados') ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700']">
              <DocumentCheckIcon class="w-5 h-5" />
              <span>Certificados</span>
            </Link>
            <Link href="/competidor/reclamos" :class="['flex items-center gap-3 px-3 py-2 rounded-xl', page.url.startsWith('/competidor/reclamos') ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700']">
              <DocumentTextIcon class="w-5 h-5" />
              <span>Reclamos</span>
            </Link>
          </nav>

          <div class="mt-6 border-t border-slate-200 pt-4 space-y-2">
            <Link
              href="/profile"
              :class="[
                'flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium',
                page.url.startsWith('/profile')
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

      <main class="flex-1 overflow-y-auto px-3 py-4 sm:px-6 sm:py-6 lg:px-10">
      <slot />
      </main>
    </div>
  </div>
</template>
