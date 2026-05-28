<script setup>
import CompetidorLayout from "@/layouts/CompetidorLayout.vue";
import { ref, watch } from "vue";

import {
  BellAlertIcon,
  CheckCircleIcon,
  ClockIcon,
  EnvelopeIcon,
  ExclamationTriangleIcon,
  InboxIcon,
  TrophyIcon,
} from "@heroicons/vue/24/outline";

defineOptions({ layout: CompetidorLayout });

const props = defineProps({
  notificacionesRecibidas: {
    type: Array,
    default: () => [],
  },
  stats: {
    type: Object,
    default: () => ({
      recibidas: 0,
      noLeidas: 0,
    }),
  },
});

const recibidas = ref([...(props.notificacionesRecibidas ?? [])]);
const dashboardStats = ref({ ...(props.stats ?? {}) });

watch(
  () => props.notificacionesRecibidas,
  (value) => {
    recibidas.value = [...(value ?? [])];
  }
);

watch(
  () => props.stats,
  (value) => {
    dashboardStats.value = { ...(value ?? {}) };
  }
);

const canalLabels = {
  app: "Aplicación",
  email: "Correo",
  app_email: "Aplicación y correo",
};

function markAsRead(id) {
  if (!id) return;

  const target = recibidas.value.find((item) => item.id === id);
  if (!target || target.leido) return;

  target.leido = true;
  dashboardStats.value.noLeidas = Math.max(0, Number(dashboardStats.value.noLeidas ?? 0) - 1);

  fetch(`/notificaciones/${id}/leer`, {
    method: "PATCH",
    credentials: "same-origin",
    headers: {
      "Content-Type": "application/json",
      "X-Requested-With": "XMLHttpRequest",
      "X-XSRF-TOKEN": decodeURIComponent(
        document.cookie
          .split("; ")
          .find((row) => row.startsWith("XSRF-TOKEN="))
          ?.split("=")[1] ?? ""
      ),
    },
    body: JSON.stringify({}),
  }).then((response) => {
    if (!response.ok) {
      target.leido = false;
      dashboardStats.value.noLeidas = Number(dashboardStats.value.noLeidas ?? 0) + 1;
    }
  });
}

function typeMeta(type) {
  switch (type) {
    case "resultados_publicados":
      return { icon: TrophyIcon, iconColor: "text-yellow-600", bgColor: "bg-yellow-50" };
    case "inscripcion_aprobada":
      return { icon: CheckCircleIcon, iconColor: "text-emerald-600", bgColor: "bg-emerald-50" };
    case "inscripcion_rechazada":
      return { icon: ExclamationTriangleIcon, iconColor: "text-rose-600", bgColor: "bg-rose-50" };
    default:
      return { icon: BellAlertIcon, iconColor: "text-blue-600", bgColor: "bg-blue-50" };
  }
}
</script>

<template>
  <div class="space-y-5 px-3 py-5 sm:space-y-6 sm:px-0 sm:py-0">
    <div>
      <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Mis Notificaciones</h1>
      <p class="text-sm text-slate-500 mt-1">Consulta los avisos enviados por el sistema y administración</p>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4">
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="h-10 w-10 rounded-xl bg-blue-600/10 flex items-center justify-center">
          <InboxIcon class="w-5 h-5 text-blue-600" />
        </div>
        <p class="mt-4 text-2xl font-semibold text-slate-900 sm:text-3xl">{{ dashboardStats.recibidas }}</p>
        <p class="text-sm text-slate-500 mt-1">Notificaciones recibidas</p>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="h-10 w-10 rounded-xl bg-orange-600/10 flex items-center justify-center">
          <BellAlertIcon class="w-5 h-5 text-orange-600" />
        </div>
        <p class="mt-4 text-2xl font-semibold text-slate-900 sm:text-3xl">{{ dashboardStats.noLeidas }}</p>
        <p class="text-sm text-slate-500 mt-1">No leídas</p>
      </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
      <div class="space-y-3 p-3 sm:space-y-4 sm:p-6">
        <div
          v-for="n in recibidas"
          :key="n.id"
          class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:flex-row sm:gap-4 sm:p-5"
          :class="!n.leido ? 'ring-1 ring-blue-100' : ''"
        >
          <div class="h-12 w-12 rounded-xl flex items-center justify-center shrink-0" :class="typeMeta(n.tipo).bgColor">
            <component :is="typeMeta(n.tipo).icon" class="w-6 h-6" :class="typeMeta(n.tipo).iconColor" />
          </div>

          <div class="min-w-0 flex-1">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
              <div class="min-w-0">
                <p class="font-semibold text-slate-900 leading-tight">{{ n.asunto }}</p>
                <p class="text-sm text-slate-600 mt-1">{{ n.contenido }}</p>
              </div>
              <button
                v-if="!n.leido"
                @click.prevent.stop="markAsRead(n.id)"
                class="rounded-xl bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700 transition hover:bg-blue-100 sm:shrink-0 sm:py-1.5"
                type="button"
              >
                Marcar leída
              </button>
            </div>

            <div class="flex flex-wrap gap-4 text-xs text-slate-500 mt-3">
              <div class="inline-flex items-center gap-1">
                <EnvelopeIcon class="w-4 h-4" />
                <span>{{ canalLabels[n.canal] ?? n.canal }}</span>
              </div>
              <div class="inline-flex items-center gap-1">
                <ClockIcon class="w-4 h-4" />
                <span>{{ n.created_at }}</span>
              </div>
            </div>
          </div>
        </div>

        <div v-if="recibidas.length === 0" class="py-10 text-center text-sm text-slate-500 sm:py-12 sm:text-base">
          No tienes notificaciones recibidas.
        </div>
      </div>
    </div>
  </div>
</template>
