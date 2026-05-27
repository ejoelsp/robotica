<script setup>
import { computed, ref, watch } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import {
  ArrowPathIcon,
  ClipboardDocumentListIcon,
  EyeIcon,
  Squares2X2Icon,
} from "@heroicons/vue/24/outline";

const page = usePage();

const competenciaId = computed(() => page.props.competenciaId ?? null);
const competencias = computed(() => page.props.competencias ?? []);
const categorias = computed(() => page.props.categorias ?? []);
const vista = computed(() => page.props.vista ?? { scope: null, sorteo: null, error: null });

const selectedCompetition = ref(competenciaId.value ?? "");
const selectedCategoryId = ref(vista.value.scope?.categoria_id ?? categorias.value[0]?.id ?? null);
const selectedRondaId = ref(vista.value.scope?.ronda_id ?? categorias.value[0]?.rondas?.[0]?.id ?? null);

const selectedCategory = computed(() => {
  return categorias.value.find((item) => Number(item.id) === Number(selectedCategoryId.value)) ?? null;
});

const rondasDisponibles = computed(() => selectedCategory.value?.rondas ?? []);

const sorteoGroups = computed(() => {
  const detalles = vista.value.sorteo?.detalles ?? [];

  if (vista.value.sorteo?.tipo_sorteo !== "enfrentamiento") {
    return [];
  }

  return Object.values(
    detalles.filter((detalle) => detalle.estado !== "directo").reduce((acc, detalle) => {
      const key = detalle.grupo ?? detalle.orden;
      acc[key] ??= { grupo: key, items: [] };
      acc[key].items.push(detalle);
      return acc;
    }, {})
  ).sort((a, b) => Number(a.grupo) - Number(b.grupo));
});

const sorteoDirectItems = computed(() => {
  const detalles = vista.value.sorteo?.detalles ?? [];

  if (vista.value.sorteo?.tipo_sorteo !== "enfrentamiento") {
    return [];
  }

  return detalles
    .filter((detalle) => detalle.estado === "directo")
    .sort((a, b) => Number(a.orden ?? 0) - Number(b.orden ?? 0));
});

const sorteoOrdenItems = computed(() => {
  return [...(vista.value.sorteo?.detalles ?? [])]
    .sort((a, b) => Number(a.orden ?? 0) - Number(b.orden ?? 0))
    .map((detalle) => ({
      orden: detalle.orden,
      participante: detalle.nombre_prototipo || detalle.equipo_nombre || "Sin participante",
      institucion: detalle.institucion || "Sin institucion",
    }));
});

const sorteoTitle = computed(() => {
  if (!vista.value.sorteo) return "Sorteo pendiente";

  return vista.value.sorteo.tipo_sorteo === "enfrentamiento"
    ? "Llaves de enfrentamiento"
    : "Orden de participación";
});

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

  router.get("/sorteos", params, {
    replace: true,
    preserveScroll: true,
    preserveState: true,
  });
}

function participantFor(group, side) {
  return group.items.find((item) => item.lado === side) ?? null;
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
          <p class="text-sm uppercase tracking-[0.2em] text-white/80">Club de Robótica ESPOCH</p>
          <h1 class="mt-2 text-3xl font-bold leading-tight">Sorteos</h1>
          <p class="mt-2 text-sm text-white/85">
            Consulta las llaves de enfrentamiento y el orden de participación por categoría y ronda.
          </p>
        </div>

        <Link
          href="/resultados-en-vivo"
          class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-slate-100"
        >
          <EyeIcon class="h-5 w-5" />
          Ver resultados en vivo
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
            <label class="mb-1 block text-sm font-medium text-slate-700">Categoría</label>
            <select
              v-model="selectedCategoryId"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option disabled :value="null">Seleccionar categoría</option>
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
              <ArrowPathIcon class="h-5 w-5" />
              Ver sorteo
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

      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">
                {{ vista.scope?.categoria_nombre || "Categoría" }} · {{ vista.scope?.ronda_nombre || "Ronda" }}
              </p>
              <h2 class="mt-1 text-xl font-bold text-slate-900">{{ sorteoTitle }}</h2>
            </div>

            <span
              class="inline-flex w-fit items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold"
              :class="vista.sorteo ? 'bg-blue-50 text-blue-700 ring-1 ring-blue-100' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200'"
            >
              <ClipboardDocumentListIcon class="h-4 w-4" />
              {{ vista.sorteo?.estado || "pendiente" }}
            </span>
          </div>
        </div>

        <div v-if="vista.sorteo?.tipo_sorteo === 'enfrentamiento' && (sorteoGroups.length || sorteoDirectItems.length)" class="space-y-5">
          <div v-if="sorteoGroups.length" class="overflow-x-auto">
            <div class="border-b border-slate-100 bg-slate-50 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
              {{ sorteoDirectItems.length ? "Ronda previa" : "Llaves de enfrentamiento" }}
            </div>
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
                <tr>
                  <th class="w-24 px-6 py-4 font-semibold">Combate</th>
                  <th class="px-6 py-4 font-semibold">Participante A</th>
                  <th class="w-16 px-2 py-4 text-center font-semibold"></th>
                  <th class="px-6 py-4 font-semibold">Participante B</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 bg-white">
                <tr v-for="group in sorteoGroups" :key="`sorteo-${group.grupo}`">
                  <td class="px-6 py-4">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">
                      {{ group.grupo }}
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    <p class="font-semibold text-slate-900">
                      {{ participantFor(group, 'A')?.nombre_prototipo || participantFor(group, 'A')?.equipo_nombre || group.items[0]?.nombre_prototipo || group.items[0]?.equipo_nombre || "-" }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                      {{ participantFor(group, 'A')?.institucion || group.items[0]?.institucion || "Sin institucion" }}
                    </p>
                  </td>
                  <td class="px-2 py-4 text-center">
                    <span class="inline-flex h-7 min-w-8 items-center justify-center rounded-lg bg-slate-900 px-2 text-[11px] font-bold text-white">
                      VS
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    <p class="font-semibold text-slate-900">
                      {{ participantFor(group, 'B')?.nombre_prototipo || participantFor(group, 'B')?.equipo_nombre || "-" }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                      {{ participantFor(group, 'B')?.institucion || "Sin institucion" }}
                    </p>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="sorteoDirectItems.length" class="overflow-x-auto">
            <div class="border-b border-emerald-100 bg-emerald-50 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-emerald-700">
              Pasan directo
            </div>
            <table class="min-w-full text-sm">
              <thead class="bg-white text-left text-[11px] uppercase tracking-wide text-slate-500">
                <tr>
                  <th class="w-24 px-6 py-4 font-semibold">Orden</th>
                  <th class="px-6 py-4 font-semibold">Participante</th>
                  <th class="px-6 py-4 font-semibold">Institucion</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-emerald-50 bg-white">
                <tr v-for="item in sorteoDirectItems" :key="`directo-${item.inscripcion_id}`">
                  <td class="px-6 py-4 font-semibold text-emerald-700">{{ item.orden }}</td>
                  <td class="px-6 py-4 font-semibold text-slate-900">{{ item.nombre_prototipo || item.equipo_nombre || "-" }}</td>
                  <td class="px-6 py-4 text-slate-600">{{ item.institucion || "Sin institucion" }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-else-if="vista.sorteo && sorteoOrdenItems.length" class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
              <tr>
                <th class="w-24 px-6 py-4 font-semibold">Orden</th>
                <th class="px-6 py-4 font-semibold">Participante</th>
                <th class="px-6 py-4 font-semibold">Institucion</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <tr v-for="item in sorteoOrdenItems" :key="`orden-${item.orden}-${item.participante}`">
                <td class="px-6 py-4">
                  <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">
                    {{ item.orden }}
                  </span>
                </td>
                <td class="px-6 py-4 font-semibold text-slate-900">{{ item.participante }}</td>
                <td class="px-6 py-4 text-slate-600">{{ item.institucion }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="px-6 py-14 text-center">
          <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100">
            <Squares2X2Icon class="h-7 w-7 text-slate-500" />
          </div>
          <p class="mt-4 text-base font-semibold text-slate-900">El sorteo aun no ha sido generado para esta ronda.</p>
          <p class="mt-1 text-sm text-slate-500">Cuando el juez genere el sorteo, aparecera en esta seccion publica.</p>
        </div>
      </div>
    </div>
  </div>
</template>
