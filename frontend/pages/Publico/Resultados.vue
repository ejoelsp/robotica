<script setup>
import { computed, ref, watch } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import {
  TrophyIcon,
  EyeIcon,
  CheckBadgeIcon,
  ClockIcon,
} from "@heroicons/vue/24/outline";

const page = usePage();

const competenciaId = computed(() => page.props.competenciaId ?? null);
const competencias = computed(() => page.props.competencias ?? []);
const categorias = computed(() => page.props.categorias ?? []);
const vista = computed(() => page.props.vista ?? { scope: null, summary: null, rows: [], error: null });

const selectedCompetition = ref(competenciaId.value ?? "");
const selectedCategoryId = ref(vista.value.scope?.categoria_id ?? categorias.value[0]?.id ?? null);
const selectedRondaId = ref(vista.value.scope?.ronda_id ?? categorias.value[0]?.rondas?.[0]?.id ?? null);

const selectedCategory = computed(() => {
  return categorias.value.find((item) => Number(item.id) === Number(selectedCategoryId.value)) ?? null;
});

const rondasDisponibles = computed(() => selectedCategory.value?.rondas ?? []);

function syncSelection() {
  selectedCompetition.value = competenciaId.value ?? "";
  selectedCategoryId.value = vista.value.scope?.categoria_id ?? categorias.value[0]?.id ?? null;
  selectedRondaId.value = vista.value.scope?.ronda_id ?? selectedCategory.value?.rondas?.[0]?.id ?? null;
}

function applyFilters() {
  const params = {};

  if (Number(selectedCompetition.value) > 0) params.competencia_id = Number(selectedCompetition.value);
  if (Number(selectedCategoryId.value) > 0) params.categoria_id = Number(selectedCategoryId.value);
  if (Number(selectedRondaId.value) > 0) params.ronda_id = Number(selectedRondaId.value);

  router.get("/resultados", params, {
    replace: true,
    preserveScroll: true,
    preserveState: true,
  });
}

function formatUpdatedAt(value) {
  if (!value) return "Sin fecha";

  return new Date(value).toLocaleString("es-EC", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
}

watch(
  () => [competenciaId.value, categorias.value, vista.value.scope?.categoria_id, vista.value.scope?.ronda_id],
  () => {
    syncSelection();
  },
  { immediate: true }
);

watch(
  () => selectedCategoryId.value,
  () => {
    const firstRondaId = selectedCategory.value?.rondas?.[0]?.id ?? null;
    const exists = (selectedCategory.value?.rondas ?? []).some(
      (item) => Number(item.id) === Number(selectedRondaId.value)
    );
    if (!exists) selectedRondaId.value = firstRondaId;
  }
);
</script>

<template>
  <div class="min-h-screen bg-slate-100">
    <div class="mx-auto w-full max-w-[1180px] space-y-6 px-4 py-8 sm:px-6 lg:px-4">
      <div class="flex flex-col gap-4 rounded-3xl bg-gradient-to-r from-blue-700 via-sky-600 to-teal-500 px-6 py-8 text-white shadow-lg md:flex-row md:items-end md:justify-between">
        <div>
          <p class="text-sm uppercase tracking-[0.2em] text-white/80">Club de Robotica ESPOCH</p>
          <h1 class="mt-2 text-3xl font-bold leading-tight">Resultados Publicados</h1>
          <p class="mt-2 text-sm text-white/85">
            Consulta clasificaciones oficiales publicadas por competencia, categoria y ronda.
          </p>
        </div>

        <Link
          href="/"
          class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-slate-100"
        >
          <EyeIcon class="h-5 w-5" />
          Volver al inicio
        </Link>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Competencia</label>
            <select
              v-model="selectedCompetition"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option disabled value="">Seleccionar competencia</option>
              <option v-for="item in competencias" :key="item.id" :value="item.id">
                {{ item.nombre }}
              </option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Categoria</label>
            <select
              v-model="selectedCategoryId"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option disabled :value="null">Seleccionar categoria</option>
              <option v-for="item in categorias" :key="item.id" :value="item.id">
                {{ item.nombre }}
              </option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Ronda</label>
            <select
              v-model="selectedRondaId"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option disabled :value="null">Seleccionar ronda</option>
              <option v-for="item in rondasDisponibles" :key="item.id" :value="item.id">
                {{ item.nombre }}
              </option>
            </select>
          </div>

          <div class="flex items-end">
            <button
              type="button"
              @click="applyFilters"
              class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-white transition hover:bg-slate-800"
            >
              <CheckBadgeIcon class="h-5 w-5" />
              Ver resultados
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="vista.error"
        class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
      >
        {{ vista.error }}
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-3xl border border-blue-200 bg-blue-50 p-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm font-semibold text-blue-700">Categoria</p>
              <p class="mt-3 text-xl font-bold text-blue-900">
                {{ vista.scope?.categoria_nombre || "Sin seleccion" }}
              </p>
            </div>
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-100">
              <TrophyIcon class="h-7 w-7 text-blue-700" />
            </div>
          </div>
        </div>

        <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm font-semibold text-emerald-700">Ronda</p>
              <p class="mt-3 text-xl font-bold text-emerald-900">
                {{ vista.scope?.ronda_nombre || "Sin seleccion" }}
              </p>
            </div>
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100">
              <CheckBadgeIcon class="h-7 w-7 text-emerald-700" />
            </div>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm font-semibold text-slate-700">Ultima publicacion</p>
              <p class="mt-3 text-base font-bold text-slate-900">
                {{ formatUpdatedAt(vista.summary?.updated_at) }}
              </p>
            </div>
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100">
              <ClockIcon class="h-7 w-7 text-slate-700" />
            </div>
          </div>
        </div>
      </div>

      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
          <h2 class="text-lg font-semibold text-slate-900">
            {{ competencias.find((item) => Number(item.id) === Number(selectedCompetition))?.nombre || "Resultados" }}
          </h2>
          <p class="mt-1 text-sm text-slate-500">
            Clasificacion oficial - {{ vista.scope?.mecanismo_nombre || "Publicada" }}
          </p>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-white">
              <tr class="border-b border-slate-200 text-left text-black">
                <th class="w-[110px] px-6 py-4 font-medium">Posicion</th>
                <th class="px-6 py-4 font-medium">Equipo</th>
                <th class="px-6 py-4 font-medium">Institucion</th>
                <th class="px-6 py-4 font-medium">Resultado</th>
                <th class="px-6 py-4 font-medium">Puntaje</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-200">
              <tr
                v-for="row in vista.rows"
                :key="`${row.posicion}-${row.equipo_nombre}`"
                class="hover:bg-slate-50/60"
              >
                <td class="px-6 py-4">
                  <span class="font-semibold text-slate-900">{{ row.posicion }}</span>
                </td>
                <td class="px-6 py-4">
                  <p class="font-medium text-slate-900">{{ row.equipo_nombre }}</p>
                </td>
                <td class="px-6 py-4 text-slate-600">{{ row.institucion || "Sin institucion" }}</td>
                <td class="px-6 py-4 text-slate-900">{{ row.resultado_label }}</td>
                <td class="px-6 py-4 text-slate-700">{{ row.puntaje_total ?? "-" }}</td>
              </tr>

              <tr v-if="!vista.rows?.length">
                <td colspan="5" class="px-6 py-10 text-center text-slate-500">
                  No hay resultados publicados para los filtros seleccionados.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
