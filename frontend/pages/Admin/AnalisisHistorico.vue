<script setup>
import axios from "axios";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { computed, ref } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import {
  ArrowDownTrayIcon,
  ArrowPathIcon,
  ArrowTrendingUpIcon,
  BuildingLibraryIcon,
  ChartBarIcon,
  CheckCircleIcon,
  ClipboardDocumentCheckIcon,
  ExclamationTriangleIcon,
  PresentationChartLineIcon,
  SparklesIcon,
  TrophyIcon,
  UserGroupIcon,
} from "@heroicons/vue/24/outline";
import { formatEcuadorMediumDate, formatEcuadorMediumDateTime } from "@/lib/datetime";

defineOptions({ layout: AdminLayout });

const page = usePage();

const temporadas = computed(() => page.props.temporadas ?? []);
const competencias = computed(() => page.props.competencias ?? []);
const cierreTemporada = computed(() => page.props.cierreTemporada ?? null);
const cierreCompetencia = computed(() => page.props.cierreCompetencia ?? null);
const preliminarTemporada = computed(() => page.props.preliminarTemporada ?? null);
const preliminarCompetencia = computed(() => page.props.preliminarCompetencia ?? null);
const temporadaId = computed(() => page.props.temporadaId ?? null);
const competenciaId = computed(() => page.props.competenciaId ?? null);

const selectedTemporada = ref(temporadaId.value ?? "");
const selectedCompetencia = ref(competenciaId.value ?? "");
const vista = ref(competenciaId.value ? "competencia" : "temporada");
const activeTab = ref("resumen");
const generating = ref(false);
const notice = ref({ type: "", message: "" });

const tabs = [
  { id: "resumen", label: "Resumen" },
  { id: "instituciones", label: "Instituciones" },
  { id: "categorias", label: "Categorías" },
  { id: "tendencias", label: "Tendencias" },
  { id: "estado", label: "Estado del cierre" },
];

const selectedTemporadaData = computed(() => {
  return temporadas.value.find((item) => Number(item.id) === Number(selectedTemporada.value)) ?? null;
});

const selectedCompetenciaData = computed(() => {
  return competencias.value.find((item) => Number(item.id) === Number(selectedCompetencia.value)) ?? null;
});

const activeCierre = computed(() => {
  return vista.value === "competencia"
    ? (cierreCompetencia.value ?? preliminarCompetencia.value)
    : (cierreTemporada.value ?? preliminarTemporada.value);
});

const hasOfficialCierre = computed(() => {
  return vista.value === "competencia" ? Boolean(cierreCompetencia.value) : Boolean(cierreTemporada.value);
});

const metrics = computed(() => activeCierre.value?.metricas ?? {});
const resumen = computed(() => metrics.value.resumen_anual ?? {});
const instituciones = computed(() => metrics.value.rendimiento_instituciones ?? []);
const categorias = computed(() => metrics.value.distribucion_categorias ?? []);
const indicadores = computed(() => metrics.value.indicadores_categorias ?? []);
const comparativo = computed(() => metrics.value.comparativo_anual ?? []);
const proyeccion = computed(() => metrics.value.proyeccion ?? null);
const observaciones = computed(() => metrics.value.observaciones ?? []);
const progresoCierre = computed(() => resumen.value.progreso_cierre ?? {
  categorias_evaluables: 0,
  categorias_cerradas: 0,
  categorias_pendientes: 0,
  porcentaje: 0,
  pendientes: [],
});

const temporadaListaParaCierre = computed(() => Boolean(selectedTemporadaData.value?.cerrable));
const competenciaListaParaCierre = computed(() => Boolean(selectedCompetenciaData.value?.cerrable));

const canGenerate = computed(() => {
  if (generating.value || !selectedTemporada.value) return false;
  if (vista.value === "competencia") {
    return Number(selectedCompetencia.value) > 0 && competenciaListaParaCierre.value;
  }

  return temporadaListaParaCierre.value;
});

const statusInfo = computed(() => {
  const item = vista.value === "competencia" ? selectedCompetenciaData.value : selectedTemporadaData.value;

  if (hasOfficialCierre.value) {
    return {
      label: "Cierre oficial",
      detail: `Generado el ${formatDate(activeCierre.value?.cerrado_at || activeCierre.value?.generado_at)}`,
      class: "bg-emerald-50 text-emerald-700 ring-emerald-200",
    };
  }

  if (activeCierre.value) {
    return {
      label: "Vista preliminar",
      detail: "Puedes revisar el avance; el cierre oficial requiere todas las categorías finalizadas.",
      class: "bg-amber-50 text-amber-700 ring-amber-200",
    };
  }

  if (item?.cerrable) {
    return {
      label: "Listo para cerrar",
      detail: "La fecha de finalización ya concluyó.",
      class: "bg-blue-50 text-blue-700 ring-blue-200",
    };
  }

  return {
    label: "Pendiente",
    detail: "El análisis aparecerá cuando existan datos en la temporada.",
    class: "bg-amber-50 text-amber-700 ring-amber-200",
  };
});

const kpis = computed(() => [
  {
    label: "Participantes",
    value: activeCierre.value?.total_participantes ?? 0,
    icon: UserGroupIcon,
    tone: "bg-blue-50 text-blue-700",
    delta: activeCierre.value?.tasa_crecimiento_participantes,
  },
  {
    label: "Equipos",
    value: activeCierre.value?.total_equipos ?? 0,
    icon: TrophyIcon,
    tone: "bg-violet-50 text-violet-700",
    delta: activeCierre.value?.tasa_crecimiento_equipos,
  },
  {
    label: "Instituciones",
    value: activeCierre.value?.total_instituciones ?? 0,
    icon: BuildingLibraryIcon,
    tone: "bg-emerald-50 text-emerald-700",
    delta: activeCierre.value?.tasa_crecimiento_instituciones,
  },
  {
    label: "Inscripciones aprobadas",
    value: activeCierre.value?.total_inscripciones_aprobadas ?? 0,
    icon: ClipboardDocumentCheckIcon,
    tone: "bg-amber-50 text-amber-700",
    delta: null,
  },
]);

const cierreStats = computed(() => [
  {
    label: "Categorías evaluables",
    value: progresoCierre.value.categorias_evaluables ?? 0,
    tone: "bg-slate-50 text-slate-700",
  },
  {
    label: "Categorías cerradas",
    value: progresoCierre.value.categorias_cerradas ?? 0,
    tone: "bg-emerald-50 text-emerald-700",
  },
  {
    label: "Categorías pendientes",
    value: progresoCierre.value.categorias_pendientes ?? 0,
    tone: "bg-amber-50 text-amber-700",
  },
]);

const maxInstitucionScore = computed(() => {
  return Math.max(...instituciones.value.map((item) => Number(item.puntaje_ponderado ?? 0)), 1);
});

const maxComparativoParticipantes = computed(() => {
  return Math.max(...comparativo.value.map((item) => Number(item.participantes ?? 0)), 1);
});

const categoriaPrincipal = computed(() => categorias.value[0] ?? null);
const institucionPrincipal = computed(() => instituciones.value[0] ?? null);

function changeTemporada() {
  selectedCompetencia.value = "";
  vista.value = "temporada";
  activeTab.value = "resumen";
  router.get(
    "/admin/analisis-historico",
    { temporada_id: selectedTemporada.value },
    { preserveScroll: true, preserveState: false, replace: true }
  );
}

function changeCompetencia() {
  vista.value = selectedCompetencia.value ? "competencia" : "temporada";
  activeTab.value = "resumen";
  router.get(
    "/admin/analisis-historico",
    {
      temporada_id: selectedTemporada.value,
      competencia_id: selectedCompetencia.value || undefined,
    },
    { preserveScroll: true, preserveState: false, replace: true }
  );
}

function setVista(value) {
  activeTab.value = "resumen";
  vista.value = value;
  if (value === "temporada") {
    selectedCompetencia.value = "";
    changeCompetencia();
  } else if (!selectedCompetencia.value && competencias.value.length) {
    selectedCompetencia.value = competencias.value[0].id;
    changeCompetencia();
  }
}

async function generarCierre() {
  if (!canGenerate.value) return;

  generating.value = true;
  notice.value = { type: "", message: "" };

  try {
    const { data } = await axios.post("/admin/analisis-historico/generar", {
      tipo_cierre: vista.value,
      temporada_id: Number(selectedTemporada.value),
      competencia_id: vista.value === "competencia" ? Number(selectedCompetencia.value) : null,
    });

    notice.value = { type: "success", message: data.message };
    router.reload({
      only: ["temporadas", "competencias", "cierreTemporada", "cierreCompetencia", "preliminarTemporada", "preliminarCompetencia"],
      preserveScroll: true,
    });
  } catch (error) {
    const errors = error?.response?.data?.errors ?? {};
    const message = Object.values(errors)[0]?.[0];
    notice.value = {
      type: "error",
      message: message || error?.response?.data?.message || "No se pudo generar el cierre.",
    };
  } finally {
    generating.value = false;
  }
}

function exportJSON() {
  downloadBlob(
    `analisis_historico_${vista.value}_${resumen.value.anio ?? "sin_anio"}.json`,
    "application/json;charset=utf-8",
    JSON.stringify(activeCierre.value ?? {}, null, 2)
  );
}

function exportCSV() {
  const rows = [
    ["Métrica", "Valor"],
    ["Participantes", activeCierre.value?.total_participantes ?? 0],
    ["Equipos", activeCierre.value?.total_equipos ?? 0],
    ["Instituciones", activeCierre.value?.total_instituciones ?? 0],
    ["Inscripciones aprobadas", activeCierre.value?.total_inscripciones_aprobadas ?? 0],
    ["Categorías", activeCierre.value?.total_categorias ?? 0],
    ["Competencias", activeCierre.value?.total_competencias ?? 0],
  ];

  downloadBlob(
    `analisis_historico_${vista.value}_${resumen.value.anio ?? "sin_anio"}.csv`,
    "text/csv;charset=utf-8",
    rows.map((row) => row.join(",")).join("\n")
  );
}

function downloadBlob(filename, mime, content) {
  const blob = new Blob([content], { type: mime });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

function formatNumber(value) {
  return new Intl.NumberFormat("es-EC").format(Number(value ?? 0));
}

function formatPercent(value) {
  if (value === null || value === undefined) return "Sin histórico previo";
  const number = Number(value);
  const sign = number > 0 ? "+" : "";
  return `${sign}${number.toFixed(1)}% vs año anterior`;
}

function formatDate(value) {
  return formatEcuadorMediumDateTime(value, "sin fecha");
}

function formatShortDate(value) {
  return formatEcuadorMediumDate(value, "-");
}

function formatMetric(value, suffix = "") {
  if (value === null || value === undefined) return "-";
  return `${Number(value).toFixed(2)}${suffix}`;
}
</script>

<template>
  <div class="mx-auto w-full max-w-[1180px] space-y-5 px-3 py-5 sm:space-y-6 sm:px-6 sm:py-6 lg:px-4">
    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-slate-950 shadow-sm">
      <div class="grid gap-6 p-4 text-white sm:p-6 lg:grid-cols-[1fr_360px] lg:p-7">
        <div>
          <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-blue-100 ring-1 ring-white/15">
            <PresentationChartLineIcon class="h-4 w-4" />
            Panel histórico oficial
          </div>
          <h1 class="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">Análisis Histórico</h1>
          <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
            Consulta cierres anuales por temporada y revisa el detalle de cada competencia con métricas consolidadas para administración.
          </p>

          <div class="mt-5 flex flex-wrap items-center gap-2">
            <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold ring-1" :class="statusInfo.class">
              {{ statusInfo.label }}
            </span>
            <span class="text-xs text-slate-300">{{ statusInfo.detail }}</span>
          </div>
        </div>

        <div class="rounded-2xl bg-white p-4 text-slate-900 shadow-xl">
          <div class="grid gap-3">
            <div>
              <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Temporada</label>
              <select
                v-model="selectedTemporada"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                @change="changeTemporada"
              >
                <option v-for="temporada in temporadas" :key="temporada.id" :value="temporada.id">
                  {{ temporada.nombre }} - {{ temporada.anio }}
                </option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Detalle por competencia</label>
              <select
                v-model="selectedCompetencia"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                @change="changeCompetencia"
              >
                <option value="">Ver temporada completa</option>
                <option v-for="competencia in competencias" :key="competencia.id" :value="competencia.id">
                  {{ competencia.nombre }}
                </option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-2">
              <button
                type="button"
                class="rounded-xl px-3 py-2 text-sm font-bold transition"
                :class="vista === 'temporada' ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                @click="setVista('temporada')"
              >
                Temporada
              </button>
              <button
                type="button"
                class="rounded-xl px-3 py-2 text-sm font-bold transition disabled:cursor-not-allowed disabled:opacity-50"
                :class="vista === 'competencia' ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                :disabled="!competencias.length"
                @click="setVista('competencia')"
              >
                Competencia
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="notice.message"
      class="flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm"
      :class="notice.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'"
    >
      <CheckCircleIcon class="mt-0.5 h-5 w-5 shrink-0" />
      <span>{{ notice.message }}</span>
    </div>

    <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:flex-row lg:items-center lg:justify-between">
      <div>
        <p class="text-sm font-semibold text-slate-900">
          {{ vista === "competencia" ? selectedCompetenciaData?.nombre : selectedTemporadaData?.nombre }}
        </p>
        <p class="mt-1 text-sm text-slate-500">
          {{ activeCierre ? `Periodo analizado: ${formatShortDate(activeCierre.fecha_inicio)} - ${formatShortDate(activeCierre.fecha_fin)}` : "Aún no existe información para esta selección." }}
        </p>
      </div>

      <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
        <button
          type="button"
          class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
          :disabled="!canGenerate"
          @click="generarCierre"
        >
          <ArrowPathIcon class="h-5 w-5" :class="{ 'animate-spin': generating }" />
          {{ hasOfficialCierre ? "Actualizar cierre" : "Generar cierre oficial" }}
        </button>
        <button
          type="button"
          class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50 sm:w-auto"
          :disabled="!activeCierre"
          @click="exportCSV"
        >
          <ArrowDownTrayIcon class="h-5 w-5" />
          CSV
        </button>
        <button
          type="button"
          class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50 sm:w-auto"
          :disabled="!activeCierre"
          @click="exportJSON"
        >
          <ArrowDownTrayIcon class="h-5 w-5" />
          JSON
        </button>
      </div>
    </div>

    <div v-if="!activeCierre" class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm">
      <SparklesIcon class="mx-auto h-12 w-12 text-slate-300" />
      <h2 class="mt-4 text-xl font-bold text-slate-900">Sin análisis disponible</h2>
      <p class="mx-auto mt-2 max-w-2xl text-sm leading-6 text-slate-500">
        Asigna competencias a una temporada y registra resultados para que el sistema pueda construir el análisis histórico.
      </p>
    </div>

    <template v-else>
      <div class="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            class="rounded-xl px-3 py-2.5 text-sm font-bold transition"
            :class="activeTab === tab.id ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            @click="activeTab = tab.id"
          >
            {{ tab.label }}
          </button>
        </div>
      </div>

      <section v-if="activeTab === 'resumen'" class="space-y-5">
        <div
          v-if="!hasOfficialCierre"
          class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900 shadow-sm"
        >
          <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <p class="font-bold">Vista preliminar del análisis</p>
              <p class="mt-1 text-sm leading-6">
                {{ progresoCierre.categorias_cerradas }} de {{ progresoCierre.categorias_evaluables }} categorías evaluables están finalizadas.
                El cierre oficial se habilita cuando no existan categorías pendientes.
              </p>
            </div>
            <div class="min-w-[220px]">
              <div class="mb-1 flex justify-between text-xs font-bold">
                <span>Progreso</span>
                <span>{{ progresoCierre.porcentaje }}%</span>
              </div>
              <div class="h-3 overflow-hidden rounded-full bg-white/80">
                <div class="h-full rounded-full bg-amber-500" :style="{ width: `${progresoCierre.porcentaje}%` }"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="grid gap-3 sm:gap-4 md:grid-cols-2 xl:grid-cols-4">
          <div v-for="kpi in kpis" :key="kpi.label" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-start justify-between gap-3">
              <div class="rounded-xl p-3" :class="kpi.tone">
                <component :is="kpi.icon" class="h-6 w-6" />
              </div>
              <ArrowTrendingUpIcon v-if="kpi.delta !== null && kpi.delta !== undefined" class="h-5 w-5 text-emerald-600" />
            </div>
            <p class="mt-4 text-3xl font-bold text-slate-900">{{ formatNumber(kpi.value) }}</p>
            <p class="mt-1 text-sm font-medium text-slate-500">{{ kpi.label }}</p>
            <p class="mt-2 text-xs font-semibold" :class="kpi.delta === null || kpi.delta === undefined ? 'text-slate-400' : 'text-emerald-700'">
              {{ formatPercent(kpi.delta) }}
            </p>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[1fr_360px]">
          <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <h2 class="text-lg font-bold text-slate-900">Lectura rápida</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
              <div v-for="item in observaciones" :key="item" class="rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-600">
                {{ item }}
              </div>
              <div v-if="!observaciones.length" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">
                Aún no hay observaciones automáticas para esta selección.
              </div>
            </div>
          </section>

          <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <h2 class="text-lg font-bold text-slate-900">Estado general</h2>
            <div class="mt-4 space-y-3">
              <div v-for="stat in cierreStats" :key="stat.label" class="flex items-center justify-between rounded-xl p-4" :class="stat.tone">
                <span class="text-sm font-semibold">{{ stat.label }}</span>
                <span class="text-2xl font-black">{{ formatNumber(stat.value) }}</span>
              </div>
            </div>
          </section>
        </div>
      </section>

      <section v-else-if="activeTab === 'instituciones'" class="grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h2 class="text-lg font-bold text-slate-900">Rendimiento por institución</h2>
              <p class="text-sm text-slate-500">Ranking ponderado: 1.º lugar = 3 pts, 2.º = 2 pts, 3.º = 1 pt.</p>
            </div>
            <ChartBarIcon class="h-6 w-6 text-blue-600" />
          </div>

          <div class="mt-5 space-y-4">
            <div v-for="(item, index) in instituciones" :key="item.institucion">
              <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                <span class="font-semibold text-slate-800">{{ index + 1 }}. {{ item.institucion }}</span>
                <span class="font-bold text-slate-900">{{ item.puntaje_ponderado }} pts</span>
              </div>
              <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                <div
                  class="h-full rounded-full bg-gradient-to-r from-blue-600 to-emerald-500"
                  :style="{ width: `${Math.max(6, (Number(item.puntaje_ponderado) / maxInstitucionScore) * 100)}%` }"
                ></div>
              </div>
              <p class="mt-1 text-xs text-slate-500">
                {{ item.primeros }} primeros, {{ item.segundos }} segundos, {{ item.terceros }} terceros
              </p>
            </div>
            <p v-if="!instituciones.length" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">
              No existen instituciones con podios registrados.
            </p>
          </div>
        </section>

        <aside class="space-y-4">
          <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Institución líder</h2>
            <div v-if="institucionPrincipal" class="mt-4 rounded-xl bg-blue-50 p-5">
              <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Mejor posicionada</p>
              <p class="mt-2 text-2xl font-black text-slate-900">{{ institucionPrincipal.institucion }}</p>
              <p class="mt-2 text-sm text-slate-600">
                {{ institucionPrincipal.total_podios }} podios y {{ institucionPrincipal.equipos }} equipos.
              </p>
            </div>
          </section>

          <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Top 5</h2>
            <div class="mt-4 space-y-3">
              <div
                v-for="(item, index) in instituciones.slice(0, 5)"
                :key="item.institucion"
                class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-4"
              >
                <div class="flex items-center gap-3">
                  <div
                    class="flex h-9 w-9 items-center justify-center rounded-full text-sm font-black"
                    :class="index === 0 ? 'bg-amber-100 text-amber-700' : index === 1 ? 'bg-slate-200 text-slate-700' : index === 2 ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700'"
                  >
                    {{ index + 1 }}
                  </div>
                  <div>
                    <p class="font-bold text-slate-900">{{ item.institucion }}</p>
                    <p class="text-xs text-slate-500">{{ item.total_podios }} podios</p>
                  </div>
                </div>
                <p class="text-sm font-bold text-blue-700">{{ item.equipos }} equipos</p>
              </div>
            </div>
          </section>
        </aside>
      </section>

      <section v-else-if="activeTab === 'categorias'" class="grid gap-4 lg:grid-cols-[0.9fr_1.1fr]">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-lg font-bold text-slate-900">Participación por categorías</h2>
          <div v-if="categoriaPrincipal" class="mt-4 rounded-xl bg-blue-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Categoría con más inscripciones</p>
            <p class="mt-2 text-xl font-black text-slate-900">{{ categoriaPrincipal.nombre }}</p>
            <p class="mt-1 text-sm text-slate-600">
              {{ formatNumber(categoriaPrincipal.inscripciones) }} inscripciones aprobadas, {{ categoriaPrincipal.porcentaje }}% del total.
            </p>
          </div>

          <div class="mt-5 space-y-3">
            <div v-for="categoria in categorias" :key="categoria.categoria_id" class="rounded-xl border border-slate-200 p-4">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <p class="font-bold text-slate-900">{{ categoria.nombre }}</p>
                  <p class="text-xs text-slate-500">{{ formatNumber(categoria.inscripciones) }} inscripciones aprobadas</p>
                </div>
                <p class="text-sm font-black text-blue-700">{{ categoria.porcentaje }}%</p>
              </div>
              <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-blue-600" :style="{ width: `${categoria.porcentaje}%` }"></div>
              </div>
            </div>
          </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-lg font-bold text-slate-900">Indicadores técnicos por categoría</h2>
          <p class="mt-1 text-sm text-slate-500">Resultados registrados, mejor tiempo y mejor puntaje según el mecanismo de calificación.</p>
          <div class="mt-5 overflow-x-auto">
            <table class="min-w-[680px] w-full text-sm">
              <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                <tr>
                  <th class="px-4 py-3">Categoría</th>
                  <th class="px-4 py-3">Resultados</th>
                  <th class="px-4 py-3">Mejor tiempo</th>
                  <th class="px-4 py-3">Mejor puntaje</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="item in indicadores" :key="item.categoria_id">
                  <td class="px-4 py-3 font-semibold text-slate-900">{{ item.nombre }}</td>
                  <td class="px-4 py-3 text-slate-600">{{ item.resultados_registrados }}</td>
                  <td class="px-4 py-3 text-slate-600">{{ formatMetric(item.mejor_tiempo, " s") }}</td>
                  <td class="px-4 py-3 text-slate-600">{{ formatMetric(item.mejor_puntaje, " pts") }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </section>

      <section v-else-if="activeTab === 'tendencias'" class="grid gap-4 lg:grid-cols-[1fr_360px]">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <h2 class="text-lg font-bold text-slate-900">Comparativo anual</h2>
          <p class="mt-1 text-sm text-slate-500">Evolución de participantes por temporada cerrada.</p>
          <div v-if="comparativo.length" class="mt-5 flex h-72 items-end gap-3 overflow-x-auto border-b border-slate-200 pb-3">
            <div v-for="item in comparativo" :key="item.temporada_id" class="flex min-w-[72px] flex-1 flex-col items-center justify-end gap-2">
              <div class="flex w-full items-end justify-center">
                <div
                  class="w-10 rounded-t-xl bg-blue-600"
                  :style="{ height: `${Math.max(12, (Number(item.participantes) / maxComparativoParticipantes) * 220)}px` }"
                  :title="`${item.participantes} participantes`"
                ></div>
              </div>
              <p class="text-xs font-bold text-slate-700">{{ item.anio }}</p>
              <p class="text-xs text-slate-500">{{ formatNumber(item.participantes) }}</p>
            </div>
          </div>
          <p v-else class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-500">
            El comparativo anual estará disponible cuando existan cierres oficiales de temporada.
          </p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900">
            <SparklesIcon class="h-5 w-5 text-blue-600" />
            Proyección
          </h2>
          <div v-if="proyeccion" class="mt-4 space-y-3">
            <div class="rounded-xl bg-blue-50 p-4">
              <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Participantes {{ proyeccion.anio }}</p>
              <p class="mt-1 text-3xl font-black text-blue-800">{{ formatNumber(proyeccion.participantes_estimados) }}</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-xs text-slate-500">Equipos</p>
                <p class="text-xl font-bold text-slate-900">{{ formatNumber(proyeccion.equipos_estimados) }}</p>
              </div>
              <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-xs text-slate-500">Instituciones</p>
                <p class="text-xl font-bold text-slate-900">{{ formatNumber(proyeccion.instituciones_estimadas) }}</p>
              </div>
            </div>
            <p class="text-xs leading-5 text-slate-500">{{ proyeccion.metodo }}</p>
          </div>
          <p v-else class="mt-4 text-sm text-slate-500">Disponible solo para cierres de temporada con histórico previo.</p>
        </section>
      </section>

      <section v-else class="grid gap-4 lg:grid-cols-[0.9fr_1.1fr]">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <h2 class="text-lg font-bold text-slate-900">Regla del cierre oficial</h2>
          <p class="mt-2 text-sm leading-6 text-slate-500">
            El cierre se habilita cuando todas las categorías evaluables tienen sus rondas cerradas. Esta regla evita consolidar resultados incompletos.
          </p>

          <div class="mt-5">
            <div class="mb-2 flex justify-between text-xs font-bold text-slate-600">
              <span>Avance del cierre</span>
              <span>{{ progresoCierre.porcentaje }}%</span>
            </div>
            <div class="h-4 overflow-hidden rounded-full bg-slate-100">
              <div
                class="h-full rounded-full"
                :class="progresoCierre.categorias_pendientes ? 'bg-amber-500' : 'bg-emerald-500'"
                :style="{ width: `${progresoCierre.porcentaje}%` }"
              ></div>
            </div>
          </div>

          <div class="mt-5 grid gap-3">
            <div v-for="stat in cierreStats" :key="stat.label" class="flex items-center justify-between rounded-xl p-4" :class="stat.tone">
              <span class="text-sm font-semibold">{{ stat.label }}</span>
              <span class="text-2xl font-black">{{ formatNumber(stat.value) }}</span>
            </div>
          </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-lg font-bold text-slate-900">Categorías pendientes</h2>
          <div v-if="progresoCierre.pendientes?.length" class="mt-4 space-y-3">
            <div
              v-for="categoria in progresoCierre.pendientes"
              :key="categoria.categoria_id"
              class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900"
            >
              <div class="flex items-start gap-3">
                <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0" />
                <div>
                  <p class="font-bold">{{ categoria.nombre }}</p>
                  <p class="mt-1 text-sm">
                    {{
                      categoria.rondas_pendientes?.length
                        ? `Rondas pendientes: ${categoria.rondas_pendientes.map((ronda) => ronda.nombre).join(", ")}.`
                        : "Tiene resultados o rondas pendientes por cerrar."
                    }}
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
            <div class="flex items-start gap-3">
              <CheckCircleIcon class="mt-0.5 h-5 w-5 shrink-0" />
              <div>
                <p class="font-bold">Todo listo para el cierre</p>
                <p class="mt-1 text-sm">No existen categorías pendientes para esta selección.</p>
              </div>
            </div>
          </div>
        </section>
      </section>
    </template>
  </div>
</template>
