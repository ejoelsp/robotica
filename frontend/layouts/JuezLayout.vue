<script setup>
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import {
  TrophyIcon,
  ArrowRightOnRectangleIcon,
  UserCircleIcon,
  ClipboardDocumentCheckIcon,
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
</script>

<template>
  <div class="min-h-screen bg-slate-100">
    <header class="border-b border-slate-200 bg-white">
      <div class="mx-auto flex h-[100px] max-w-[1400px] items-center justify-between px-6 lg:px-10">
        <!-- Bloque izquierdo clickeable -->
        <Link
          href="/juez/evaluaciones"
          class="flex items-center gap-4 rounded-2xl transition hover:opacity-90"
        >
          <div
            class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-violet-600 text-white shadow-md"
          >
            <TrophyIcon class="h-7 w-7" />
          </div>

          <div>
            <h1 class="text-[20px] font-semibold leading-tight text-slate-900">
              {{ competitionName }}
            </h1>
            <p class="mt-1 text-[14px] text-slate-500">Panel del Juez</p>
          </div>
        </Link>

        <div class="flex items-center gap-8">
          <div
            class="inline-flex items-center rounded-full bg-green-500 px-4 py-1.5 text-sm font-semibold text-white shadow-sm"
          >
            Sistema Activo
          </div>

          <!-- Botón Calificar -->
          <Link
            href="/juez/evaluaciones"
            class="inline-flex items-center gap-3 rounded-xl bg-blue-600 px-5 py-3 text-[16px] font-semibold text-white shadow-sm transition hover:bg-blue-700"
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
      </div>
    </header>

    <main class="mx-auto w-full max-w-[1400px] px-6 py-6 lg:px-10">
      <slot />
    </main>
  </div>
</template>