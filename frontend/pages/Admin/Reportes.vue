<script setup>
import axios from "axios";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { computed, ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import {
  ArrowDownTrayIcon,
  ArrowPathIcon,
  CheckCircleIcon,
  CloudArrowUpIcon,
  DocumentArrowDownIcon,
  DocumentTextIcon,
  ExclamationTriangleIcon,
  PrinterIcon,
  XMarkIcon,
} from "@heroicons/vue/24/outline";

defineOptions({ layout: AdminLayout });

const page = usePage();

const competenciaId = computed(() => page.props.competenciaId ?? null);
const competencias = computed(() => page.props.competencias ?? []);
const categorias = computed(() => page.props.categorias ?? []);
const tiposReporte = computed(() => page.props.tiposReporte ?? []);

const selectedCompetition = ref(competenciaId.value ?? "");
const selectedCategoryId = ref(categorias.value[0]?.id ?? "");
const selectedRondaId = ref("");
const selectedTipoReporte = ref("inscritos");
const observaciones = ref("");
const reportes = ref(page.props.reportes ?? []);
const loading = ref(false);
const generating = ref(false);
const notice = ref({ type: "", message: "" });
let noticeTimer = null;
const uploadState = ref({
  reporteId: null,
  file: null,
  saving: false,
});

const selectedCategory = computed(() => {
  return categorias.value.find((item) => Number(item.id) === Number(selectedCategoryId.value)) ?? null;
});

const rondasDisponibles = computed(() => selectedCategory.value?.rondas ?? []);

const requiereRonda = computed(() => false);

const puedeGenerar = computed(() => {
  if (Number(selectedCompetition.value) <= 0 || Number(selectedCategoryId.value) <= 0) {
    return false;
  }

  return Boolean(selectedTipoReporte.value);
});

const summary = computed(() => {
  return {
    total: reportes.value.length,
    firmados: reportes.value.filter((item) => item.estado === "firmado").length,
    pendientes: reportes.value.filter((item) => item.estado === "generado").length,
  };
});

watch(categorias, () => {
  const exists = categorias.value.some((item) => Number(item.id) === Number(selectedCategoryId.value));
  selectedCategoryId.value = exists ? selectedCategoryId.value : (categorias.value[0]?.id ?? "");
});

function setNotice(type, message) {
  if (noticeTimer) {
    clearTimeout(noticeTimer);
    noticeTimer = null;
  }

  notice.value = { type, message };

  if (message) {
    noticeTimer = window.setTimeout(() => {
      notice.value = { type: "", message: "" };
      noticeTimer = null;
    }, 4500);
  }
}

function changeCompetition() {
  const id = Number(selectedCompetition.value);
  if (!Number.isInteger(id) || id <= 0) return;

  setNotice("", "");
  router.get(
    "/admin/reportes",
    { competencia_id: id },
    {
      replace: true,
      preserveScroll: true,
      preserveState: false,
    }
  );
}

async function refreshReportes() {
  if (Number(selectedCompetition.value) <= 0) {
    reportes.value = [];
    return;
  }

  loading.value = true;

  try {
    const { data } = await axios.get("/admin/reportes/listado", {
      params: { competencia_id: Number(selectedCompetition.value) },
    });
    reportes.value = data.reportes ?? [];
  } catch (error) {
    setNotice("error", error?.response?.data?.message || "No se pudo actualizar la lista de reportes.");
  } finally {
    loading.value = false;
  }
}

async function generarReporte() {
  if (!puedeGenerar.value) {
    setNotice("warning", "Completa la selección antes de generar el reporte.");
    return;
  }

  generating.value = true;
  setNotice("", "");

  try {
    await axios.post("/admin/reportes", {
      competencia_id: Number(selectedCompetition.value),
      categoria_id: Number(selectedCategoryId.value),
      tipo_reporte: selectedTipoReporte.value,
    });

    await refreshReportes();
    setNotice("success", "Reporte generado correctamente. Ya puedes descargarlo o imprimirlo.");
  } catch (error) {
    const errors = error?.response?.data?.errors ?? {};
    const message = Object.values(errors)[0]?.[0];
    setNotice("error", message || error?.response?.data?.message || "No se pudo generar el reporte.");
  } finally {
    generating.value = false;
  }
}

function onFileChange(event, reporte) {
  uploadState.value = {
    reporteId: reporte.id,
    file: event.target.files?.[0] ?? null,
    saving: false,
  };
}

async function subirFirmado(reporte) {
  if (!uploadState.value.file || Number(uploadState.value.reporteId) !== Number(reporte.id)) {
    setNotice("warning", "Selecciona el PDF firmado antes de cargarlo.");
    return;
  }

  uploadState.value.saving = true;
  setNotice("", "");

  const formData = new FormData();
  formData.append("archivo_firmado", uploadState.value.file);

  try {
    await axios.post(`/admin/reportes/${reporte.id}/firmado`, formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    uploadState.value = { reporteId: null, file: null, saving: false };
    await refreshReportes();
    setNotice("success", "Acta firmada cargada correctamente.");
  } catch (error) {
    const errors = error?.response?.data?.errors ?? {};
    const message = Object.values(errors)[0]?.[0];
    setNotice("error", message || error?.response?.data?.message || "No se pudo cargar el acta firmada.");
  } finally {
    uploadState.value.saving = false;
  }
}

function estadoClase(estado) {
  if (estado === "firmado") return "bg-emerald-50 text-emerald-700 ring-emerald-200";
  if (estado === "anulado") return "bg-red-50 text-red-700 ring-red-200";
  return "bg-amber-50 text-amber-700 ring-amber-200";
}

function estadoLabel(estado) {
  if (estado === "firmado") return "Firmado";
  if (estado === "anulado") return "Anulado";
  return "Pendiente de firma";
}

function formatDate(value) {
  if (!value) return "-";

  return new Intl.DateTimeFormat("es-EC", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}
</script>

<template>
  <div class="mx-auto w-full max-w-[1180px] space-y-5 px-3 py-5 sm:space-y-6 sm:px-6 sm:py-6 lg:px-4">
    <Teleport to="body">
      <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-2 opacity-0"
      >
        <div
          v-if="notice.message"
          class="fixed right-4 top-4 z-50 flex w-[calc(100%-2rem)] max-w-md items-start gap-3 rounded-2xl border bg-white px-4 py-3 text-sm shadow-xl sm:right-6 sm:top-6"
          :class="{
            'border-emerald-200 text-emerald-800': notice.type === 'success',
            'border-amber-200 text-amber-800': notice.type === 'warning',
            'border-red-200 text-red-800': notice.type === 'error',
          }"
        >
          <CheckCircleIcon v-if="notice.type === 'success'" class="mt-0.5 h-5 w-5 shrink-0" />
          <ExclamationTriangleIcon v-else class="mt-0.5 h-5 w-5 shrink-0" />
          <span class="flex-1 leading-5">{{ notice.message }}</span>
          <button
            type="button"
            class="rounded-full p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
            aria-label="Cerrar notificación"
            @click="setNotice('', '')"
          >
            <XMarkIcon class="h-4 w-4" />
          </button>
        </div>
      </transition>
    </Teleport>

    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Reportes</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-500">
          Genera reportes PDF para imprimir, firmar manualmente y guardar el acta firmada en el sistema.
        </p>
      </div>

      <button
        type="button"
        class="inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 sm:w-auto"
        :disabled="loading"
        @click="refreshReportes"
      >
        <ArrowPathIcon class="h-5 w-5" :class="{ 'animate-spin': loading }" />
        Actualizar
      </button>
    </div>

    <div class="grid gap-3 sm:gap-4 md:grid-cols-3">
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <p class="text-sm text-slate-500">Reportes generados</p>
        <p class="mt-4 text-3xl font-semibold text-slate-900">{{ summary.total }}</p>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <p class="text-sm text-slate-500">Pendientes de firma</p>
        <p class="mt-4 text-3xl font-semibold text-amber-700">{{ summary.pendientes }}</p>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <p class="text-sm text-slate-500">Actas firmadas</p>
        <p class="mt-4 text-3xl font-semibold text-emerald-700">{{ summary.firmados }}</p>
      </div>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="flex items-center gap-2 text-lg font-semibold text-slate-900">
          <DocumentTextIcon class="h-5 w-5 text-blue-600" />
          Generar nuevo reporte
        </h2>
      </div>

      <div class="grid gap-4 px-4 py-4 sm:px-5 sm:py-5 md:grid-cols-2 xl:grid-cols-4">
        <div>
          <label class="mb-1 block text-sm font-semibold text-slate-800">Competencia</label>
          <select
            v-model="selectedCompetition"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            @change="changeCompetition"
          >
            <option v-for="competencia in competencias" :key="competencia.id" :value="competencia.id">
              {{ competencia.nombre }}
            </option>
          </select>
        </div>

        <div>
          <label class="mb-1 block text-sm font-semibold text-slate-800">Categoría</label>
          <select
            v-model="selectedCategoryId"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Selecciona una categoría</option>
            <option v-for="categoria in categorias" :key="categoria.id" :value="categoria.id">
              {{ categoria.nombre }}
            </option>
          </select>
        </div>

        <div v-if="false">
          <label class="mb-1 block text-sm font-semibold text-slate-800">Ronda</label>
          <select
            v-model="selectedRondaId"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Sin ronda específica</option>
            <option v-for="ronda in rondasDisponibles" :key="ronda.id" :value="ronda.id">
              {{ ronda.nombre }}{{ ronda.es_final ? " - Final" : "" }}
            </option>
          </select>
          <p v-if="requiereRonda" class="mt-1 text-xs text-amber-700">
            La tabla de resultados requiere una ronda.
          </p>
        </div>

        <div>
          <label class="mb-1 block text-sm font-semibold text-slate-800">Tipo de reporte</label>
          <select
            v-model="selectedTipoReporte"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option v-for="tipo in tiposReporte" :key="tipo.value" :value="tipo.value">
              {{ tipo.label }}
            </option>
          </select>
        </div>

        <div v-if="false" class="lg:col-span-3">
          <label class="mb-1 block text-sm font-semibold text-slate-800">Observaciones</label>
          <input
            v-model="observaciones"
            type="text"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Opcional"
          />
        </div>

        <div class="flex items-end">
          <button
            type="button"
            class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="generating || !puedeGenerar"
            @click="generarReporte"
          >
            <PrinterIcon class="h-5 w-5" />
            {{ generating ? "Generando..." : "Generar PDF" }}
          </button>
        </div>
      </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-slate-900">Historial de reportes</h2>
          <p class="text-sm text-slate-500">Descarga el PDF generado o carga el acta firmada manualmente.</p>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-[980px] w-full text-sm">
          <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-5 py-3">Reporte</th>
              <th class="px-5 py-3">Categoría</th>
              <th class="px-5 py-3">Estado</th>
              <th class="px-5 py-3">Generado</th>
              <th class="px-5 py-3">Acta firmada</th>
              <th class="px-5 py-3 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="reporte in reportes" :key="reporte.id" class="align-top">
              <td class="px-5 py-4">
                <p class="font-semibold text-slate-900">{{ reporte.tipo_reporte_label }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ reporte.ronda_nombre || "Categoría completa" }}</p>
              </td>
              <td class="px-5 py-4">
                <p class="font-medium text-slate-900">{{ reporte.categoria_nombre }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ reporte.competencia_nombre }}</p>
              </td>
              <td class="px-5 py-4">
                <span
                  class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1"
                  :class="estadoClase(reporte.estado)"
                >
                  {{ estadoLabel(reporte.estado) }}
                </span>
              </td>
              <td class="px-5 py-4 text-slate-600">
                <p>{{ formatDate(reporte.generado_at) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ reporte.generado_por_nombre }}</p>
              </td>
              <td class="px-5 py-4">
                <p v-if="reporte.download_firmado_url" class="text-sm text-emerald-700">
                  Cargada el {{ formatDate(reporte.archivo_firmado_subido_at) }}
                </p>
                <div v-else class="space-y-2">
                  <input
                    type="file"
                    accept="application/pdf"
                    class="block w-56 text-xs text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700 hover:file:bg-slate-200"
                    @change="onFileChange($event, reporte)"
                  />
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="uploadState.saving && Number(uploadState.reporteId) === Number(reporte.id)"
                    @click="subirFirmado(reporte)"
                  >
                    <CloudArrowUpIcon class="h-4 w-4" />
                    {{ uploadState.saving && Number(uploadState.reporteId) === Number(reporte.id) ? "Cargando..." : "Cargar firmado" }}
                  </button>
                </div>
              </td>
              <td class="px-5 py-4">
                <div class="flex flex-wrap justify-end gap-2">
                  <a
                    :href="reporte.download_generado_url"
                    class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-700"
                  >
                    <DocumentArrowDownIcon class="h-4 w-4" />
                    Generado
                  </a>
                  <a
                    v-if="reporte.download_firmado_url"
                    :href="reporte.download_firmado_url"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700"
                  >
                    <ArrowDownTrayIcon class="h-4 w-4" />
                    Firmado
                  </a>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!reportes.length" class="px-5 py-12 text-center">
        <DocumentTextIcon class="mx-auto h-10 w-10 text-slate-300" />
        <p class="mt-3 font-semibold text-slate-800">No hay reportes generados.</p>
        <p class="mt-1 text-sm text-slate-500">Selecciona una categoría y genera el primer PDF.</p>
      </div>
    </section>
  </div>
</template>
