<script setup>
import axios from "axios";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";

import {
  EyeIcon,
  TrophyIcon,
  SignalIcon,
  CheckCircleIcon,
  ClipboardDocumentListIcon,
  PlayIcon,
  ArrowPathIcon,
  ChartBarSquareIcon,
  ClockIcon,
  UserGroupIcon,
  NoSymbolIcon,
} from "@heroicons/vue/24/outline";

defineOptions({ layout: AdminLayout });

const page = usePage();

const activeTab = ref("control");

const competenciaId = computed(() => page.props.competenciaId ?? null);
const competencias = computed(() => page.props.competencias ?? []);
const categorias = computed(() => page.props.categorias ?? []);

const selectedCompetition = ref(competenciaId.value ?? "");
const selectedCategoryId = ref(null);
const selectedRondaId = ref(null);

const evaluacionesLoading = ref(false);
const evaluacionChangingId = ref(null);
const evaluacionesNotice = ref({ type: "", message: "" });
const selectedEstadoEvaluacion = ref("");
const selectedJuezId = ref("");
const evaluacionesData = ref({
  summary: {
    total: 0,
    registradas: 0,
    publicadas: 0,
    anuladas: 0,
    equipos_count: 0,
    jueces_count: 0,
  },
  jueces: [],
  rows: [],
});

const finalesLoading = ref(false);
const consolidando = ref(false);
const publicando = ref(false);
const finalesNotice = ref({ type: "", message: "" });
const finalesData = ref({
  scope: null,
  summary: null,
  rows: [],
});

const selectedLiveCategoryId = ref(null);
const selectedLiveRondaId = ref(null);
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

const selectedCategory = computed(() => {
  return categorias.value.find((item) => Number(item.id) === Number(selectedCategoryId.value)) ?? null;
});

const rondasDisponibles = computed(() => selectedCategory.value?.rondas ?? []);

const hasSelectionReady = computed(() => {
  return (
    Number(selectedCompetition.value) > 0 &&
    Number(selectedCategoryId.value) > 0 &&
    Number(selectedRondaId.value) > 0
  );
});

const selectedCompetitionName = computed(() => {
  return (
    competencias.value.find((item) => Number(item.id) === Number(selectedCompetition.value))?.nombre ??
    "Competencia"
  );
});

const currentSummary = computed(() => {
  return (
    finalesData.value.summary ?? {
      evaluaciones_count: 0,
      equipos_evaluados_count: 0,
      jueces_count: 0,
      clasificaciones_count: 0,
      estado_publicacion: "sin_consolidar",
      updated_at: null,
    }
  );
});

const publicationHistory = computed(() => {
  return finalesData.value.publication_history ?? [];
});

const evaluacionesSummary = computed(() => {
  return evaluacionesData.value.summary ?? {
    total: 0,
    registradas: 0,
    publicadas: 0,
    anuladas: 0,
    equipos_count: 0,
    jueces_count: 0,
  };
});

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
      return !selectedLiveCategoryId.value || Number(item.categoria_id) === Number(selectedLiveCategoryId.value);
    })
    .map((item) => ({
      id: item.ronda_id,
      nombre: item.ronda_nombre,
    }))
    .filter((item, index, array) => {
      return array.findIndex((candidate) => Number(candidate.id) === Number(item.id)) === index;
    });
});

const liveScopesFiltered = computed(() => {
  return (liveData.value.scopes ?? []).filter((item) => {
    if (selectedLiveCategoryId.value && Number(item.categoria_id) !== Number(selectedLiveCategoryId.value)) {
      return false;
    }

    if (selectedLiveRondaId.value && Number(item.ronda_id) !== Number(selectedLiveRondaId.value)) {
      return false;
    }

    return true;
  });
});

function setNotice(type, message) {
  finalesNotice.value = { type, message };
}

function setEvaluacionesNotice(type, message) {
  evaluacionesNotice.value = { type, message };
}

function badgeResultadoEstado(estado) {
  if (estado === "registrado") return "bg-emerald-50 text-emerald-700 ring-emerald-200";
  if (estado === "publicado") return "bg-blue-50 text-blue-700 ring-blue-200";
  if (estado === "anulado") return "bg-red-50 text-red-700 ring-red-200";
  return "bg-slate-100 text-slate-700 ring-slate-200";
}

function badgeLive(estado) {
  if (estado === "borrador") return "bg-red-100 text-red-700 ring-1 ring-red-200";
  if (estado === "visible") return "bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200";
  if (estado === "cerrado") return "bg-slate-900 text-white ring-1 ring-slate-800";
  return "bg-slate-100 text-slate-700 ring-1 ring-slate-200";
}

function closeLiveStream() {
  if (liveEventSource) {
    liveEventSource.close();
    liveEventSource = null;
  }

  liveConnected.value = false;
}

function syncLiveFiltersFromPayload(payload) {
  const scopes = payload?.scopes ?? [];

  if (!scopes.length) {
    selectedLiveCategoryId.value = null;
    selectedLiveRondaId.value = null;
    return;
  }

  const categoryExists = scopes.some(
    (item) => Number(item.categoria_id) === Number(selectedLiveCategoryId.value)
  );

  if (selectedLiveCategoryId.value && !categoryExists) {
    selectedLiveCategoryId.value = null;
  }

  const matchingRounds = scopes.filter(
    (item) => !selectedLiveCategoryId.value || Number(item.categoria_id) === Number(selectedLiveCategoryId.value)
  );

  const roundExists = matchingRounds.some(
    (item) => Number(item.ronda_id) === Number(selectedLiveRondaId.value)
  );

  if (selectedLiveRondaId.value && !roundExists) {
    selectedLiveRondaId.value = null;
  }
}

async function loadLiveSnapshot() {
  if (Number(selectedCompetition.value) <= 0) {
    liveData.value = {
      competition_id: null,
      generated_at: null,
      meta: null,
      stream: null,
      scopes: [],
      selected: null,
    };
    return;
  }

  liveLoading.value = true;

  try {
    const params = {
      competencia_id: Number(selectedCompetition.value),
    };

    if (Number(selectedLiveCategoryId.value) > 0) {
      params.categoria_id = Number(selectedLiveCategoryId.value);
    }

    if (Number(selectedLiveRondaId.value) > 0) {
      params.ronda_id = Number(selectedLiveRondaId.value);
    }

    const { data } = await axios.get("/admin/resultados/en-vivo", { params });
    liveData.value = data;
    liveNotice.value = "";
    syncLiveFiltersFromPayload(data);
  } catch (error) {
    liveData.value = {
      competition_id: null,
      generated_at: null,
      meta: null,
      stream: null,
      scopes: [],
      selected: null,
    };
    liveNotice.value = error?.response?.data?.message || "No se pudo cargar el panel en vivo.";
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

  if (Number(selectedLiveCategoryId.value) > 0) {
    params.set("categoria_id", String(selectedLiveCategoryId.value));
  }

  if (Number(selectedLiveRondaId.value) > 0) {
    params.set("ronda_id", String(selectedLiveRondaId.value));
  }

  liveEventSource = new EventSource(`/admin/resultados/en-vivo/stream?${params.toString()}`);

  liveEventSource.addEventListener("live-results", (event) => {
    try {
      const payload = JSON.parse(event.data);
      liveData.value = payload;
      liveConnected.value = true;
      liveNotice.value = "";
      syncLiveFiltersFromPayload(payload);
    } catch {
      liveNotice.value = "Se recibio una actualizacion en vivo con formato invalido.";
    }
  });

  liveEventSource.addEventListener("heartbeat", () => {
    liveConnected.value = true;
  });

  liveEventSource.onerror = () => {
    liveConnected.value = false;
  };
}

function syncSelectionFromProps() {
  selectedCompetition.value = competenciaId.value ?? "";

  const firstCategoryId = categorias.value[0]?.id ?? null;
  const categoryStillExists = categorias.value.some(
    (item) => Number(item.id) === Number(selectedCategoryId.value)
  );
  selectedCategoryId.value = categoryStillExists ? selectedCategoryId.value : firstCategoryId;

  const firstRondaId =
    (
      categorias.value.find((item) => Number(item.id) === Number(selectedCategoryId.value))?.rondas ?? []
    )[0]?.id ?? null;
  const rondaStillExists = (selectedCategory.value?.rondas ?? []).some(
    (item) => Number(item.id) === Number(selectedRondaId.value)
  );
  selectedRondaId.value = rondaStillExists ? selectedRondaId.value : firstRondaId;
}

async function loadFinales() {
  if (!hasSelectionReady.value) {
    finalesData.value = { scope: null, summary: null, rows: [] };
    return;
  }

  finalesLoading.value = true;

  try {
    const { data } = await axios.get("/admin/resultados/consolidado", {
      params: {
        competencia_id: Number(selectedCompetition.value),
        categoria_id: Number(selectedCategoryId.value),
        ronda_id: Number(selectedRondaId.value),
      },
    });

    finalesData.value = data;
  } catch (error) {
    finalesData.value = { scope: null, summary: null, rows: [] };
    setNotice(
      "error",
      error?.response?.data?.message || "No se pudo cargar la vista consolidada."
    );
  } finally {
    finalesLoading.value = false;
  }
}

async function loadEvaluaciones() {
  if (Number(selectedCompetition.value) <= 0) {
    evaluacionesData.value = { summary: null, jueces: [], rows: [] };
    return;
  }

  evaluacionesLoading.value = true;

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

    if (selectedEstadoEvaluacion.value) {
      params.estado = selectedEstadoEvaluacion.value;
    }

    if (Number(selectedJuezId.value) > 0) {
      params.juez_user_id = Number(selectedJuezId.value);
    }

    const { data } = await axios.get("/admin/resultados/evaluaciones", { params });
    evaluacionesData.value = data;
    setEvaluacionesNotice("", "");
  } catch (error) {
    evaluacionesData.value = { summary: null, jueces: [], rows: [] };
    setEvaluacionesNotice(
      "error",
      error?.response?.data?.message || "No se pudieron cargar las evaluaciones registradas."
    );
  } finally {
    evaluacionesLoading.value = false;
  }
}

function changeCompetition() {
  const id = Number(selectedCompetition.value);
  if (!Number.isInteger(id) || id <= 0) return;

  setNotice("", "");
  router.get(
    "/admin/resultados",
    { competencia_id: id },
    {
      replace: true,
      preserveScroll: true,
      preserveState: true,
      only: ["competenciaId", "competencias", "categorias", "flash"],
    }
  );
}

async function cambiarEstadoEvaluacion(row, estado) {
  const accion = estado === "anulado" ? "anular" : "restaurar";
  const confirmed = window.confirm(`Seguro que deseas ${accion} la evaluacion de ${row.equipo_nombre}?`);

  if (!confirmed) return;

  evaluacionChangingId.value = row.id;
  setEvaluacionesNotice("", "");

  try {
    await axios.patch(`/admin/resultados/evaluaciones/${row.id}/estado`, {
      estado,
      motivo_cambio: estado === "anulado"
        ? "Anulada desde revision de evaluaciones registradas"
        : "Restaurada desde revision de evaluaciones registradas",
    });

    setEvaluacionesNotice(
      "success",
      estado === "anulado" ? "Evaluacion anulada correctamente." : "Evaluacion restaurada correctamente."
    );

    await loadEvaluaciones();

    if (activeTab.value === "control") {
      await loadFinales();
    }
  } catch (error) {
    const message = Object.values(error?.response?.data?.errors ?? {})[0]?.[0];
    setEvaluacionesNotice(
      "error",
      message || error?.response?.data?.message || "No se pudo cambiar el estado de la evaluacion."
    );
  } finally {
    evaluacionChangingId.value = null;
  }
}

async function consolidarResultados() {
  if (!hasSelectionReady.value) {
    setNotice("warning", "Selecciona competencia, categoría y ronda antes de consolidar.");
    return;
  }

  consolidando.value = true;
  setNotice("", "");

  try {
    const { data } = await axios.post("/admin/resultados/consolidar", {
      competencia_id: Number(selectedCompetition.value),
      categoria_id: Number(selectedCategoryId.value),
      ronda_id: Number(selectedRondaId.value),
    });

    finalesData.value = data;
    setNotice("success", "Clasificaciones consolidadas correctamente en estado borrador.");
  } catch (error) {
    if (error?.response?.status === 422) {
      const message = Object.values(error.response.data?.errors ?? {})[0]?.[0];
      setNotice("warning", message || "No fue posible consolidar con la seleccion actual.");
    } else {
      setNotice(
        "error",
        error?.response?.data?.message || "No se pudo consolidar resultados."
      );
    }
  } finally {
    consolidando.value = false;
  }
}

async function actualizarPublicacion(estado) {
  if (!hasSelectionReady.value) {
    setNotice("warning", "Selecciona competencia, categoría y ronda antes de cambiar el estado.");
    return;
  }

  publicando.value = true;
  setNotice("", "");

  try {
    const { data } = await axios.post("/admin/resultados/publicar", {
      competencia_id: Number(selectedCompetition.value),
      categoria_id: Number(selectedCategoryId.value),
      ronda_id: Number(selectedRondaId.value),
      estado_publicacion: estado,
    });

    finalesData.value = data;
    setNotice(
      "success",
      estado === "visible"
        ? "Clasificaciones publicadas para la vista pública."
        : estado === "cerrado"
          ? "Clasificaciones cerradas correctamente."
          : "Clasificaciones regresadas a borrador."
    );
  } catch (error) {
    if (error?.response?.status === 422) {
      const message = Object.values(error.response.data?.errors ?? {})[0]?.[0];
      setNotice("warning", message || "No fue posible actualizar la publicación.");
    } else {
      setNotice(
        "error",
        error?.response?.data?.message || "No se pudo actualizar el estado de publicación."
      );
    }
  } finally {
    publicando.value = false;
  }
}

function formatUpdatedAt(value) {
  if (!value) return "Sin consolidar";

  return new Date(value).toLocaleString("es-EC", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function formatPublicationAction(action) {
  if (action === "publicar") return "Publicación";
  if (action === "cerrar") return "Cierre";
  return "Paso a borrador";
}

function openPublicView() {
  const params = new URLSearchParams();
  if (Number(selectedCompetition.value) > 0) params.set("competencia_id", String(selectedCompetition.value));
  if (Number(selectedCategoryId.value) > 0) params.set("categoria_id", String(selectedCategoryId.value));
  if (Number(selectedRondaId.value) > 0) params.set("ronda_id", String(selectedRondaId.value));
  window.open(`/resultados?${params.toString()}`, "_blank");
}

function openScopePublicView(scope) {
  const params = new URLSearchParams();
  params.set("competencia_id", String(selectedCompetition.value));
  params.set("categoria_id", String(scope.categoria_id));
  params.set("ronda_id", String(scope.ronda_id));
  window.open(`/resultados?${params.toString()}`, "_blank");
}

function syncMainSelectionToScope(scope) {
  selectedCategoryId.value = scope.categoria_id;
  selectedRondaId.value = scope.ronda_id;
  activeTab.value = "control";
}

watch(
  () => competenciaId.value,
  () => {
    syncSelectionFromProps();
    if (activeTab.value === "registros") {
      loadEvaluaciones();
    }
    if (activeTab.value === "control") {
      loadFinales();
    }
    if (activeTab.value === "publicaciones") {
      loadLiveSnapshot();
      connectLiveStream();
    }
  }
);

watch(
  () => categorias.value,
  () => {
    syncSelectionFromProps();
    if (activeTab.value === "registros") {
      loadEvaluaciones();
    }
    if (activeTab.value === "control") {
      loadFinales();
    }
    if (activeTab.value === "publicaciones") {
      loadLiveSnapshot();
      connectLiveStream();
    }
  },
  { immediate: true }
);

watch(
  () => selectedCategoryId.value,
  () => {
    const firstRondaId = selectedCategory.value?.rondas?.[0]?.id ?? null;
    const rondaStillExists = (selectedCategory.value?.rondas ?? []).some(
      (item) => Number(item.id) === Number(selectedRondaId.value)
    );
    selectedRondaId.value = rondaStillExists ? selectedRondaId.value : firstRondaId;

    if (activeTab.value === "registros") {
      loadEvaluaciones();
    }

    if (activeTab.value === "control") {
      loadFinales();
    }
  }
);

watch(
  () => selectedRondaId.value,
  () => {
    if (activeTab.value === "registros") {
      loadEvaluaciones();
    }
    if (activeTab.value === "control") {
      loadFinales();
    }
  }
);

watch(
  () => [selectedEstadoEvaluacion.value, selectedJuezId.value],
  () => {
    if (activeTab.value === "registros") {
      loadEvaluaciones();
    }
  }
);

watch(
  () => [selectedLiveCategoryId.value, selectedLiveRondaId.value],
  () => {
    if (activeTab.value === "publicaciones") {
      loadLiveSnapshot();
      connectLiveStream();
    }
  }
);

watch(
  () => activeTab.value,
  (value) => {
    if (value === "registros") {
      loadEvaluaciones();
      closeLiveStream();
      return;
    }

    if (value === "control") {
      loadFinales();
      closeLiveStream();
      return;
    }

    if (value === "publicaciones") {
      loadLiveSnapshot();
      connectLiveStream();
      return;
    }

    closeLiveStream();
  }
);

onMounted(() => {
  syncSelectionFromProps();
});

onBeforeUnmount(() => {
  closeLiveStream();
});
</script>

<template>
  <div class="w-full">
    <div class="mx-auto w-full max-w-[1180px] px-4 py-6 sm:px-6 lg:px-4 space-y-6">
      <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold text-slate-900">Resultados de Competencias</h1>
        <p class="text-sm text-slate-500">Registra evaluaciones, consolida clasificaciones y prepara la publicación final.</p>
      </div>

      <div class="inline-flex rounded-2xl border border-slate-200 bg-gray-200 p-1">
        <button
          class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition"
          :class="activeTab === 'control' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'control'"
        >
          <ChartBarSquareIcon class="h-4 w-4" />
          Panel de Control
        </button>

        <button
          class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition"
          :class="activeTab === 'registros' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'registros'"
        >
          <ClipboardDocumentListIcon class="h-4 w-4" />
          Registros de Jueces
        </button>

        <button
          class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition"
          :class="activeTab === 'publicaciones' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'publicaciones'"
        >
          <SignalIcon class="h-4 w-4" />
          Publicaciones en Vivo
        </button>
      </div>

      <div v-if="activeTab === 'registros'" class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="grid grid-cols-1 gap-4 lg:grid-cols-6">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Competencia</label>
              <select
                v-model="selectedCompetition"
                @change="changeCompetition"
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

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Estado</label>
              <select
                v-model="selectedEstadoEvaluacion"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Todos</option>
                <option value="registrado">Registrado</option>
                <option value="publicado">Publicado</option>
                <option value="anulado">Anulado</option>
                <option value="borrador">Borrador</option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Juez</label>
              <select
                v-model="selectedJuezId"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Todos</option>
                <option v-for="juez in evaluacionesData.jueces" :key="juez.id" :value="juez.id">
                  {{ juez.nombre }}
                </option>
              </select>
            </div>

            <div class="flex flex-col justify-end">
              <button
                type="button"
                @click="loadEvaluaciones"
                :disabled="evaluacionesLoading"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 transition hover:bg-slate-50 disabled:opacity-50"
              >
                <ArrowPathIcon class="h-5 w-5 text-slate-700" />
                Recargar
              </button>
            </div>
          </div>
        </div>

        <div
          v-if="evaluacionesNotice.message"
          class="rounded-2xl border px-4 py-3 text-sm"
          :class="{
            'border-emerald-200 bg-emerald-50 text-emerald-700': evaluacionesNotice.type === 'success',
            'border-amber-200 bg-amber-50 text-amber-800': evaluacionesNotice.type === 'warning',
            'border-red-200 bg-red-50 text-red-700': evaluacionesNotice.type === 'error',
          }"
        >
          {{ evaluacionesNotice.message }}
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-4">
          <div class="rounded-3xl border border-blue-200 bg-blue-50 p-5">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-blue-700">Evaluaciones</p>
                <p class="mt-3 text-3xl font-bold text-blue-900">{{ evaluacionesSummary.total }}</p>
              </div>
              <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-100">
                <ClipboardDocumentListIcon class="h-7 w-7 text-blue-700" />
              </div>
            </div>
          </div>

          <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-emerald-700">Registradas</p>
                <p class="mt-3 text-3xl font-bold text-emerald-900">{{ evaluacionesSummary.registradas }}</p>
              </div>
              <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100">
                <CheckCircleIcon class="h-7 w-7 text-emerald-700" />
              </div>
            </div>
          </div>

          <div class="rounded-3xl border border-red-200 bg-red-50 p-5">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-red-700">Anuladas</p>
                <p class="mt-3 text-3xl font-bold text-red-900">{{ evaluacionesSummary.anuladas }}</p>
              </div>
              <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-red-100">
                <NoSymbolIcon class="h-7 w-7 text-red-700" />
              </div>
            </div>
          </div>

          <div class="rounded-3xl border border-slate-200 bg-white p-5">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-slate-700">Equipos / jueces</p>
                <p class="mt-3 text-3xl font-bold text-slate-900">
                  {{ evaluacionesSummary.equipos_count }} / {{ evaluacionesSummary.jueces_count }}
                </p>
              </div>
              <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100">
                <UserGroupIcon class="h-7 w-7 text-slate-700" />
              </div>
            </div>
          </div>
        </div>

        <div
          v-if="evaluacionesLoading"
          class="rounded-2xl border border-slate-200 bg-white px-6 py-10 text-center text-slate-500 shadow-sm"
        >
          Cargando registros de jueces...
        </div>

        <div v-else class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-slate-900">Auditoría de registros</h3>
            <p class="mt-1 text-sm text-slate-500">
              Revisa lo guardado por cada juez y usa las acciones solo para correcciones administrativas.
            </p>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-white">
                <tr class="border-b border-slate-200 text-left text-black">
                  <th class="px-6 py-4 font-medium">Estado</th>
                  <th class="px-6 py-4 font-medium">Equipo</th>
                  <th class="px-6 py-4 font-medium">Juez</th>
                  <th class="px-6 py-4 font-medium">Plantilla</th>
                  <th class="px-6 py-4 font-medium">Resultado</th>
                  <th class="px-6 py-4 font-medium">Detalle</th>
                  <th class="px-6 py-4 font-medium">Fecha</th>
                  <th class="px-6 py-4 font-medium text-right">Acciones</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-slate-200">
                <tr v-for="row in evaluacionesData.rows" :key="row.id" class="hover:bg-slate-50/60">
                  <td class="px-6 py-4 align-top">
                    <span
                      class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1"
                      :class="badgeResultadoEstado(row.estado)"
                    >
                      {{ row.estado }}
                    </span>
                    <p class="mt-2 text-xs text-slate-400">v{{ row.version }}</p>
                  </td>

                  <td class="px-6 py-4 align-top">
                    <p class="font-medium text-slate-900">{{ row.equipo_nombre || "Equipo sin nombre" }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ row.nombre_prototipo || row.institucion || "Sin prototipo" }}</p>
                  </td>

                  <td class="px-6 py-4 align-top">
                    <p class="font-medium text-slate-900">{{ row.juez_nombre || "Juez sin nombre" }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ row.rol_juez || row.juez_email || "Sin rol" }}</p>
                  </td>

                  <td class="px-6 py-4 align-top">
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                      {{ row.mecanismo_nombre }}
                    </span>
                    <p class="mt-2 text-xs text-slate-400">{{ row.mecanismo_codigo || "sin_codigo" }}</p>
                  </td>

                  <td class="px-6 py-4 align-top">
                    <p class="font-semibold text-slate-900">{{ row.resultado_label }}</p>
                    <p v-if="row.observaciones" class="mt-1 max-w-[240px] text-xs text-slate-500">
                      {{ row.observaciones }}
                    </p>
                  </td>

                  <td class="px-6 py-4 align-top">
                    <div v-if="row.payload_resumen?.length" class="flex max-w-[360px] flex-wrap gap-2">
                      <span
                        v-for="item in row.payload_resumen"
                        :key="`${row.id}-${item.key}`"
                        class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                      >
                        {{ item.label }}: {{ item.value }}
                      </span>
                    </div>
                    <span v-else class="text-slate-400">Sin detalle adicional</span>
                  </td>

                  <td class="px-6 py-4 align-top text-slate-700">
                    {{ formatUpdatedAt(row.updated_at) }}
                  </td>

                  <td class="px-6 py-4 align-top">
                    <div class="flex justify-end gap-2">
                      <button
                        v-if="row.estado !== 'anulado'"
                        type="button"
                        @click="cambiarEstadoEvaluacion(row, 'anulado')"
                        :disabled="evaluacionChangingId === row.id"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100 disabled:opacity-50"
                      >
                        <NoSymbolIcon class="h-4 w-4" />
                        Anular
                      </button>

                      <button
                        v-else
                        type="button"
                        @click="cambiarEstadoEvaluacion(row, 'registrado')"
                        :disabled="evaluacionChangingId === row.id"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:opacity-50"
                      >
                        <CheckCircleIcon class="h-4 w-4" />
                        Restaurar
                      </button>
                    </div>
                  </td>
                </tr>

                <tr v-if="!evaluacionesData.rows.length">
                  <td colspan="8" class="px-6 py-10 text-center text-slate-500">
                    No hay registros de jueces para esta seleccion.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div v-else-if="activeTab === 'control'" class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Competencia</label>
              <select
                v-model="selectedCompetition"
                @change="changeCompetition"
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

            <div class="flex flex-col justify-end gap-2 sm:flex-row">
              <button
                type="button"
                @click="loadFinales"
                :disabled="finalesLoading"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 transition hover:bg-slate-50 disabled:opacity-50"
              >
                <ArrowPathIcon class="h-5 w-5 text-slate-700" />
                Recargar
              </button>

              <button
                type="button"
                @click="consolidarResultados"
                :disabled="consolidando || !hasSelectionReady"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-white transition hover:bg-blue-700 disabled:opacity-50"
              >
                <CheckCircleIcon class="h-5 w-5" />
                {{ consolidando ? "Actualizando..." : "Actualizar consolidado" }}
              </button>
            </div>
          </div>
        </div>

        <div
          v-if="finalesNotice.message"
          class="rounded-2xl border px-4 py-3 text-sm"
          :class="{
            'border-emerald-200 bg-emerald-50 text-emerald-700': finalesNotice.type === 'success',
            'border-amber-200 bg-amber-50 text-amber-800': finalesNotice.type === 'warning',
            'border-red-200 bg-red-50 text-red-700': finalesNotice.type === 'error',
          }"
        >
          {{ finalesNotice.message }}
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-4">
          <div class="rounded-3xl border border-blue-200 bg-blue-50 p-5">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-blue-700">Evaluaciones</p>
                <p class="mt-3 text-3xl font-bold text-blue-900">{{ currentSummary.evaluaciones_count }}</p>
              </div>
              <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-100">
                <ClipboardDocumentListIcon class="h-7 w-7 text-blue-700" />
              </div>
            </div>
          </div>

          <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-emerald-700">Equipos evaluados</p>
                <p class="mt-3 text-3xl font-bold text-emerald-900">{{ currentSummary.equipos_evaluados_count }}</p>
              </div>
              <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100">
                <TrophyIcon class="h-7 w-7 text-emerald-700" />
              </div>
            </div>
          </div>

          <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-amber-700">Jueces involucrados</p>
                <p class="mt-3 text-3xl font-bold text-amber-900">{{ currentSummary.jueces_count }}</p>
              </div>
              <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100">
                <UserGroupIcon class="h-7 w-7 text-amber-700" />
              </div>
            </div>
          </div>

          <div class="rounded-3xl border border-slate-200 bg-white p-5">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-slate-700">Estado actual</p>
                <p class="mt-3 text-lg font-bold text-slate-900">{{ currentSummary.estado_publicacion }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ formatUpdatedAt(currentSummary.updated_at) }}</p>
                <p class="mt-1 text-xs text-slate-400">
                  Último evento: {{ formatUpdatedAt(currentSummary.ultimo_evento_publicacion_at) }}
                </p>
              </div>
              <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100">
                <ChartBarSquareIcon class="h-7 w-7 text-slate-700" />
              </div>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h3 class="text-lg font-semibold text-slate-900">
                {{ finalesData.scope?.categoria_nombre || selectedCategory?.nombre || "Categoría" }}
              </h3>
              <p class="mt-1 text-sm text-slate-500">
                {{ selectedCompetitionName }} · {{ finalesData.scope?.ronda_nombre || "Sin ronda" }}
              </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200">
                {{ finalesData.scope?.mecanismo_nombre || selectedCategory?.config_calificacion?.mecanismo_nombre || "Sin mecanismo" }}
              </span>

              <button
                type="button"
                @click="actualizarPublicacion('borrador')"
                :disabled="publicando || !finalesData.rows.length"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 transition hover:bg-slate-50 disabled:opacity-50"
              >
                <ArrowPathIcon class="h-5 w-5 text-slate-700" />
                Reabrir
              </button>

              <button
                type="button"
                @click="actualizarPublicacion('visible')"
                :disabled="publicando || !finalesData.rows.length"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-white transition hover:bg-emerald-700 disabled:opacity-50"
              >
                <CheckCircleIcon class="h-5 w-5" />
                Publicar como admin
              </button>

              <button
                type="button"
                @click="actualizarPublicacion('cerrado')"
                :disabled="publicando || !finalesData.rows.length"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-white transition hover:bg-slate-800 disabled:opacity-50"
              >
                <ClockIcon class="h-5 w-5" />
                Cerrar publicación
              </button>

              <button
                type="button"
                @click="openPublicView"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 transition hover:bg-slate-50"
              >
                <EyeIcon class="h-5 w-5 text-slate-700" />
                Vista Publica
              </button>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-slate-900">Historial de publicación</h3>
            <p class="mt-1 text-sm text-slate-500">
              Auditoría de cambios de estado para la categoría y ronda seleccionadas.
            </p>
          </div>

          <div v-if="publicationHistory.length" class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-white">
                <tr class="border-b border-slate-200 text-left text-black">
                  <th class="px-6 py-4 font-medium">Evento</th>
                  <th class="px-6 py-4 font-medium">Cambio</th>
                  <th class="px-6 py-4 font-medium">Ejecutado por</th>
                  <th class="px-6 py-4 font-medium">Filas</th>
                  <th class="px-6 py-4 font-medium">Fecha</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-slate-200">
                <tr v-for="event in publicationHistory" :key="event.id" class="hover:bg-slate-50/60">
                  <td class="px-6 py-4">
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-800 ring-1 ring-slate-200">
                      {{ formatPublicationAction(event.accion) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 text-slate-700">
                    {{ event.estado_anterior || "sin estado previo" }} -> {{ event.estado_nuevo }}
                  </td>
                  <td class="px-6 py-4 text-slate-700">
                    {{ event.ejecutado_por || "Sistema" }}
                  </td>
                  <td class="px-6 py-4 text-slate-700">
                    {{ event.clasificaciones_count }}
                  </td>
                  <td class="px-6 py-4 text-slate-700">
                    {{ formatUpdatedAt(event.ejecutado_at) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="px-6 py-10 text-center text-slate-500">
            Todavía no hay eventos de publicación o cierre para esta selección.
          </div>
        </div>

        <div
          v-if="finalesLoading"
          class="rounded-2xl border border-slate-200 bg-white px-6 py-10 text-center text-slate-500 shadow-sm"
        >
          Cargando clasificaciones consolidadas...
        </div>

        <div v-else class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-white">
                <tr class="border-b border-slate-200 text-left text-black">
                  <th class="px-6 py-4 font-medium w-[110px]">Posición</th>
                  <th class="px-6 py-4 font-medium">Equipo</th>
                  <th class="px-6 py-4 font-medium">Institución</th>
                  <th class="px-6 py-4 font-medium">Resultado</th>
                  <th class="px-6 py-4 font-medium">Puntaje</th>
                  <th class="px-6 py-4 font-medium">Tiempo</th>
                  <th class="px-6 py-4 font-medium">Version origen</th>
                  <th class="px-6 py-4 font-medium">Estado</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-slate-200">
                <tr v-for="row in finalesData.rows" :key="row.id" class="hover:bg-slate-50/60">
                  <td class="px-6 py-4">
                    <span class="font-semibold text-slate-900">{{ row.posicion }}°</span>
                  </td>

                  <td class="px-6 py-4">
                    <p class="font-medium text-slate-900">{{ row.equipo_nombre }}</p>
                  </td>

                  <td class="px-6 py-4">
                    <span class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-800 ring-1 ring-slate-200">
                      {{ row.institucion || "Sin institución" }}
                    </span>
                  </td>

                  <td class="px-6 py-4">
                    <span class="text-slate-900">{{ row.resultado_label }}</span>
                  </td>

                  <td class="px-6 py-4">
                    <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-200">
                      {{ row.puntaje_total ?? "-" }}
                    </span>
                  </td>

                  <td class="px-6 py-4 text-slate-700">
                    {{ row.tiempo_total ?? "-" }}
                  </td>

                  <td class="px-6 py-4 text-slate-700">
                    v{{ row.origen_version }}
                  </td>

                  <td class="px-6 py-4">
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200">
                      {{ row.estado_publicacion }}
                    </span>
                  </td>
                </tr>

                <tr v-if="!finalesData.rows.length">
                  <td colspan="8" class="px-6 py-10 text-center text-slate-500">
                    No hay clasificaciones consolidadas para esta selección. Puedes consolidar cuando existan evaluaciones registradas.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div v-else class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-4">
          <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex items-center gap-2">
                <SignalIcon class="h-5 w-5 text-slate-700" />
                <div>
                  <h3 class="text-lg font-semibold text-slate-900">Publicaciones en Vivo</h3>
                  <p class="text-sm text-slate-500">El panel admin ve borrador, visible y cerrado. La vista pública solo ve scopes publicados.</p>
                </div>
              </div>

              <div class="flex items-center gap-2">
                <span
                  class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                  :class="liveConnected ? 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-700 ring-1 ring-slate-200'"
                >
                  {{ liveConnected ? "Conexión activa" : "Reconectando" }}
                </span>

                <button
                  type="button"
                  @click="loadLiveSnapshot"
                  class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900 transition hover:bg-slate-50"
                >
                  <ArrowPathIcon class="h-4 w-4" />
                  Snapshot
                </button>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Categoría</label>
                <select
                  v-model="selectedLiveCategoryId"
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
                  v-model="selectedLiveRondaId"
                  class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option :value="null">Todas</option>
                  <option v-for="item in liveRondas" :key="item.id" :value="item.id">
                    {{ item.nombre }}
                  </option>
                </select>
              </div>

              <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estados incluidos</p>
                <p class="mt-1 text-sm font-medium text-slate-900">
                  {{ liveData.meta?.estados_publicacion?.join(", ") || "Sin datos" }}
                </p>
              </div>

              <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Último snapshot</p>
                <p class="mt-1 text-sm font-medium text-slate-900">
                  {{ liveData.generated_at ? formatUpdatedAt(liveData.generated_at) : "Sin datos" }}
                </p>
              </div>
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
          Cargando panel en vivo...
        </div>

        <div v-else class="grid grid-cols-1 gap-6">
          <div
            v-for="scope in liveScopesFiltered"
            :key="scope.key"
            class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden"
          >
            <div class="bg-gradient-to-r from-blue-600 to-cyan-600 p-4 sm:p-5">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <p class="font-semibold text-white">{{ scope.categoria_nombre }}</p>
                  <p class="mt-1 text-sm text-blue-100">{{ scope.ronda_nombre }} · {{ scope.mecanismo_nombre }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                  <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold" :class="badgeLive(scope.estado_publicacion)">
                    {{ scope.estado_publicacion }}
                  </span>

                  <span class="inline-flex items-center rounded-xl bg-white/15 px-3 py-1.5 text-xs font-semibold text-white">
                    {{ scope.es_oficial ? "Publicado" : "Solo admin" }}
                  </span>
                </div>
              </div>
            </div>

            <div class="space-y-4 p-5 sm:p-6">
              <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                  <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Última consolidación</p>
                  <p class="mt-1 text-sm font-medium text-slate-900">
                    {{ scope.ultima_consolidacion_at ? formatUpdatedAt(scope.ultima_consolidacion_at) : "Sin consolidar" }}
                  </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                  <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Última publicación</p>
                  <p class="mt-1 text-sm font-medium text-slate-900">
                    {{ scope.ultima_publicacion_at ? formatUpdatedAt(scope.ultima_publicacion_at) : "No publicado" }}
                  </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                  <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Filas visibles</p>
                  <p class="mt-1 text-sm font-medium text-slate-900">{{ scope.rows.length }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                  <button
                    type="button"
                    @click="syncMainSelectionToScope(scope)"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900 transition hover:bg-slate-50"
                  >
                    <PlayIcon class="h-4 w-4" />
                    Ir al panel
                  </button>

                  <button
                    type="button"
                    @click="openScopePublicView(scope)"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900 transition hover:bg-slate-50"
                  >
                    <EyeIcon class="h-4 w-4" />
                    Ver público
                  </button>
                </div>
              </div>

              <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                  <thead>
                    <tr class="border-b border-slate-200 text-left text-slate-600">
                      <th class="py-3 pr-4 font-medium">Pos.</th>
                      <th class="py-3 pr-4 font-medium">Equipo</th>
                      <th class="py-3 pr-4 font-medium">Institución</th>
                      <th class="py-3 pr-4 font-medium">Resultado</th>
                      <th class="py-3 pr-0 font-medium">Puntaje</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-200">
                    <tr v-for="row in scope.rows" :key="`${scope.key}-${row.posicion}-${row.equipo_nombre}`">
                      <td class="py-3 pr-4 font-semibold text-slate-900">{{ row.posicion }}°</td>
                      <td class="py-3 pr-4">
                        <p class="font-medium text-slate-900">{{ row.equipo_nombre }}</p>
                      </td>
                      <td class="py-3 pr-4 text-slate-600">{{ row.institucion || "Sin institución" }}</td>
                      <td class="py-3 pr-4 text-slate-900">{{ row.resultado_label }}</td>
                      <td class="py-3 pr-0 text-slate-700">{{ row.puntaje_total ?? "-" }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div v-if="!liveScopesFiltered.length" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-500 shadow-sm">
            No hay clasificaciones en vivo para la competencia o filtros seleccionados.
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
