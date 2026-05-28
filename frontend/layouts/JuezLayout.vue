<script setup>
import { computed, ref, watch } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import {
  TrophyIcon,
  ArrowRightOnRectangleIcon,
  UserCircleIcon,
  ClipboardDocumentCheckIcon,
  Bars3Icon,
  XMarkIcon,
} from "@heroicons/vue/24/outline";

const page = usePage();

const user = computed(() => page.props.juez ?? page.props.auth?.user ?? {});
const competenciaActual = computed(() => page.props.competenciaActual ?? null);

const competitionName = computed(() => {
  return competenciaActual.value?.nombre || "COMPETENCIA";
});

const fullName = computed(() => {
  const name = user.value?.name ?? "";
  const lastName = user.value?.last_name ?? "";
  return `${name} ${lastName}`.trim() || "Juez";
});

const photoUrl = computed(() => {
  if (user.value?.photo_url) return user.value.photo_url;
  if (user.value?.photo_path) return `/storage/${user.value.photo_path}`;
  return null;
});

const isMobileMenuOpen = ref(false);

watch(
  () => page.url,
  () => {
    isMobileMenuOpen.value = false;
  }
);
</script>

<template>
  <div class="min-h-screen bg-slate-100">
    <header class="border-b border-slate-200 bg-white">
      <div class="mx-auto flex h-[76px] max-w-[1400px] items-center justify-between px-3 sm:h-[88px] sm:px-6 lg:h-[100px] lg:px-10">
        <Link
          href="/juez/evaluaciones"
          class="flex min-w-0 items-center gap-3 rounded-2xl transition hover:opacity-90 sm:gap-4"
        >
          <div
            class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-violet-600 text-white shadow-md sm:h-12 sm:w-12 sm:rounded-2xl lg:h-14 lg:w-14"
          >
            <TrophyIcon class="h-5 w-5 sm:h-6 sm:w-6 lg:h-7 lg:w-7" />
          </div>

          <div class="min-w-0">
            <h1 class="truncate text-sm font-semibold leading-tight text-slate-900 sm:text-base lg:text-[20px]">
              {{ competitionName }}
            </h1>
            <p class="mt-0.5 text-xs text-slate-500 sm:mt-1 sm:text-[14px]">Panel del Juez</p>
          </div>
        </Link>

        <div class="hidden items-center gap-5 lg:flex">
          <Link
            href="/juez/evaluaciones"
            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
          >
            <ClipboardDocumentCheckIcon class="h-5 w-5" />
            Calificar
          </Link>

          <Link
            href="/juez/profile"
            class="flex items-center gap-3 text-slate-900 transition hover:opacity-80"
          >
            <template v-if="photoUrl">
              <img
                :src="photoUrl"
                :alt="`Foto de ${fullName}`"
                class="h-12 w-12 rounded-full object-cover ring-2 ring-slate-200"
              />
            </template>

            <template v-else>
              <div
                class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-200 text-slate-500 ring-2 ring-slate-200"
              >
                <UserCircleIcon class="h-7 w-7" />
              </div>
            </template>

            <span class="text-[16px] font-medium">Perfil</span>
          </Link>

          <div class="h-10 w-px bg-slate-200"></div>

          <Link
            href="/logout"
            method="post"
            as="button"
            class="inline-flex items-center gap-3 text-[16px] font-medium text-red-600 transition hover:text-red-700"
          >
            <span>Cerrar Sesión</span>
            <ArrowRightOnRectangleIcon class="h-6 w-6" />
          </Link>
        </div>

        <button
          type="button"
          class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-700 lg:hidden"
          aria-label="Abrir menú del juez"
          @click="isMobileMenuOpen = true"
        >
          <Bars3Icon class="h-6 w-6" />
        </button>
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
      enter-from-class="translate-y-[-8px] opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transform transition duration-150 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-[-8px] opacity-0"
    >
      <div
        v-if="isMobileMenuOpen"
        class="fixed left-3 right-3 top-[84px] z-50 rounded-2xl border border-slate-200 bg-white p-4 shadow-xl sm:left-6 sm:right-6 sm:top-[96px] lg:hidden"
      >
        <div class="mb-3 flex items-center justify-between">
          <p class="text-sm font-semibold text-slate-900">{{ fullName }}</p>
          <button
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-700"
            aria-label="Cerrar menú del juez"
            @click="isMobileMenuOpen = false"
          >
            <XMarkIcon class="h-5 w-5" />
          </button>
        </div>

        <div class="space-y-2">
          <Link
            href="/juez/evaluaciones"
            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
            @click="isMobileMenuOpen = false"
          >
            <ClipboardDocumentCheckIcon class="h-5 w-5" />
            Calificar
          </Link>

          <Link
            href="/juez/profile"
            class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            @click="isMobileMenuOpen = false"
          >
            <UserCircleIcon class="h-5 w-5" />
            Perfil
          </Link>

          <Link
            href="/logout"
            method="post"
            as="button"
            class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50"
            @click="isMobileMenuOpen = false"
          >
            <ArrowRightOnRectangleIcon class="h-5 w-5" />
            Cerrar SesiÃ³n
          </Link>
        </div>
      </div>
    </transition>

    <main class="mx-auto w-full max-w-[1400px] px-3 py-4 sm:px-6 sm:py-6 lg:px-10">
      <slot />
    </main>
  </div>
</template>
