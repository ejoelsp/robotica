<script setup>
import axios from "axios";
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Link } from "@inertiajs/vue3";
import {
  SignalIcon,
  ArrowPathIcon,
  EyeIcon,
  ClockIcon,
} from "@heroicons/vue/24/outline";

const props = defineProps({
  competenciaId: { type: [Number, null], default: null },
  competencias: { type: Array, default: () => [] },
});

const selectedCompetition = ref(props.competenciaId ?? "");
const selectedCategoryId = ref(null);
const selectedRondaId = ref(null);

const liveData = ref({
  competition_id: null,
  generated_at: null,
  meta: null,
  stream: null,
  scopes: [],
  selected: null,
});
const liveLoading = ref(false);
const liveConnected = ref(false);
const liveNotice = ref("");
let liveEventSource = null;

const liveCategories = computed(() => {
  const seen = new Map();

  for (const item of liveData.value.scopes ?? []) {
    if (!seen.has(item.categoria_id)) {
      seen.set(item.categoria_id, {
        id: item.categoria_id,
        nombre: item.categoria_nombre,
      });
    }
  }

  return Array.from(seen.values());
});

const liveRondas = computed(() => {
  return (liveData.value.scopes ?? [])
    .filter((item) => {
      return !selectedCategoryId.value || Number(item.categoria_id) === Number(selectedCategoryId.value);
    })
    .map((item) => ({
      id: item.ronda_id,
      nombre: item.ronda_nombre,
    }))
    .filter((item, index, array) => {
      return array.findIndex((candidate) => Number(candidate.id) === Number(item.id)) === index;
    });
});

const visibleScopes = computed(() => {
  return (liveData.value.scopes ?? []).filter((item) => {
    if (selectedCategoryId.value && Number(item.categoria_id) !== Number(selectedCategoryId.value)) {
      return false;
    }

    if (selectedRondaId.value && Number(item.ronda_id) !== Number(selectedRondaId.value)) {
      return false;
    }

    return true;
  });
});

function formatUpdatedAt(value) {
  if (!value) return "Sin actualización";

  return new Date(value).toLocaleString("es-EC", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function closeLiveStream() {
  if (liveEventSource) {
    liveEventSource.close();
    liveEventSource = null;
  }

  liveConnected.value = false;
}

function syncFiltersFromPayload(payload) {
  const scopes = payload?.scopes ?? [];

  if (!scopes.length) {
    selectedCategoryId.value = null;
    selectedRondaId.value = null;
    return;
  }

  const categoryExists = scopes.some(
    (item) => Number(item.categoria_id) === Number(selectedCategoryId.value)
  );

  if (selectedCategoryId.value && !categoryExists) {
    selectedCategoryId.value = null;
  }

  const matchingRounds = scopes.filter(
    (item) => !selectedCategoryId.value || Number(item.categoria_id) === Number(selectedCategoryId.value)
  );

  const roundExists = matchingRounds.some(
    (item) => Number(item.ronda_id) === Number(selectedRondaId.value)
  );

  if (selectedRondaId.value && !roundExists) {
    selectedRondaId.value = null;
  }
}

async function loadSnapshot() {
  if (Number(selectedCompetition.value) <= 0) {
    liveData.value = {
      competition_id: null,
      generated_at: null,
      meta: null,
      stream: null,
      scopes: [],
      selected: null,
    };
    liveNotice.value = "No hay competencias configuradas para resultados en vivo.";
    return;
  }

  liveLoading.value = true;

  try {
    const params = {
      competencia_id: Number(selectedCompetition.value),
    };

    if (Number(selectedCategoryId.value) > 0) {
      params.categoria_id = Number(selectedCategoryId.value);
    }

    if (Number(selectedRondaId.value) > 0) {
      params.ronda_id = Number(selectedRondaId.value);
    }

    const { data } = await axios.get("/resultados/en-vivo", { params });
    liveData.value = data;
    liveNotice.value = "";
    syncFiltersFromPayload(data);
  } catch (error) {
    liveData.value = {
      competition_id: null,
      generated_at: null,
      meta: null,
      stream: null,
      scopes: [],
      selected: null,
    };
    liveNotice.value = error?.response?.data?.message || "No se pudo cargar el panel público en vivo.";
  } finally {
    liveLoading.value = false;
  }
}

function connectLiveStream() {
  closeLiveStream();

  if (Number(selectedCompetition.value) <= 0) {
    return;
  }

  const params = new URLSearchParams({
    competencia_id: String(selectedCompetition.value),
  });

  if (Number(selectedCategoryId.value) > 0) {
    params.set("categoria_id", String(selectedCategoryId.value));
  }

  if (Number(selectedRondaId.value) > 0) {
    params.set("ronda_id", String(selectedRondaId.value));
  }

  liveEventSource = new EventSource(`/resultados/en-vivo/stream?${params.toString()}`);

  liveEventSource.addEventListener("live-results", (event) => {
    try {
      const payload = JSON.parse(event.data);
      liveData.value = payload;
      liveConnected.value = true;
      liveNotice.value = "";
      syncFiltersFromPayload(payload);
    } catch {
      liveNotice.value = "No se pudo interpretar una actualización en vivo.";
    }
  });

  liveEventSource.addEventListener("heartbeat", () => {
    liveConnected.value = true;
  });

  liveEventSource.onerror = () => {
    liveConnected.value = false;
  };
}

watch(
  () => props.competenciaId,
  (value) => {
    selectedCompetition.value = value ?? "";
    loadSnapshot();
    connectLiveStream();
  }
);

watch(
  () => [selectedCompetition.value, selectedCategoryId.value, selectedRondaId.value],
  () => {
    loadSnapshot();
    connectLiveStream();
  }
);

onMounted(() => {
  loadSnapshot();
  connectLiveStream();
});

onBeforeUnmount(() => {
  closeLiveStream();
});
</script>

<template>
  <div class="min-h-screen bg-slate-100">
    <div class="mx-auto w-full max-w-[1180px] px-4 py-8 sm:px-6 lg:px-4 space-y-6">
      <div class="flex flex-col gap-4 rounded-3xl bg-gradient-to-r from-cyan-700 via-blue-600 to-sky-500 px-6 py-8 text-white shadow-lg md:flex-row md:items-end md:justify-between">
        <div>
          <p class="text-sm uppercase tracking-[0.2em] text-white/80">Club de Robótica ESPOCH</p>
          <h1 class="mt-2 text-3xl font-bold leading-tight">Resultados en Vivo</h1>
          <p class="mt-2 text-sm text-white/85">
            Sigue las clasificaciones publicadas que están actualizándose en tiempo real.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <span
            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
            :class="liveConnected ? 'bg-white text-emerald-700 ring-1 ring-emerald-200' : 'bg-white text-slate-700 ring-1 ring-slate-200'"
          >
            {{ liveConnected ? "Conexión SSE activa" : "Reconectando SSE" }}
          </span>

          <Link
            href="/resultados"
            class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-slate-100"
          >
            <EyeIcon class="h-5 w-5" />
            Ver resultados publicados
          </Link>
        </div>
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
              <option :value="null">Todas</option>
              <option v-for="item in liveCategories" :key="item.id" :value="item.id">
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
              <option :value="null">Todas</option>
              <option v-for="item in liveRondas" :key="item.id" :value="item.id">
                {{ item.nombre }}
              </option>
            </select>
          </div>

          <div class="flex items-end">
            <button
              type="button"
              @click="loadSnapshot"
              class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-white transition hover:bg-slate-800"
            >
              <ArrowPathIcon class="h-5 w-5" />
              Actualizar
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="liveNotice"
        class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
      >
        {{ liveNotice }}
      </div>

      <div
        v-if="liveLoading"
        class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-500 shadow-sm"
      >
        Cargando resultados en vivo...
      </div>

      <div v-else class="grid grid-cols-1 gap-6">
        <div
          v-for="scope in visibleScopes"
          :key="scope.key"
          class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden"
        >
          <div class="bg-gradient-to-r from-sky-600 to-cyan-600 p-4 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p class="font-semibold text-white">{{ scope.categoria_nombre }}</p>
                <p class="mt-1 text-sm text-sky-100">{{ scope.ronda_nombre }} · {{ scope.mecanismo_nombre }}</p>
              </div>

              <div class="flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center rounded-full bg-white/15 px-3 py-1.5 text-xs font-semibold text-white">
                  {{ scope.estado_publicacion }}
                </span>

                <span class="inline-flex items-center rounded-full bg-white/15 px-3 py-1.5 text-xs font-semibold text-white">
                  <ClockIcon class="mr-1 h-4 w-4" />
                  {{ scope.updated_at ? formatUpdatedAt(scope.updated_at) : "Sin actualización" }}
                </span>
              </div>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-white">
                <tr class="border-b border-slate-200 text-left text-black">
                  <th class="px-6 py-4 font-medium w-[110px]">Posición</th>
                  <th class="px-6 py-4 font-medium">Equipo</th>
                  <th class="px-6 py-4 font-medium">Institución</th>
                  <th class="px-6 py-4 font-medium">Resultado</th>
                  <th class="px-6 py-4 font-medium">Puntaje</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-slate-200">
                <tr v-for="row in scope.rows" :key="`${scope.key}-${row.posicion}-${row.equipo_nombre}`" class="hover:bg-slate-50/60">
                  <td class="px-6 py-4 font-semibold text-slate-900">{{ row.posicion }}°</td>
                  <td class="px-6 py-4">
                    <p class="font-medium text-slate-900">{{ row.equipo_nombre }}</p>
                  </td>
                  <td class="px-6 py-4 text-slate-600">{{ row.institucion || "Sin institución" }}</td>
                  <td class="px-6 py-4 text-slate-900">{{ row.resultado_label }}</td>
                  <td class="px-6 py-4 text-slate-700">{{ row.puntaje_total ?? "-" }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="!visibleScopes.length" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-500 shadow-sm">
          No hay scopes de resultados en vivo publicados para los filtros actuales.
        </div>
      </div>
    </div>
  </div>
</template>
