<script setup>
import { computed, ref } from "vue";
import CompetidorLayout from "@/layouts/CompetidorLayout.vue";
import {
  TrophyIcon,
  ClockIcon,
  CheckBadgeIcon,
  ChartBarIcon,
  ChevronDownIcon,
} from "@heroicons/vue/24/outline";

defineOptions({ layout: CompetidorLayout });

const props = defineProps({
  resultadosCompetidor: {
    type: Object,
    default: () => ({ summary: {}, items: [] }),
  },
});

const expandedIds = ref([]);
const selectedCategoryId = ref("all");

const summary = computed(() => props.resultadosCompetidor?.summary ?? {});
const categorias = computed(() => props.resultadosCompetidor?.categorias ?? []);
const items = computed(() => props.resultadosCompetidor?.items ?? []);
const filteredItems = computed(() => {
  if (selectedCategoryId.value === "all") {
    return items.value;
  }

  return items.value.filter((item) => Number(item.categoria_id) === Number(selectedCategoryId.value));
});

const publishedItems = computed(() =>
  filteredItems.value.filter((item) => item.estado_resultado === "publicado")
);

function toggleDetails(id) {
  if (expandedIds.value.includes(id)) {
    expandedIds.value = expandedIds.value.filter((item) => item !== id);
    return;
  }

  expandedIds.value = [...expandedIds.value, id];
}

function isExpanded(id) {
  return expandedIds.value.includes(id);
}

function formatDate(value) {
  if (!value) return "Sin fecha";

  return new Date(value).toLocaleString("es-EC", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function statusLabel(item) {
  if (item.estado_resultado === "publicado") {
    return item.estado_publicacion === "cerrado" ? "Resultado cerrado" : "Resultado publicado";
  }

  if (item.estado_resultado === "pendiente_publicacion") {
    return "Pendiente de publicación";
  }

  return "Pendiente";
}

function statusClasses(item) {
  if (item.estado_resultado === "publicado") {
    return item.estado_publicacion === "cerrado"
      ? "bg-slate-900 text-white"
      : "bg-emerald-100 text-emerald-700";
  }

  if (item.estado_resultado === "pendiente_publicacion") {
    return "bg-blue-100 text-blue-700";
  }

  return "bg-amber-100 text-amber-700";
}
</script>

<template>
  <div class="w-full">
    <div class="mx-auto w-full max-w-[1180px] space-y-5 px-3 py-5 sm:space-y-6 sm:px-6 sm:py-6 lg:px-4">
      <section>
        <h1 class="text-2xl font-bold text-slate-900">Mis Resultados</h1>
        <p class="mt-1 text-sm text-slate-500">
          Consulta el resultado obtenido en las categorías con inscripción aprobada.
        </p>
      </section>

      <section class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-sm font-medium text-slate-500">Inscripciones aprobadas</p>
              <p class="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl">
                {{ summary.total_inscripciones ?? 0 }}
              </p>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50">
              <ChartBarIcon class="h-6 w-6 text-blue-600" />
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm sm:p-5">
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-sm font-medium text-emerald-700">Con resultado</p>
              <p class="mt-2 text-2xl font-bold text-emerald-900 sm:text-3xl">
                {{ summary.con_resultado ?? 0 }}
              </p>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white">
              <CheckBadgeIcon class="h-6 w-6 text-emerald-600" />
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm sm:p-5">
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-sm font-medium text-amber-700">Pendientes</p>
              <p class="mt-2 text-2xl font-bold text-amber-900 sm:text-3xl">
                {{ summary.pendientes ?? 0 }}
              </p>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white">
              <ClockIcon class="h-6 w-6 text-amber-600" />
            </div>
          </div>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="grid gap-4 md:grid-cols-[1fr_280px] md:items-end">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Categoría</h2>
            <p class="mt-1 text-sm text-slate-500">
              Solo se muestran las categorías cuyo comprobante de pago ha sido aprobado por el administrador.
            </p>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Seleccionar categoría</label>
            <select
              v-model="selectedCategoryId"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="all">Todas las categorías</option>
              <option v-for="categoria in categorias" :key="categoria.id" :value="categoria.id">
                {{ categoria.nombre }}
              </option>
            </select>
          </div>
        </div>
      </section>

      <section v-if="publishedItems.length" class="grid gap-4 lg:grid-cols-3">
        <article
          v-for="item in publishedItems.slice(0, 3)"
          :key="`highlight-${item.inscripcion_id}`"
          class="rounded-2xl border bg-white p-4 shadow-sm sm:p-5"
          :class="item.es_podio ? 'border-yellow-300' : 'border-slate-200'"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-blue-700">
                {{ item.categoria_nombre }}
              </p>
              <h2 class="mt-2 text-lg font-bold text-slate-900 sm:text-xl">
                {{ item.resultado_label }}
              </h2>
              <p class="mt-1 text-sm text-slate-500">
                {{ item.ronda_nombre }} / posición {{ item.posicion }}
              </p>
            </div>
            <div
              class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl"
              :class="item.es_podio ? 'bg-yellow-100' : 'bg-slate-100'"
            >
              <TrophyIcon
                class="h-6 w-6"
                :class="item.es_podio ? 'text-yellow-700' : 'text-slate-600'"
              />
            </div>
          </div>
        </article>
      </section>

      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-4 py-4 sm:px-5">
          <h2 class="text-lg font-semibold text-slate-900">Resultados</h2>
        </div>

        <div class="divide-y divide-slate-200">
          <article
            v-for="item in filteredItems"
            :key="item.inscripcion_id"
            class="px-4 py-4 sm:px-5 sm:py-5"
          >
            <div class="grid gap-4 sm:gap-5 lg:grid-cols-[2fr_1.4fr_1.1fr_1fr_44px] lg:items-center">
              <div class="min-w-0">
                <h3 class="text-lg font-bold text-slate-900 sm:text-xl">
                  {{ item.categoria_nombre }}
                </h3>
                <p class="mt-2 text-sm text-slate-600">
                  <span class="font-semibold">Equipo:</span> {{ item.equipo_nombre }}
                </p>
                <p class="mt-1 text-sm text-slate-500">
                  {{ item.competencia_nombre }}
                </p>
              </div>

              <div class="min-w-0">
                <p class="text-sm text-slate-500">Prototipo</p>
                <p class="mt-1 truncate text-base font-semibold text-slate-900">
                  {{ item.nombre_prototipo || "Sin prototipo" }}
                </p>
              </div>

              <div>
                <p class="text-sm text-slate-500">Resultado</p>
                <p class="mt-1 text-base font-bold text-slate-900">
                  {{ item.resultado_label || "Aún no publicado" }}
                </p>
                <p v-if="item.posicion" class="mt-1 text-sm text-slate-500">
                  Posición {{ item.posicion }} / {{ item.ronda_nombre }}
                </p>
              </div>

              <div>
                <span
                  class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                  :class="statusClasses(item)"
                >
                  {{ statusLabel(item) }}
                </span>
                <p class="mt-2 text-xs text-slate-500">
                  {{ formatDate(item.publicado_at || item.updated_at) }}
                </p>
              </div>

              <div class="flex lg:justify-end">
                <button
                  v-if="item.resultados?.length"
                  type="button"
                  class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                  :aria-label="isExpanded(item.inscripcion_id) ? 'Ocultar detalle' : 'Ver detalle'"
                  @click="toggleDetails(item.inscripcion_id)"
                >
                  <ChevronDownIcon
                    class="h-5 w-5 transition"
                    :class="isExpanded(item.inscripcion_id) ? 'rotate-180' : ''"
                  />
                </button>
              </div>
            </div>

            <div
              v-if="isExpanded(item.inscripcion_id)"
              class="mt-4 overflow-x-auto rounded-xl border border-slate-200 sm:mt-5"
            >
              <table class="min-w-[860px] w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                  <tr>
                    <th class="px-4 py-3 font-semibold">Ronda</th>
                    <th class="px-4 py-3 font-semibold">Posición</th>
                    <th class="px-4 py-3 font-semibold">Resultado</th>
                    <th class="px-4 py-3 font-semibold">Estado</th>
                    <th class="px-4 py-3 font-semibold">Actualizado</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                  <tr v-for="resultado in item.resultados" :key="resultado.id">
                    <td class="px-4 py-3 font-medium text-slate-900">
                      {{ resultado.ronda_nombre }}
                    </td>
                    <td class="px-4 py-3 text-slate-700">
                      {{ resultado.posicion }}
                    </td>
                    <td class="px-4 py-3 font-semibold text-slate-900">
                      {{ resultado.resultado_label }}
                    </td>
                    <td class="px-4 py-3 text-slate-700">
                      {{ resultado.estado_publicacion }}
                    </td>
                    <td class="px-4 py-3 text-slate-500">
                      {{ formatDate(resultado.publicado_at || resultado.updated_at) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </article>

          <div v-if="!items.length" class="px-4 py-10 text-center text-slate-500 sm:px-5 sm:py-12">
            Todavía no tienes inscripciones aprobadas para consultar resultados.
          </div>

          <div v-else-if="!filteredItems.length" class="px-4 py-10 text-center text-slate-500 sm:px-5 sm:py-12">
            No hay resultados para la categoría seleccionada.
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
