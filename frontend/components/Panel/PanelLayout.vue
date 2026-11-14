<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import {
  ArrowRightStartOnRectangleIcon, Bars3Icon, BellIcon, ChevronDownIcon,
  ChevronRightIcon, MoonIcon, SunIcon, UserCircleIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'
import AdminBrand from '@/components/Admin/AdminBrand.vue'
import { panelThemes } from '@/lib/panelTheme'

const props = defineProps({
  role: { type: String, required: true, validator: value => ['admin', 'competidor'].includes(value) },
  roleLabel: { type: String, required: true },
  sectionLabel: { type: String, required: true },
  navigation: { type: Array, required: true },
  profileHref: { type: String, required: true },
  notificationsHref: { type: String, required: true },
  notificationsCounterUrl: { type: String, required: true },
})
const { storageKey: themeStorageKey, read: readTheme, apply: applyTheme, save: saveTheme, clear: clearTheme } = panelThemes[props.role]
const notificationEvent = `${props.role}-notificaciones:actualizar-contador`

const page = usePage()
const user = computed(() => page.props.auth?.user ?? page.props.user ?? { name: props.roleLabel })
const photoUrl = computed(() => user.value.photo_url || (user.value.photo_path ? `/storage/${user.value.photo_path}` : null))
const navigation = computed(() => props.navigation)
const currentPath = computed(() => page.url.split(/[?#]/)[0])
const isCurrent = (href) => currentPath.value === href || currentPath.value.startsWith(`${href}/`)
const currentModule = computed(() => {
  if (isCurrent(props.profileHref)) return 'Mi perfil'
  if (props.role === 'admin' && isCurrent('/admin/dashboard')) return 'Dashboard'
  return navigation.value.find((item) => isCurrent(item.href))?.label ?? (props.role === 'admin' ? 'Panel de administración' : 'Panel del competidor')
})

const theme = ref(readTheme())
applyTheme(theme.value)
const setTheme = (value) => {
  theme.value = value
  saveTheme(value)
}
const syncTheme = (event) => {
  if (event.key === themeStorageKey || event.key === null) {
    theme.value = readTheme()
    applyTheme(theme.value)
  }
}

const contadorNoLeidas = ref(null)
const notificacionesNoLeidas = computed(() => {
  const count = Number(contadorNoLeidas.value ?? page.props.stats?.noLeidas
    ?? page.props.notificacionesNoLeidas ?? page.props.adminNotificaciones?.noLeidas
    ?? page.props['adminNotificaciones.noLeidas'] ?? 0)
  return Number.isFinite(count) ? Math.max(0, count) : 0
})
const notificationLabel = computed(() => notificacionesNoLeidas.value > 0
  ? `Notificaciones: ${notificacionesNoLeidas.value} sin leer` : 'Notificaciones')
let notificationRequest = null
const actualizarContadorNotificaciones = async () => {
  notificationRequest?.abort()
  const request = new AbortController()
  notificationRequest = request
  try {
    const response = await fetch(props.notificationsCounterUrl, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin', signal: request.signal,
    })
    if (!response.ok) return
    const data = await response.json()
    if (!request.signal.aborted) contadorNoLeidas.value = Number(data.no_leidas ?? 0)
  } catch {
    // Conserva el último contador cuando la conexión no está disponible.
  }
}
const sincronizarContadorNotificaciones = (event) => {
  if (event?.detail?.noLeidas !== undefined) {
    notificationRequest?.abort()
    contadorNoLeidas.value = Number(event.detail.noLeidas ?? 0)
  } else actualizarContadorNotificaciones()
}

const isUserMenuOpen = ref(false)
const userMenuContainer = ref(null)
const userMenuButton = ref(null)
const userMenu = ref(null)
const isLoggingOut = ref(false)
const closeUserMenu = (restoreFocus = false) => {
  isUserMenuOpen.value = false
  if (restoreFocus) userMenuButton.value?.focus()
}
const openUserMenu = async (last = false) => {
  isUserMenuOpen.value = true
  await nextTick()
  const items = userMenu.value?.querySelectorAll('[role="menuitem"]')
  items?.[last ? items.length - 1 : 0]?.focus()
}
const toggleUserMenu = () => isUserMenuOpen.value ? closeUserMenu() : openUserMenu()
const handleUserMenuKeydown = (event) => {
  const items = [...(userMenu.value?.querySelectorAll('[role="menuitem"]') ?? [])]
  const index = items.indexOf(document.activeElement)
  let target
  if (event.key === 'ArrowDown') target = (index + 1) % items.length
  if (event.key === 'ArrowUp') target = (index - 1 + items.length) % items.length
  if (event.key === 'Home') target = 0
  if (event.key === 'End') target = items.length - 1
  if (target !== undefined) { event.preventDefault(); items[target]?.focus() }
}
const handleOutsideClick = (event) => {
  if (!userMenuContainer.value?.contains(event.target)) closeUserMenu()
}
const handleMenuFocusout = (event) => {
  if (!userMenuContainer.value?.contains(event.relatedTarget)) closeUserMenu()
}
const logout = () => {
  if (isLoggingOut.value) return
  isLoggingOut.value = true
  closeUserMenu()
  router.post('/logout', {}, { onFinish: () => { isLoggingOut.value = false } })
}

const isMobileMenuOpen = ref(false)
const mobileMenuButton = ref(null)
const mobileMenu = ref(null)
let previousOverflow = ''
let desktopMedia = null
const openMobileMenu = async () => {
  closeUserMenu()
  isMobileMenuOpen.value = true
  await nextTick()
  mobileMenu.value?.querySelector('button')?.focus()
}
const closeMobileMenu = (restoreFocus = false) => {
  isMobileMenuOpen.value = false
  if (restoreFocus) mobileMenuButton.value?.focus()
}
const handleResize = (event) => { if (event.matches) closeMobileMenu() }
const handleKeydown = (event) => {
  if (event.key === 'Escape') {
    if (isMobileMenuOpen.value) closeMobileMenu(true)
    else if (isUserMenuOpen.value) closeUserMenu(true)
  }
  if (event.key !== 'Tab' || !isMobileMenuOpen.value) return
  const items = [...(mobileMenu.value?.querySelectorAll('a[href], button:not([disabled])') ?? [])]
  const first = items[0]
  const last = items[items.length - 1]
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault(); last?.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault(); first?.focus()
  }
}
watch(isMobileMenuOpen, (open) => {
  if (open) {
    previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
  } else document.body.style.overflow = previousOverflow
}, { flush: 'sync' })
watch(() => page.url, () => {
  closeMobileMenu()
  closeUserMenu()
  actualizarContadorNotificaciones()
})
onMounted(() => {
  actualizarContadorNotificaciones()
  window.addEventListener(notificationEvent, sincronizarContadorNotificaciones)
  window.addEventListener('focus', actualizarContadorNotificaciones)
  window.addEventListener('storage', syncTheme)
  document.addEventListener('pointerdown', handleOutsideClick)
  document.addEventListener('keydown', handleKeydown)
  desktopMedia = window.matchMedia('(min-width: 1024px)')
  desktopMedia.addEventListener('change', handleResize)
})
onUnmounted(() => {
  notificationRequest?.abort()
  window.removeEventListener(notificationEvent, sincronizarContadorNotificaciones)
  window.removeEventListener('focus', actualizarContadorNotificaciones)
  window.removeEventListener('storage', syncTheme)
  document.removeEventListener('pointerdown', handleOutsideClick)
  document.removeEventListener('keydown', handleKeydown)
  desktopMedia?.removeEventListener('change', handleResize)
  if (isMobileMenuOpen.value) document.body.style.overflow = previousOverflow
  clearTheme()
})
</script>

<template>
  <div :class="`${role}-shell`" class="panel-shell flex min-h-screen bg-slate-100 lg:h-dvh lg:min-h-0 lg:overflow-hidden">
    <aside :class="`${role}-sidebar`" class="panel-sidebar hidden w-60 shrink-0 flex-col border-r border-slate-200 bg-white px-3 py-6 lg:flex">
      <div class="shrink-0 px-1"><AdminBrand /></div>
      <p class="mb-4 mt-7 shrink-0 px-1 text-[10px] font-medium uppercase tracking-[0.06em] text-slate-500">{{ sectionLabel }}</p>
      <nav :aria-label="`Módulos del ${roleLabel.toLowerCase()}`" class="min-h-0 space-y-1 overflow-y-auto text-[13px]">
        <Link v-for="item in navigation" :key="item.href" :href="item.href" :aria-current="isCurrent(item.href) ? 'page' : undefined"
          :class="['panel-navigation-link flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 leading-5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500', isCurrent(item.href) ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-600 hover:bg-slate-50']">
          <component :is="item.icon" class="h-5 w-5 shrink-0 text-slate-500" stroke-width="1.5" aria-hidden="true" />
          <span class="min-w-0 flex-1">
            <template v-if="item.lines"><span v-for="line in item.lines" :key="line" class="block">{{ line }}</span></template>
            <template v-else>{{ item.label }}</template>
          </span>
          <span v-if="item.href === notificationsHref.split('?')[0] && notificacionesNoLeidas > 0" class="inline-flex min-w-6 justify-center rounded-full bg-red-600 px-2 py-0.5 text-xs font-bold text-white">{{ notificacionesNoLeidas > 99 ? '99+' : notificacionesNoLeidas }}</span>
        </Link>
      </nav>
    </aside>
    <div class="flex min-w-0 flex-1 flex-col lg:min-h-0">
      <header :class="`${role}-topbar`" class="panel-topbar sticky top-0 z-30 shrink-0 border-b border-slate-200 bg-white lg:static">
        <div class="flex h-16 min-w-0 items-center gap-2 px-3 sm:gap-4 sm:px-6 lg:px-10">
          <button ref="mobileMenuButton" type="button" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 lg:hidden" :aria-label="`Abrir menú del ${roleLabel.toLowerCase()}`" :aria-controls="`${role}-mobile-navigation`" :aria-expanded="isMobileMenuOpen" @click="openMobileMenu"><Bars3Icon class="h-6 w-6" aria-hidden="true" /></button>
          <nav aria-label="Ubicación actual" class="min-w-0 flex-1">
            <ol class="flex min-w-0 items-center gap-2 text-sm">
              <li class="hidden shrink-0 text-slate-500 sm:block">{{ sectionLabel }}</li>
              <li class="hidden text-slate-400 sm:block" aria-hidden="true"><ChevronRightIcon class="h-3.5 w-3.5" /></li>
              <li class="min-w-0 truncate font-semibold text-slate-900" aria-current="page" :title="currentModule">{{ currentModule }}</li>
            </ol>
          </nav>
          <div class="flex shrink-0 items-center gap-1 sm:gap-3">
            <div role="group" aria-label="Tema de la interfaz" class="flex items-center rounded-full border border-slate-200 bg-slate-100 p-0.5">
              <button type="button" aria-label="Activar tema claro" title="Tema claro" :aria-pressed="theme === 'light'" :class="['flex h-8 w-8 items-center justify-center rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 sm:h-9 sm:w-9', theme === 'light' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-200']" @click="setTheme('light')"><SunIcon class="h-[18px] w-[18px]" aria-hidden="true" /></button>
              <button type="button" aria-label="Activar tema oscuro" title="Tema oscuro" :aria-pressed="theme === 'dark'" :class="['flex h-8 w-8 items-center justify-center rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 sm:h-9 sm:w-9', theme === 'dark' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-200']" @click="setTheme('dark')"><MoonIcon class="h-[18px] w-[18px]" aria-hidden="true" /></button>
            </div>
            <Link :href="notificationsHref" :aria-label="notificationLabel" :title="notificationLabel" class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
              <BellIcon class="h-5 w-5" aria-hidden="true" />
              <span v-if="notificacionesNoLeidas > 0" class="absolute -right-0.5 -top-0.5 flex min-h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-4 text-white" aria-hidden="true">{{ notificacionesNoLeidas > 99 ? '99+' : notificacionesNoLeidas }}</span>
            </Link>
            <div ref="userMenuContainer" class="relative sm:border-l sm:border-slate-200 sm:pl-3" @focusout="handleMenuFocusout">
              <button :id="`${role}-user-menu-button`" ref="userMenuButton" type="button" :aria-label="`Abrir menú de ${roleLabel}`" aria-haspopup="menu" :aria-controls="`${role}-user-menu`" :aria-expanded="isUserMenuOpen" class="flex h-10 items-center gap-1 rounded-xl px-1 text-slate-700 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 sm:gap-2 sm:px-2" @click="toggleUserMenu" @keydown.down.prevent="openUserMenu()" @keydown.up.prevent="openUserMenu(true)">
                <img v-if="photoUrl" :src="photoUrl" alt="" class="h-8 w-8 rounded-full object-cover" />
                <span v-else class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-500"><UserCircleIcon class="h-7 w-7" aria-hidden="true" /></span>
                <span class="hidden text-sm font-semibold sm:inline">{{ roleLabel }}</span>
                <ChevronDownIcon :class="['h-3.5 w-3.5 shrink-0 text-slate-500 transition-transform', { 'rotate-180': isUserMenuOpen }]" aria-hidden="true" />
              </button>
              <div v-if="isUserMenuOpen" :id="`${role}-user-menu`" ref="userMenu" role="menu" :aria-labelledby="`${role}-user-menu-button`" class="absolute right-0 top-full z-50 mt-2 w-52 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl" @keydown="handleUserMenuKeydown">
                <Link :href="profileHref" role="menuitem" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-slate-700 hover:bg-slate-100 focus:bg-slate-100 focus:outline-none" @click="closeUserMenu()"><UserCircleIcon class="h-5 w-5" aria-hidden="true" />Mi perfil</Link>
                <button type="button" role="menuitem" :disabled="isLoggingOut" class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-medium text-red-600 hover:bg-red-50 focus:bg-red-50 focus:outline-none disabled:opacity-50" @click="logout"><ArrowRightStartOnRectangleIcon class="h-5 w-5" aria-hidden="true" />Cerrar sesión</button>
              </div>
            </div>
          </div>
        </div>
      </header>
      <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-150" leave-to-class="opacity-0">
        <div v-if="isMobileMenuOpen" class="fixed inset-0 z-40 bg-slate-950/60 lg:hidden" aria-hidden="true" @click="closeMobileMenu(true)" />
      </Transition>
      <Transition enter-active-class="transition-transform duration-200" enter-from-class="-translate-x-full" leave-active-class="transition-transform duration-150" leave-to-class="-translate-x-full">
        <aside v-if="isMobileMenuOpen" :id="`${role}-mobile-navigation`" ref="mobileMenu" role="dialog" aria-modal="true" :aria-labelledby="`${role}-mobile-title`" :class="`${role}-sidebar`" class="panel-sidebar fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] flex-col border-r border-slate-200 bg-white px-3 py-5 lg:hidden">
          <div class="flex shrink-0 items-center justify-between gap-2 px-1">
            <AdminBrand :title-id="`${role}-mobile-title`" />
            <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-700 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500" :aria-label="`Cerrar menú del ${roleLabel.toLowerCase()}`" @click="closeMobileMenu(true)"><XMarkIcon class="h-5 w-5" aria-hidden="true" /></button>
          </div>
          <p class="mb-4 mt-7 shrink-0 px-1 text-[10px] font-medium uppercase tracking-[0.06em] text-slate-500">{{ sectionLabel }}</p>
          <nav :aria-label="`Módulos del ${roleLabel.toLowerCase()}`" class="min-h-0 flex-1 space-y-1 overflow-y-auto text-[13px]">
            <Link v-for="item in navigation" :key="item.href" :href="item.href" :aria-current="isCurrent(item.href) ? 'page' : undefined" :class="['panel-navigation-link flex min-h-12 items-center gap-3 rounded-xl px-3 py-3 leading-5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500', isCurrent(item.href) ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-600 hover:bg-slate-50']" @click="closeMobileMenu()">
              <component :is="item.icon" class="h-5 w-5 shrink-0 text-slate-500" stroke-width="1.5" aria-hidden="true" />
              <span class="min-w-0 flex-1">
                <template v-if="item.lines"><span v-for="line in item.lines" :key="line" class="block">{{ line }}</span></template>
                <template v-else>{{ item.label }}</template>
              </span>
              <span v-if="item.href === notificationsHref.split('?')[0] && notificacionesNoLeidas > 0" class="inline-flex min-w-6 justify-center rounded-full bg-red-600 px-2 py-0.5 text-xs font-bold text-white">{{ notificacionesNoLeidas > 99 ? '99+' : notificacionesNoLeidas }}</span>
            </Link>
          </nav>
        </aside>
      </Transition>
      <main :id="`${role}-main-content`" scroll-region class="relative min-w-0 flex-1 px-3 py-4 sm:px-6 sm:py-6 lg:min-h-0 lg:overflow-y-auto lg:px-10"><slot /></main>
    </div>
  </div>
</template>
