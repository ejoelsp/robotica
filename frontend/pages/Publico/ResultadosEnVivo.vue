<script setup>
import axios from "axios";
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Link } from "@inertiajs/vue3";
import {
  SignalIcon,
  ArrowPathIcon,
  EyeIcon,
  ClockIcon,
  XMarkIcon,
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
const liveConnectionMode = ref("WebSocket");
const liveNotice = ref("");
const selectedDetail = ref(null);
let liveEventSource = null;
let liveChannelName = null;
let liveHeartbeatTimer = null;

const viewerStorageKey = "live_results_viewer_id";
const viewerId = ref("");

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

const liveViewers = computed(() => {
  const metaViewers = Number(liveData.value?.meta?.viewers_count);
  if (Number.isFinite(metaViewers) && metaViewers >= 0) {
    return metaViewers;
  }

  const streamViewers = Number(liveData.value?.stream?.viewers_count);
  if (Number.isFinite(streamViewers) && streamViewers >= 0) {
    return streamViewers;
  }

  return null;
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

function openDetail(scope, row) {
  selectedDetail.value = {
    scope,
    row,
    detail: row.detalle_publico ?? null,
  };
}

function closeDetail() {
  selectedDetail.value = null;
}

function formatNumber(value) {
  const number = Number(value ?? 0);
  return Number.isInteger(number) ? String(number) : number.toFixed(2);
}

function anonymousJudgeLabel(index) {
  return `Juez ${Number(index) + 1}`;
}

function attemptForRow(row, numero) {
  return (row.intentos ?? []).find((item) => Number(item.numero) === Number(numero)) ?? {
    numero,
    label: `Intento ${numero}`,
    resultado_label: "Pendiente",
    tiene_resultado: false,
    es_mejor: false,
  };
}

function closeLiveStream() {
  if (liveEventSource) {
    liveEventSource.close();
    liveEventSource = null;
  }

  if (liveChannelName && window.Echo) {
    window.Echo.leave(liveChannelName);
    liveChannelName = null;
  }

  liveConnected.value = false;
}

function resolveViewerId() {
  if (typeof window === "undefined") {
    return "";
  }

  try {
    const current = window.localStorage.getItem(viewerStorageKey);
    if (current) {
      return current;
    }

    const generated = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    window.localStorage.setItem(viewerStorageKey, generated);
    return generated;
  } catch {
    return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
  }
}

async function sendLiveHeartbeat() {
  if (Number(selectedCompetition.value) <= 0 || !viewerId.value) {
    return;
  }

  try {
    const { data } = await axios.post("/resultados/en-vivo/heartbeat", {
      competencia_id: Number(selectedCompetition.value),
      viewer_id: viewerId.value,
    });

    const count = Number(data?.viewers_count);
    if (Number.isFinite(count)) {
      liveData.value = {
        ...liveData.value,
        stream: {
          ...(liveData.value.stream ?? {}),
          viewers_count: count,
        },
      };
    }
  } catch {
    // no-op: heartbeat errors should not block live results UI
  }
}

function startLiveHeartbeat() {
  if (liveHeartbeatTimer) {
    clearInterval(liveHeartbeatTimer);
    liveHeartbeatTimer = null;
  }

  if (Number(selectedCompetition.value) <= 0) {
    return;
  }

  sendLiveHeartbeat();
  liveHeartbeatTimer = setInterval(sendLiveHeartbeat, 25000);
}

function shouldHandleLiveEvent(payload) {
  if (Number(payload?.competencia_id) !== Number(selectedCompetition.value)) {
    return false;
  }

  if (Number(selectedCategoryId.value) > 0 && Number(payload?.categoria_id) !== Number(selectedCategoryId.value)) {
    return false;
  }

  if (Number(selectedRondaId.value) > 0 && Number(payload?.ronda_id) !== Number(selectedRondaId.value)) {
    return false;
  }

  return true;
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
   
  if (window.Echo) {
    liveConnectionMode.value = "WebSocket";
    liveChannelName = `resultados.competencia.${selectedCompetition.value}`;
    window.Echo
      .channel(liveChannelName)
      .listen(".ResultadosActualizados", async (payload) => {
        liveConnected.value = true;

        if (!shouldHandleLiveEvent(payload)) {
          return;
        }

        await loadSnapshot();
      });
    liveConnected.value = true;
    return;
  }

  liveConnectionMode.value = "SSE";

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
    startLiveHeartbeat();
  }
);

onMounted(() => {
  viewerId.value = resolveViewerId();
  loadSnapshot();
  connectLiveStream();
  startLiveHeartbeat();
});

onBeforeUnmount(() => {
  closeLiveStream();
  if (liveHeartbeatTimer) {
    clearInterval(liveHeartbeatTimer);
    liveHeartbeatTimer = null;
  }
});
</script>

<template>
  <div class="min-h-screen bg-slate-100">
    <div class="mx-auto w-full max-w-[1180px] space-y-5 px-3 py-5 sm:space-y-6 sm:px-6 sm:py-8 lg:px-4">
      <div class="flex flex-col gap-4 rounded-2xl bg-gradient-to-r from-cyan-700 via-blue-600 to-sky-500 px-4 py-6 text-white shadow-lg sm:rounded-3xl sm:px-6 sm:py-8 md:flex-row md:items-end md:justify-between">
        <div>
          <p class="text-xs uppercase tracking-[0.18em] text-white/80 sm:text-sm">Club de Robótica ESPOCH</p>
          <h1 class="mt-2 text-2xl font-bold leading-tight sm:text-3xl">Resultados en Vivo</h1>
          <p class="mt-2 text-sm text-white/85 sm:max-w-2xl">
            Sigue las clasificaciones publicadas que están actualizándose en tiempo real.
          </p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
          <span
            class="inline-flex items-center justify-center rounded-full px-2.5 py-1 text-xs font-semibold"
            :class="liveConnected ? 'bg-white text-emerald-700 ring-1 ring-emerald-200' : 'bg-white text-slate-700 ring-1 ring-slate-200'"
          >
            En vivo
          </span>

          <span
            v-if="liveViewers !== null"
            class="inline-flex items-center gap-2 rounded-full bg-black/35 px-3 py-1 text-xs font-semibold text-white ring-1 ring-white/25"
          >
            <EyeIcon class="h-4 w-4" />
            {{ liveViewers }}
          </span>

          <Link
            href="/resultados"
            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-slate-100 sm:w-auto"
          >
            <EyeIcon class="h-5 w-5" />
            Ver resultados publicados
          </Link>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
        <div class="grid grid-cols-1 gap-3 sm:gap-4 lg:grid-cols-4">
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
            <table class="min-w-[920px] w-full text-sm">
              <thead class="bg-white">
                <tr v-if="scope.usa_enfrentamiento" class="border-b border-slate-200 text-left text-black">
                  <th class="px-3 py-3 font-medium sm:px-6 sm:py-4 w-[120px]">Encuentro</th>
                  <th class="px-3 py-3 font-medium sm:px-6 sm:py-4">Equipo A</th>
                  <th class="px-3 py-3 font-medium sm:px-6 sm:py-4">Resultado</th>
                  <th class="px-3 py-3 font-medium sm:px-6 sm:py-4">Equipo B</th>
                </tr>
                <tr v-else-if="scope.usa_intentos" class="border-b border-slate-200 text-left text-black">
                  <th class="px-3 py-3 font-medium sm:px-6 sm:py-4 w-[100px]">Orden</th>
                  <th
                    v-for="attempt in scope.intentos_headers"
                    :key="`${scope.key}-head-attempt-${attempt.numero}`"
                    class="px-3 py-3 font-medium sm:px-6 sm:py-4 min-w-[130px]"
                  >
                    {{ attempt.label }}
                  </th>
                  <th class="px-3 py-3 font-medium sm:px-6 sm:py-4 min-w-[220px]">Participante</th>
                  <th class="px-3 py-3 font-medium sm:px-6 sm:py-4 min-w-[160px]">Institución</th>
                </tr>
                <tr v-else class="border-b border-slate-200 text-left text-black">
                  <th class="px-3 py-3 font-medium sm:px-6 sm:py-4 w-[110px]">Posición</th>
                  <th class="px-3 py-3 font-medium sm:px-6 sm:py-4">Equipo</th>
                  <th class="px-3 py-3 font-medium sm:px-6 sm:py-4">Institución</th>
                  <th class="px-3 py-3 font-medium sm:px-6 sm:py-4">Resultado</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-slate-200">
                <template v-if="scope.usa_enfrentamiento">
                  <tr
                    v-for="row in scope.rows"
                    :key="`${scope.key}-match-${row.encuentro}`"
                    class="cursor-pointer hover:bg-slate-50/60"
                    @click="openDetail(scope, row)"
                  >
                    <td class="px-3 py-3 font-semibold text-slate-900 sm:px-6 sm:py-4">{{ row.encuentro }}</td>
                    <td class="px-3 py-3 sm:px-6 sm:py-4">
                      <p class="font-medium text-slate-900">{{ row.equipo_a }}</p>
                      <p class="mt-1 text-xs text-slate-500">{{ row.institucion_a || "Sin institucion" }}</p>
                    </td>
                    <td class="px-3 py-3 font-semibold text-slate-900 sm:px-6 sm:py-4">{{ row.resultado_label }}</td>
                    <td class="px-3 py-3 sm:px-6 sm:py-4">
                      <p class="font-medium text-slate-900">{{ row.equipo_b }}</p>
                      <p class="mt-1 text-xs text-slate-500">{{ row.institucion_b || "Sin institucion" }}</p>
                    </td>
                  </tr>
                </template>
                <template v-else-if="scope.usa_intentos">
                  <tr
                    v-for="row in scope.rows"
                    :key="`${scope.key}-attempts-${row.posicion}-${row.equipo_id || row.equipo_nombre}`"
                    class="cursor-pointer hover:bg-slate-50/60"
                    @click="openDetail(scope, row)"
                  >
                    <td class="px-3 py-3 sm:px-6 sm:py-4">
                      <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">
                        {{ row.posicion }}
                      </span>
                    </td>
                    <td
                      v-for="attempt in scope.intentos_headers"
                      :key="`${scope.key}-${row.equipo_id || row.equipo_nombre}-attempt-${attempt.numero}`"
                      class="px-3 py-3 sm:px-6 sm:py-4"
                    >
                      <div
                        class="inline-flex min-h-[34px] items-center rounded-full px-3 py-1.5 text-sm font-semibold"
                        :class="attemptForRow(row, attempt.numero).es_mejor
                          ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
                          : attemptForRow(row, attempt.numero).tiene_resultado
                            ? 'bg-blue-50 text-blue-700 ring-1 ring-blue-100'
                            : 'bg-slate-50 text-slate-500 ring-1 ring-slate-200'"
                      >
                        {{ attemptForRow(row, attempt.numero).resultado_label }}
                      </div>
                    </td>
                    <td class="px-3 py-3 sm:px-6 sm:py-4">
                      <p class="font-semibold text-slate-900">{{ row.equipo_nombre }}</p>
                      <p class="mt-1 text-xs text-slate-500">
                        Mejor intento: {{ row.detalle_publico?.matriz_jueces ? attemptForRow(row, row.mejor_intento_numero).resultado_label : row.resultado_label }}
                      </p>
                    </td>
                    <td class="px-3 py-3 text-slate-600 sm:px-6 sm:py-4">{{ row.institucion || "Sin institución" }}</td>
                  </tr>
                </template>
                <template v-else>
                  <tr
                    v-for="row in scope.rows"
                    :key="`${scope.key}-${row.posicion}-${row.equipo_nombre}`"
                    class="cursor-pointer hover:bg-slate-50/60"
                    @click="openDetail(scope, row)"
                  >
                  <td class="px-3 py-3 font-semibold text-slate-900 sm:px-6 sm:py-4">{{ row.posicion }}°</td>
                  <td class="px-3 py-3 sm:px-6 sm:py-4">
                    <p class="font-medium text-slate-900">{{ row.equipo_nombre }}</p>
                  </td>
                  <td class="px-3 py-3 text-slate-600 sm:px-6 sm:py-4">{{ row.institucion || "Sin institución" }}</td>
                  <td class="px-3 py-3 text-slate-900 sm:px-6 sm:py-4">{{ row.resultado_label }}</td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="!visibleScopes.length" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-500 shadow-sm">
          No hay scopes de resultados en vivo publicados para los filtros actuales.
        </div>
      </div>
    </div>

    <div v-if="selectedDetail" class="fixed inset-0 z-[10060] flex items-end justify-center p-0 sm:items-center sm:p-4">
      <div class="absolute inset-0 bg-slate-950/50" @click="closeDetail"></div>

      <div class="relative max-h-[92vh] w-full max-w-4xl overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:max-h-[90vh] sm:rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-4 py-4 sm:px-5">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">
              {{ selectedDetail.scope.categoria_nombre }} · {{ selectedDetail.scope.ronda_nombre }}
            </p>
            <h3 class="mt-1 text-lg font-bold text-slate-900 sm:text-xl">
              {{ selectedDetail.detail?.titulo || "Detalle del resultado" }}
            </h3>
            <p class="mt-1 text-sm text-slate-500">
              Resultado: <span class="font-semibold text-slate-900">{{ selectedDetail.row.resultado_label }}</span>
            </p>
          </div>

          <button
            type="button"
            class="rounded-xl p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
            @click="closeDetail"
            aria-label="Cerrar detalle"
          >
            <XMarkIcon class="h-5 w-5" />
          </button>
        </div>

        <div class="max-h-[76vh] overflow-y-auto p-4 sm:max-h-[72vh] sm:p-5">
          <div
            v-if="selectedDetail.detail?.matriz_jueces"
            class="mb-5 overflow-x-auto rounded-xl border border-slate-200"
          >
            <table class="min-w-[920px] w-full text-sm">
              <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                  <th class="px-4 py-3">Juez</th>
                  <th
                    v-for="attempt in selectedDetail.detail.matriz_jueces.intentos"
                    :key="`matrix-head-${attempt.numero}`"
                    class="px-4 py-3"
                  >
                    {{ attempt.label }}
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200">
                <tr
                  v-for="(judge, judgeIndex) in selectedDetail.detail.matriz_jueces.jueces"
                  :key="`matrix-judge-${judge.juez_user_id}`"
                >
                  <td class="px-4 py-3 font-medium text-slate-900">{{ anonymousJudgeLabel(judgeIndex) }}</td>
                  <td
                    v-for="attempt in judge.intentos"
                    :key="`matrix-judge-${judge.juez_user_id}-${attempt.numero}`"
                    class="px-4 py-3 text-slate-700"
                  >
                    {{ attempt.label || "-" }}
                  </td>
                </tr>
                <tr class="bg-blue-50 font-bold text-blue-800">
                  <td class="px-4 py-3">Promedio</td>
                  <td
                    v-for="attempt in selectedDetail.detail.matriz_jueces.promedios"
                    :key="`matrix-average-${attempt.numero}`"
                    class="px-4 py-3"
                  >
                    {{ attempt.label || "-" }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <template v-else-if="selectedDetail.detail?.tipo === 'tabla_enfrentamiento_criterios'">
            <div class="mb-4 grid grid-cols-1 gap-3 rounded-2xl bg-slate-50 p-4 text-center sm:grid-cols-3">
              <div>
                <p class="text-xs text-slate-500">{{ selectedDetail.detail.equipo_a }}</p>
                <p class="text-2xl font-black text-slate-900">{{ formatNumber(selectedDetail.detail.total_a) }}</p>
              </div>
              <div class="flex items-center justify-center text-lg font-bold text-slate-400">VS</div>
              <div>
                <p class="text-xs text-slate-500">{{ selectedDetail.detail.equipo_b }}</p>
                <p class="text-2xl font-black text-slate-900">{{ formatNumber(selectedDetail.detail.total_b) }}</p>
              </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200">
              <table class="min-w-[760px] w-full text-sm">
                <thead class="bg-slate-50 text-center text-slate-600">
                  <tr>
                    <th class="px-4 py-3 font-semibold">Puntaje</th>
                    <th class="px-4 py-3 font-semibold">Cantidad</th>
                    <th class="px-4 py-3 font-semibold text-left">Criterio</th>
                    <th class="px-4 py-3 font-semibold">Valor</th>
                    <th class="px-4 py-3 font-semibold">Cantidad</th>
                    <th class="px-4 py-3 font-semibold">Puntaje</th>
                  </tr>
                </thead>

                <tbody class="divide-y divide-slate-200 text-center">
                  <tr
                    v-for="item in selectedDetail.detail.criterios"
                    :key="item.criterio"
                    class="hover:bg-slate-50"
                  >
                    <td class="px-4 py-3 font-medium text-slate-700">
                      {{ formatNumber(item.puntaje_a) }}
                    </td>

                    <td class="px-4 py-3 text-slate-700">
                      {{ formatNumber(item.cantidad_a) }}
                    </td>

                    <td
                      class="px-4 py-3 text-left font-semibold"
                      :class="item.es_penalizacion ? 'text-red-600' : 'text-slate-900'"
                    >
                      {{ item.criterio }}
                    </td>

                    <td class="px-4 py-3 text-slate-700">
                      x {{ formatNumber(item.valor_unitario) }}
                    </td>

                    <td class="px-4 py-3 text-slate-700">
                      {{ formatNumber(item.cantidad_b) }}
                    </td>

                    <td class="px-4 py-3 font-medium text-slate-700">
                      {{ formatNumber(item.puntaje_b) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <template v-else-if="['tabla_individual_criterios', 'tabla_individual_puntaje_maximo'].includes(selectedDetail.detail?.tipo)">
            <div class="overflow-x-auto rounded-xl border border-slate-200">
              <table class="min-w-[760px] w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                  <tr>
                    <th class="px-4 py-3">Criterio</th>
                    <th class="px-4 py-3">{{ selectedDetail.detail?.tipo === 'tabla_individual_puntaje_maximo' ? 'Maximo' : 'Cantidad' }}</th>
                    <th class="px-4 py-3">{{ selectedDetail.detail?.tipo === 'tabla_individual_puntaje_maximo' ? 'Otorgado' : 'Valor' }}</th>
                    <th class="px-4 py-3">Puntaje</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                  <tr v-for="item in selectedDetail.detail.criterios" :key="item.criterio">
                    <td class="px-4 py-3 font-medium" :class="item.es_penalizacion ? 'text-red-600' : 'text-slate-900'">{{ item.criterio }}</td>
                    <td class="px-4 py-3">{{ formatNumber(selectedDetail.detail?.tipo === 'tabla_individual_puntaje_maximo' ? item.puntaje_maximo : item.cantidad) }}</td>
                    <td class="px-4 py-3">{{ selectedDetail.detail?.tipo === 'tabla_individual_puntaje_maximo' ? formatNumber(item.puntaje) : `x ${formatNumber(item.valor_unitario)}` }}</td>
                    <td class="px-4 py-3">{{ formatNumber(item.puntaje) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
              <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Subtotal</p><p class="text-lg font-bold">{{ formatNumber(selectedDetail.detail.subtotal) }}</p></div>
              <div class="rounded-xl bg-red-50 p-4"><p class="text-xs text-red-500">Penalizaciones</p><p class="text-lg font-bold text-red-700">{{ formatNumber(selectedDetail.detail.penalizaciones) }}</p></div>
              <div class="rounded-xl bg-blue-50 p-4"><p class="text-xs text-blue-500">Resultado final</p><p class="text-lg font-bold text-blue-700">{{ formatNumber(selectedDetail.detail.total) }}</p></div>
            </div>
          </template>

          <template v-else-if="selectedDetail.detail?.tipo === 'marcador'">
            <div class="rounded-2xl bg-slate-950 p-6 text-center text-white">
              <p class="text-sm text-slate-300">Marcador final</p>
              <p class="mt-2 text-4xl font-black sm:text-5xl">{{ selectedDetail.detail.marcador_a }} - {{ selectedDetail.detail.marcador_b }}</p>
            </div>
          </template>

          <template v-else-if="selectedDetail.detail?.tipo === 'tiempo'">
            <div class="flex justify-center">
              <div class="w-full max-w-md rounded-2xl bg-slate-50 p-6 text-center">
                <p class="text-sm font-medium text-slate-500">Tiempo</p>
                <p class="mt-2 text-3xl font-black text-slate-900 sm:text-4xl">{{ selectedDetail.detail.tiempo_label }}</p>
              </div>
            </div>
          </template>

          <div v-else class="rounded-xl bg-slate-50 p-5 text-sm text-slate-600">
            No hay detalle adicional para este resultado.
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
