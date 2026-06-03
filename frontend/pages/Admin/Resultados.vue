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
  PencilSquareIcon,
  XMarkIcon,
} from "@heroicons/vue/24/outline";
import { formatEcuadorDateTime } from "@/lib/datetime";

defineOptions({ layout: AdminLayout });

const page = usePage();

const activeTab = ref("registros");

const competenciaId = computed(() => page.props.competenciaId ?? null);
const competencias = computed(() => page.props.competencias ?? []);
const categorias = computed(() => page.props.categorias ?? []);

const selectedCompetition = ref(competenciaId.value ?? "");
const selectedCategoryId = ref(null);
const selectedRondaId = ref(null);

const evaluacionesLoading = ref(false);
const evaluacionesNotice = ref({ type: "", message: "" });
const selectedEstadoEvaluacion = ref("registrado");
const selectedJuezId = ref("");
const editEvaluacionModal = ref({
  open: false,
  row: null,
  payload: {},
  motivo_opcion: "",
  motivo_otro: "",
});
const editEvaluacionSaving = ref(false);
const editEvaluacionErrors = ref({});
const evaluacionesSuccessModal = ref({ open: false, message: "" });
let evaluacionesSuccessTimer = null;
const correctionReasonOptions = [
  "Error de digitación en el resultado",
  "Corrección por reclamo aprobado",
  "Penalización aplicada después del registro",
  "Penalización retirada después de revisión",
  "Corrección por decisión del comité organizador",
  "Otro",
];
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
const liveConnectionMode = ref("WebSocket");
const liveNotice = ref("");
const selectedLiveDetail = ref(null);
let liveEventSource = null;
let liveChannelName = null;

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

const finalesUsaTiempo = computed(() => Boolean(finalesData.value.scope?.usa_tiempo));

const publicationHistory = computed(() => {
  return finalesData.value.publication_history ?? [];
});

const evaluacionesSummary = computed(() => {
  return evaluacionesData.value.summary ?? {
    total: 0,
    registradas: 0,
    publicadas: 0,
    equipos_count: 0,
    jueces_count: 0,
  };
});

const editFieldsGrouped = computed(() => {
  const fields = editEvaluacionModal.value.row?.campos_edicion ?? [];

  return {
    principales: fields.filter((field) => !field.es_penalizacion),
    penalizaciones: fields.filter((field) => field.es_penalizacion),
  };
});

const editTemplate = computed(() => editEvaluacionModal.value.row?.plantilla_resultado || "");
const editFields = computed(() => editEvaluacionModal.value.row?.campos_edicion ?? []);
const editNumericFields = computed(() =>
  editFields.value.filter((field) => field.type === "number")
);
const editObservationFields = computed(() =>
  editFields.value.filter((field) => field.key === "observaciones" || field.type === "textarea")
);
const editNonNumericFields = computed(() =>
  editFields.value.filter((field) => field.type !== "number" && field.key !== "observaciones" && field.type !== "textarea")
);
const editPositiveFields = computed(() =>
  editNumericFields.value.filter((field) => !field.es_penalizacion)
);
const editPenaltyFields = computed(() =>
  editNumericFields.value.filter((field) => field.es_penalizacion)
);

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

function openLiveDetail(scope, row) {
  selectedLiveDetail.value = {
    scope,
    row,
    detail: row.detalle_publico ?? null,
  };
}

function closeLiveDetail() {
  selectedLiveDetail.value = null;
}

function formatDetailNumber(value) {
  const number = Number(value ?? 0);
  return Number.isInteger(number) ? String(number) : number.toFixed(2);
}

function editNumberValue(key, fallback = 0) {
  const value = editEvaluacionModal.value.payload?.[key];
  if (value === null || value === "" || value === undefined || Number.isNaN(Number(value))) {
    return fallback;
  }

  return Number(value);
}

function fieldUnit(field) {
  const unit = Number(field?.valor_unitario ?? field?.max ?? 1);
  return Number.isFinite(unit) ? unit : 1;
}

function fieldSignedUnit(field) {
  const unit = fieldUnit(field);
  return field?.es_penalizacion ? -Math.abs(unit) : unit;
}

function fieldUnitLabel(field) {
  const unit = Math.abs(fieldUnit(field));
  return field?.es_penalizacion ? `-${formatDetailNumber(unit)}` : `x${formatDetailNumber(unit)}`;
}

function fieldKindLabel(field) {
  return field?.es_penalizacion ? "Resta" : "Suma";
}

function fieldTotal(field, suffix = "") {
  return editNumberValue(`${field.key}${suffix}`) * fieldSignedUnit(field);
}

function individualCriteriaTotal() {
  return editNumericFields.value.reduce((total, field) => total + fieldTotal(field), 0);
}

function matchupCriteriaTotal(side) {
  return editNumericFields.value.reduce((total, field) => total + fieldTotal(field, `_${side}`), 0);
}

function maxScoreTotal() {
  return editNumericFields.value.reduce((total, field) => total + editNumberValue(field.key), 0);
}

function visibleFieldInputType(field) {
  return field.type === "number" ? "number" : "text";
}

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

function templateDisplayName(row) {
  return row?.plantilla_nombre || row?.mecanismo_nombre || "Sin plantilla";
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

function shouldHandleLiveEvent(payload) {
  if (Number(payload?.competencia_id) !== Number(selectedCompetition.value)) {
    return false;
  }

  if (Number(selectedLiveCategoryId.value) > 0 && Number(payload?.categoria_id) !== Number(selectedLiveCategoryId.value)) {
    return false;
  }

  if (Number(selectedLiveRondaId.value) > 0 && Number(payload?.ronda_id) !== Number(selectedLiveRondaId.value)) {
    return false;
  }

  return true;
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

        await loadLiveSnapshot();
      });
    liveConnected.value = true;
    return;
  }

  liveConnectionMode.value = "SSE";

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
      liveNotice.value = "Se recibió una actualización en vivo con formato inválido.";
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

function isBooleanEditField(field) {
  return ["checkbox", "boolean"].includes(field?.type);
}

function isSelectEditField(field) {
  return field?.type === "select";
}

function hasSideEditFields(row, field) {
  const payload = row?.payload_actual ?? {};
  return field?.type === "number" && (
    Object.prototype.hasOwnProperty.call(payload, `${field.key}_a`) ||
    Object.prototype.hasOwnProperty.call(payload, `${field.key}_b`)
  );
}

function buildEditablePayload(row) {
  const payload = { ...(row?.payload_actual ?? {}) };

  for (const field of row?.campos_edicion ?? []) {
    if (hasSideEditFields(row, field)) {
      payload[`${field.key}_a`] ??= "";
      payload[`${field.key}_b`] ??= "";
      continue;
    }

    payload[field.key] ??= isBooleanEditField(field) ? false : "";
  }

  return payload;
}

function openEditEvaluacion(row) {
  editEvaluacionErrors.value = {};
  editEvaluacionModal.value = {
    open: true,
    row,
    payload: buildEditablePayload(row),
    motivo_opcion: "",
    motivo_otro: "",
  };
}

function closeEditEvaluacion(force = false) {
  if (editEvaluacionSaving.value && !force) return;

  editEvaluacionModal.value = {
    open: false,
    row: null,
    payload: {},
    motivo_opcion: "",
    motivo_otro: "",
  };
  editEvaluacionErrors.value = {};
}

function showEvaluacionesSuccessModal(message) {
  if (evaluacionesSuccessTimer) {
    clearTimeout(evaluacionesSuccessTimer);
  }

  evaluacionesSuccessModal.value = { open: true, message };
  evaluacionesSuccessTimer = setTimeout(() => {
    evaluacionesSuccessModal.value = { open: false, message: "" };
    evaluacionesSuccessTimer = null;
  }, 2200);
}

function editFieldError(key) {
  return editEvaluacionErrors.value[`payload.${key}`]?.[0]
    ?? editEvaluacionErrors.value[key]?.[0]
    ?? "";
}

async function saveEditEvaluacion() {
  const row = editEvaluacionModal.value.row;
  if (!row) return;

  const motivoSeleccionado = String(editEvaluacionModal.value.motivo_opcion ?? "").trim();
  const motivoOtro = String(editEvaluacionModal.value.motivo_otro ?? "").trim();
  const motivoCambio = motivoSeleccionado === "Otro" ? motivoOtro : motivoSeleccionado;

  if (!motivoSeleccionado) {
    editEvaluacionErrors.value = {
      motivo_opcion: ["Selecciona el motivo de corrección."],
    };
    return;
  }

  if (!motivoCambio) {
    editEvaluacionErrors.value = {
      motivo_otro: ["Ingresa el motivo de corrección."],
    };
    return;
  }

  editEvaluacionSaving.value = true;
  editEvaluacionErrors.value = {};
  setEvaluacionesNotice("", "");

  try {
    await axios.patch(`/admin/resultados/evaluaciones/${row.id}`, {
      payload: editEvaluacionModal.value.payload,
      observaciones: null,
      motivo_cambio: motivoCambio,
    });

    closeEditEvaluacion(true);
    await loadEvaluaciones();

    if (activeTab.value === "control") {
      await loadFinales();
    }

    showEvaluacionesSuccessModal("Información actualizada correctamente.");
  } catch (error) {
    editEvaluacionErrors.value = error?.response?.data?.errors ?? {};
    const message = Object.values(editEvaluacionErrors.value)[0]?.[0];
    setEvaluacionesNotice(
      "error",
      message || error?.response?.data?.message || "No se pudo corregir la evaluación."
    );
  } finally {
    editEvaluacionSaving.value = false;
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
      setNotice("warning", message || "No fue posible consolidar con la selección actual.");
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
  return formatEcuadorDateTime(value, "Sin consolidar");
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
  activeTab.value = "registros";
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
  if (evaluacionesSuccessTimer) {
    clearTimeout(evaluacionesSuccessTimer);
  }
});
</script>

<template>
  <div class="w-full">
    <div class="mx-auto w-full max-w-[1180px] px-3 py-5 space-y-5 sm:px-6 sm:py-6 sm:space-y-6 lg:px-4">
      <div class="flex flex-col gap-1">
        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Resultados de Competencias</h1>
        <p class="text-sm text-slate-500">Registra evaluaciones, consolida clasificaciones y prepara la publicación final.</p>
      </div>

      <div class="inline-flex w-full rounded-2xl border border-slate-200 bg-gray-200 p-1 sm:w-auto">
        <button
          class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition sm:flex-none"
          :class="activeTab === 'registros' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'registros'"
        >
          <ClipboardDocumentListIcon class="h-4 w-4" />
          Registros de Jueces
        </button>

        <button
          class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition sm:flex-none"
          :class="activeTab === 'publicaciones' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'publicaciones'"
        >
          <SignalIcon class="h-4 w-4" />
          Publicaciones en Vivo
        </button>
      </div>

      <div v-if="activeTab === 'registros'" class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
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

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
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
            <table class="min-w-[1180px] w-full text-sm">
              <thead class="bg-white">
                <tr class="border-b border-slate-200 text-left text-black">
                  <th class="px-6 py-4 font-medium">Estado</th>
                  <th class="px-6 py-4 font-medium">Equipo</th>
                  <th class="px-6 py-4 font-medium">Prototipo</th>
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
                    <p class="mt-1 text-xs text-slate-500">{{ row.institucion || "Sin institución" }}</p>
                  </td>

                  <td class="px-6 py-4 align-top">
                    <p class="font-medium text-slate-900">{{ row.nombre_prototipo || "Sin prototipo" }}</p>
                  </td>

                  <td class="px-6 py-4 align-top">
                    <p class="font-medium text-slate-900">{{ row.juez_nombre || "Juez sin nombre" }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ row.rol_juez || row.juez_email || "Sin rol" }}</p>
                  </td>

                  <td class="px-6 py-4 align-top">
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                      {{ templateDisplayName(row) }}
                    </span>
                    <p class="mt-2 text-xs text-slate-400">{{ row.plantilla_resultado || row.mecanismo_codigo || "sin_codigo" }}</p>
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
                        type="button"
                        @click="openEditEvaluacion(row)"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-100"
                      >
                        <PencilSquareIcon class="h-4 w-4" />
                        Editar
                      </button>
                    </div>
                  </td>
                </tr>

                <tr v-if="!evaluacionesData.rows.length">
                  <td colspan="8" class="px-6 py-10 text-center text-slate-500">
                    No hay registros de jueces para esta selección.
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
            <table class="min-w-[900px] w-full text-sm">
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
            <table class="min-w-[860px] w-full text-sm">
              <thead class="bg-white">
                <tr class="border-b border-slate-200 text-left text-black">
                  <th class="px-6 py-4 font-medium w-[110px]">Posición</th>
                  <th class="px-6 py-4 font-medium">Equipo</th>
                  <th class="px-6 py-4 font-medium">Institución</th>
                  <th class="px-6 py-4 font-medium">Resultado</th>
                  <th v-if="!finalesUsaTiempo" class="px-6 py-4 font-medium">Puntaje</th>
                  <th v-if="!finalesUsaTiempo" class="px-6 py-4 font-medium">Tiempo</th>
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

                  <td v-if="!finalesUsaTiempo" class="px-6 py-4">
                    <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-200">
                      {{ row.puntaje_total ?? "-" }}
                    </span>
                  </td>

                  <td v-if="!finalesUsaTiempo" class="px-6 py-4 text-slate-700">
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
                  <td :colspan="finalesUsaTiempo ? 6 : 8" class="px-6 py-10 text-center text-slate-500">
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
                  {{ liveConnected ? `${liveConnectionMode} activo` : `Reconectando ${liveConnectionMode}` }}
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
                    Ver registros
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
                <table class="min-w-[760px] w-full text-sm">
                  <thead>
                    <tr v-if="scope.usa_enfrentamiento" class="border-b border-slate-200 text-left text-slate-600">
                      <th class="py-3 pr-4 font-medium">Encuentro</th>
                      <th class="py-3 pr-4 font-medium">Equipo A</th>
                      <th class="py-3 pr-4 font-medium">Resultado</th>
                      <th class="py-3 pr-0 font-medium">Equipo B</th>
                    </tr>
                    <tr v-else class="border-b border-slate-200 text-left text-slate-600">
                      <th class="py-3 pr-4 font-medium">Pos.</th>
                      <th class="py-3 pr-4 font-medium">Equipo</th>
                      <th class="py-3 pr-4 font-medium">Institución</th>
                      <th class="py-3 pr-4 font-medium">Resultado</th>
                      <th v-if="!scope.usa_tiempo" class="py-3 pr-0 font-medium">Puntaje</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-200">
                    <template v-if="scope.usa_enfrentamiento">
                      <tr
                        v-for="row in scope.rows"
                        :key="`${scope.key}-match-${row.encuentro}-${row.inscripcion_id || row.equipo_a}`"
                        class="cursor-pointer transition hover:bg-slate-50"
                        @click="openLiveDetail(scope, row)"
                      >
                        <td class="py-3 pr-4 font-semibold text-slate-900">{{ row.encuentro }}</td>
                        <td class="py-3 pr-4">
                          <p class="font-medium text-slate-900">{{ row.equipo_a }}</p>
                          <p class="mt-1 text-xs text-slate-500">{{ row.institucion_a || "Sin institución" }}</p>
                        </td>
                        <td class="py-3 pr-4 font-semibold text-slate-900">{{ row.resultado_label }}</td>
                        <td class="py-3 pr-0">
                          <p class="font-medium text-slate-900">{{ row.equipo_b }}</p>
                          <p class="mt-1 text-xs text-slate-500">{{ row.institucion_b || "Sin institución" }}</p>
                        </td>
                      </tr>
                    </template>
                    <tr
                      v-else
                      v-for="row in scope.rows"
                      :key="`${scope.key}-${row.posicion}-${row.inscripcion_id || row.equipo_nombre}`"
                      class="cursor-pointer transition hover:bg-slate-50"
                      @click="openLiveDetail(scope, row)"
                    >
                      <td class="py-3 pr-4 font-semibold text-slate-900">{{ row.posicion }}°</td>
                      <td class="py-3 pr-4">
                        <p class="font-medium text-slate-900">{{ row.equipo_nombre }}</p>
                      </td>
                      <td class="py-3 pr-4 text-slate-600">{{ row.institucion || "Sin institución" }}</td>
                      <td class="py-3 pr-4 text-slate-900">{{ row.resultado_label }}</td>
                      <td v-if="!scope.usa_tiempo" class="py-3 pr-0 text-slate-700">{{ row.puntaje_total ?? "-" }}</td>
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

    <div v-if="evaluacionesSuccessModal.open" class="fixed inset-0 z-[10080] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-950/30"></div>
      <div class="relative w-full max-w-sm rounded-2xl border border-emerald-200 bg-white p-6 text-center shadow-2xl">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50">
          <CheckCircleIcon class="h-8 w-8 text-emerald-600" />
        </div>
        <h3 class="mt-4 text-lg font-bold text-slate-900">Información actualizada</h3>
        <p class="mt-2 text-sm text-slate-600">{{ evaluacionesSuccessModal.message }}</p>
      </div>
    </div>

    <div v-if="editEvaluacionModal.open" class="fixed inset-0 z-[10060] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-950/50" @click="closeEditEvaluacion"></div>

      <div class="relative max-h-[90vh] w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
          <div>
            <h3 class="text-lg font-semibold text-slate-900">Editar evaluación</h3>
            <p class="mt-1 text-sm text-slate-500">
              Revisa los valores registrados y corrige solo lo necesario.
            </p>
          </div>

          <button
            type="button"
            class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 hover:bg-slate-50"
            @click="closeEditEvaluacion"
          >
            <XMarkIcon class="h-5 w-5 text-slate-600" />
          </button>
        </div>

        <div class="max-h-[65vh] overflow-y-auto px-5 py-5">
          <div class="mb-5 grid gap-3 rounded-2xl border border-blue-100 bg-blue-50 p-4 text-sm md:grid-cols-5">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Equipo</p>
              <p class="mt-1 font-semibold text-slate-900">{{ editEvaluacionModal.row?.equipo_nombre || "Equipo sin nombre" }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Prototipo</p>
              <p class="mt-1 font-semibold text-slate-900">{{ editEvaluacionModal.row?.nombre_prototipo || "Sin prototipo" }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Juez</p>
              <p class="mt-1 font-semibold text-slate-900">{{ editEvaluacionModal.row?.juez_nombre || "Juez sin nombre" }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Plantilla</p>
              <p class="mt-1 font-semibold text-slate-900">{{ templateDisplayName(editEvaluacionModal.row) }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Resultado actual</p>
              <p class="mt-1 font-semibold text-slate-900">{{ editEvaluacionModal.row?.resultado_label || "Sin resultado" }}</p>
            </div>
          </div>

          <section
            v-if="editTemplate === 'tabla_enfrentamiento_criterios'"
            class="overflow-hidden rounded-2xl border border-slate-200"
          >
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
              <h4 class="font-semibold text-slate-900">Criterios del enfrentamiento</h4>
              <p class="text-sm text-slate-500">Edita las cantidades registradas por el juez y revisa el impacto en cada participante.</p>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-[760px] w-full text-sm">
                <thead class="bg-white text-left text-slate-600">
                  <tr>
                    <th class="px-4 py-3 font-semibold">Criterio</th>
                    <th class="px-4 py-3 font-semibold">Tipo</th>
                    <th class="px-4 py-3 font-semibold">Valor</th>
                    <th class="px-4 py-3 font-semibold">Equipo A</th>
                    <th class="px-4 py-3 font-semibold">Total A</th>
                    <th class="px-4 py-3 font-semibold">Equipo B</th>
                    <th class="px-4 py-3 font-semibold">Total B</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                  <tr v-for="field in editNumericFields" :key="field.key">
                    <td class="px-4 py-3 font-semibold text-slate-900">{{ field.label }}</td>
                    <td class="px-4 py-3">
                      <span
                        class="rounded-full px-2 py-1 text-xs font-semibold ring-1"
                        :class="field.es_penalizacion ? 'bg-red-50 text-red-700 ring-red-200' : 'bg-emerald-50 text-emerald-700 ring-emerald-200'"
                      >
                        {{ fieldKindLabel(field) }}
                      </span>
                    </td>
                    <td class="px-4 py-3 font-semibold text-slate-700">{{ fieldUnitLabel(field) }}</td>
                    <td class="px-4 py-3">
                      <input
                        v-model="editEvaluacionModal.payload[`${field.key}_a`]"
                        type="number"
                        step="0.001"
                        class="w-24 rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      />
                      <p v-if="editFieldError(`${field.key}_a`)" class="mt-1 text-xs text-red-600">{{ editFieldError(`${field.key}_a`) }}</p>
                    </td>
                    <td class="px-4 py-3 font-bold" :class="field.es_penalizacion ? 'text-red-700' : 'text-slate-900'">
                      {{ formatDetailNumber(fieldTotal(field, "_a")) }}
                    </td>
                    <td class="px-4 py-3">
                      <input
                        v-model="editEvaluacionModal.payload[`${field.key}_b`]"
                        type="number"
                        step="0.001"
                        class="w-24 rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      />
                      <p v-if="editFieldError(`${field.key}_b`)" class="mt-1 text-xs text-red-600">{{ editFieldError(`${field.key}_b`) }}</p>
                    </td>
                    <td class="px-4 py-3 font-bold" :class="field.es_penalizacion ? 'text-red-700' : 'text-slate-900'">
                      {{ formatDetailNumber(fieldTotal(field, "_b")) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="grid gap-3 border-t border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
              <div class="rounded-xl bg-white p-3">
                <p class="text-xs font-semibold uppercase text-slate-500">Total Equipo A</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ formatDetailNumber(matchupCriteriaTotal("a")) }} pts</p>
              </div>
              <div class="rounded-xl bg-white p-3">
                <p class="text-xs font-semibold uppercase text-slate-500">Total Equipo B</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ formatDetailNumber(matchupCriteriaTotal("b")) }} pts</p>
              </div>
            </div>
          </section>

          <section
            v-else-if="editTemplate === 'tabla_individual_criterios'"
            class="overflow-hidden rounded-2xl border border-slate-200"
          >
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
              <h4 class="font-semibold text-slate-900">Criterios individuales</h4>
              <p class="text-sm text-slate-500">Cada cantidad se multiplica por su valor configurado en la plantilla.</p>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-[760px] w-full text-sm">
                <thead class="bg-white text-left text-slate-600">
                  <tr>
                    <th class="px-4 py-3 font-semibold">Criterio</th>
                    <th class="px-4 py-3 font-semibold">Tipo</th>
                    <th class="px-4 py-3 font-semibold">Valor</th>
                    <th class="px-4 py-3 font-semibold">Registrado</th>
                    <th class="px-4 py-3 font-semibold">Total</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                  <tr v-for="field in editNumericFields" :key="field.key">
                    <td class="px-4 py-3 font-semibold text-slate-900">{{ field.label }}</td>
                    <td class="px-4 py-3">
                      <span
                        class="rounded-full px-2 py-1 text-xs font-semibold ring-1"
                        :class="field.es_penalizacion ? 'bg-red-50 text-red-700 ring-red-200' : 'bg-emerald-50 text-emerald-700 ring-emerald-200'"
                      >
                        {{ fieldKindLabel(field) }}
                      </span>
                    </td>
                    <td class="px-4 py-3 font-semibold text-slate-700">{{ fieldUnitLabel(field) }}</td>
                    <td class="px-4 py-3">
                      <input
                        v-model="editEvaluacionModal.payload[field.key]"
                        type="number"
                        step="0.001"
                        class="w-28 rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      />
                      <p v-if="editFieldError(field.key)" class="mt-1 text-xs text-red-600">{{ editFieldError(field.key) }}</p>
                    </td>
                    <td class="px-4 py-3 font-bold" :class="field.es_penalizacion ? 'text-red-700' : 'text-slate-900'">
                      {{ formatDetailNumber(fieldTotal(field)) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="border-t border-slate-200 bg-slate-50 p-4">
              <p class="text-xs font-semibold uppercase text-slate-500">Resultado estimado</p>
              <p class="mt-1 text-xl font-bold text-slate-900">{{ formatDetailNumber(individualCriteriaTotal()) }} pts</p>
            </div>
          </section>

          <section
            v-else-if="editTemplate === 'tabla_individual_puntaje_maximo'"
            class="overflow-hidden rounded-2xl border border-slate-200"
          >
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
              <h4 class="font-semibold text-slate-900">Puntaje máximo por criterio</h4>
              <p class="text-sm text-slate-500">Edita el puntaje otorgado dentro del máximo configurado para cada criterio.</p>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-[760px] w-full text-sm">
                <thead class="bg-white text-left text-slate-600">
                  <tr>
                    <th class="px-4 py-3 font-semibold">Criterio</th>
                    <th class="px-4 py-3 font-semibold">Máximo</th>
                    <th class="px-4 py-3 font-semibold">Puntaje registrado</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                  <tr v-for="field in editNumericFields" :key="field.key">
                    <td class="px-4 py-3 font-semibold text-slate-900">{{ field.label }}</td>
                    <td class="px-4 py-3 font-semibold text-slate-700">{{ formatDetailNumber(fieldUnit(field)) }} pts</td>
                    <td class="px-4 py-3">
                      <input
                        v-model="editEvaluacionModal.payload[field.key]"
                        type="number"
                        step="0.001"
                        :max="fieldUnit(field)"
                        class="w-32 rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      />
                      <p v-if="editFieldError(field.key)" class="mt-1 text-xs text-red-600">{{ editFieldError(field.key) }}</p>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="border-t border-slate-200 bg-slate-50 p-4">
              <p class="text-xs font-semibold uppercase text-slate-500">Resultado estimado</p>
              <p class="mt-1 text-xl font-bold text-slate-900">{{ formatDetailNumber(maxScoreTotal()) }} pts</p>
            </div>
          </section>

          <section
            v-else-if="editTemplate === 'tiempo'"
            class="rounded-2xl border border-slate-200 p-4"
          >
            <h4 class="font-semibold text-slate-900">Registro de tiempo</h4>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
              <div v-for="field in editFields" :key="field.key" :class="field.type === 'textarea' ? 'sm:col-span-2' : ''">
                <label class="mb-1 block text-sm font-semibold text-slate-800">{{ field.label }}</label>
                <textarea
                  v-if="field.type === 'textarea'"
                  v-model="editEvaluacionModal.payload[field.key]"
                  rows="3"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <input
                  v-else
                  v-model="editEvaluacionModal.payload[field.key]"
                  :type="visibleFieldInputType(field)"
                  :step="field.type === 'number' ? '0.001' : undefined"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :placeholder="field.type === 'duration' ? 'Ej: 00:07:35' : ''"
                />
                <p v-if="editFieldError(field.key)" class="mt-1 text-xs text-red-600">{{ editFieldError(field.key) }}</p>
              </div>
            </div>
          </section>

          <section
            v-else-if="editTemplate === 'marcador'"
            class="rounded-2xl border border-slate-200 p-4"
          >
            <h4 class="font-semibold text-slate-900">Marcador del encuentro</h4>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
              <div v-for="field in editFields" :key="field.key">
                <label class="mb-1 block text-sm font-semibold text-slate-800">{{ field.label }}</label>
                <input
                  v-model="editEvaluacionModal.payload[field.key]"
                  :type="visibleFieldInputType(field)"
                  :step="field.type === 'number' ? '1' : undefined"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <p v-if="editFieldError(field.key)" class="mt-1 text-xs text-red-600">{{ editFieldError(field.key) }}</p>
              </div>
            </div>
          </section>

          <section v-else class="rounded-2xl border border-slate-200 p-4">
            <h4 class="font-semibold text-slate-900">Campos configurados</h4>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div v-for="field in editFields" :key="field.key" :class="field.type === 'textarea' ? 'sm:col-span-2' : ''">
                <label class="mb-1 block text-sm font-semibold text-slate-800">{{ field.label }}</label>
                <select
                  v-if="isSelectEditField(field)"
                  v-model="editEvaluacionModal.payload[field.key]"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">Selecciona {{ field.label.toLowerCase() }}</option>
                  <option
                    v-for="option in field.options || []"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label || option.value }}
                  </option>
                </select>
                <label
                  v-else-if="isBooleanEditField(field)"
                  class="flex min-h-[44px] items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-800"
                >
                  <input
                    v-model="editEvaluacionModal.payload[field.key]"
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                  />
                  <span>Activo</span>
                </label>
                <textarea
                  v-else-if="field.type === 'textarea'"
                  v-model="editEvaluacionModal.payload[field.key]"
                  rows="3"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <input
                  v-else
                  v-model="editEvaluacionModal.payload[field.key]"
                  :type="visibleFieldInputType(field)"
                  :step="field.type === 'number' ? '0.001' : undefined"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <p v-if="editFieldError(field.key)" class="mt-1 text-xs text-red-600">{{ editFieldError(field.key) }}</p>
              </div>
            </div>
          </section>

          <section
            v-if="editNonNumericFields.length && ['tabla_enfrentamiento_criterios', 'tabla_individual_criterios', 'tabla_individual_puntaje_maximo'].includes(editTemplate)"
            class="mt-5 rounded-2xl border border-slate-200 p-4"
          >
            <h4 class="font-semibold text-slate-900">Campos adicionales</h4>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div v-for="field in editNonNumericFields" :key="field.key">
                <label class="mb-1 block text-sm font-semibold text-slate-800">{{ field.label }}</label>
                <select
                  v-if="isSelectEditField(field)"
                  v-model="editEvaluacionModal.payload[field.key]"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">Selecciona {{ field.label.toLowerCase() }}</option>
                  <option
                    v-for="option in field.options || []"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label || option.value }}
                  </option>
                </select>
                <label
                  v-else-if="isBooleanEditField(field)"
                  class="flex min-h-[44px] items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-800"
                >
                  <input
                    v-model="editEvaluacionModal.payload[field.key]"
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                  />
                  <span>Activo</span>
                </label>
                <input
                  v-else
                  v-model="editEvaluacionModal.payload[field.key]"
                  :type="visibleFieldInputType(field)"
                  :step="field.type === 'number' ? '0.001' : undefined"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :placeholder="field.type === 'duration' ? 'Ej: 00:07:35' : ''"
                />
                <p v-if="editFieldError(field.key)" class="mt-1 text-xs text-red-600">{{ editFieldError(field.key) }}</p>
              </div>
            </div>
          </section>

          <section v-if="editObservationFields.length && editTemplate !== 'tiempo'" class="mt-5 rounded-2xl border border-slate-200 p-4">
            <h4 class="font-semibold text-slate-900">Observaciones de la plantilla</h4>
            <div class="mt-3 space-y-3">
              <div v-for="field in editObservationFields" :key="field.key">
                <label class="mb-1 block text-sm font-semibold text-slate-800">{{ field.label }}</label>
                <textarea
                  v-model="editEvaluacionModal.payload[field.key]"
                  rows="3"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :placeholder="`Ingresa ${field.label.toLowerCase()}`"
                />
                <p v-if="editFieldError(field.key)" class="mt-1 text-xs text-red-600">{{ editFieldError(field.key) }}</p>
              </div>
            </div>
          </section>

          <div class="mt-5 space-y-4">
            <div>
              <label class="mb-1 block text-sm font-semibold text-slate-800">
                Motivo de corrección <span class="text-red-500">*</span>
              </label>
              <select
                v-model="editEvaluacionModal.motivo_opcion"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Selecciona un motivo</option>
                <option
                  v-for="option in correctionReasonOptions"
                  :key="option"
                  :value="option"
                >
                  {{ option }}
                </option>
              </select>
              <p v-if="editFieldError('motivo_opcion')" class="mt-1 text-xs text-red-600">
                {{ editFieldError("motivo_opcion") }}
              </p>
            </div>

            <div v-if="editEvaluacionModal.motivo_opcion === 'Otro'">
              <label class="mb-1 block text-sm font-semibold text-slate-800">
                Especificar motivo <span class="text-red-500">*</span>
              </label>
              <input
                v-model="editEvaluacionModal.motivo_otro"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Escribe el motivo de corrección"
              />
              <p v-if="editFieldError('motivo_otro')" class="mt-1 text-xs text-red-600">
                {{ editFieldError("motivo_otro") }}
              </p>
            </div>
          </div>
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 px-4 py-4 sm:flex-row sm:justify-end sm:gap-3 sm:px-5">
          <button
            type="button"
            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto"
            @click="closeEditEvaluacion"
          >
            Cancelar
          </button>
          <button
            type="button"
            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
            :disabled="editEvaluacionSaving"
            @click="saveEditEvaluacion"
          >
            <CheckCircleIcon class="h-5 w-5" />
            {{ editEvaluacionSaving ? "Guardando..." : "Guardar cambios" }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="selectedLiveDetail" class="fixed inset-0 z-[10060] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-950/50" @click="closeLiveDetail"></div>

      <div class="relative max-h-[90vh] w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">
              {{ selectedLiveDetail.scope.categoria_nombre }} · {{ selectedLiveDetail.scope.ronda_nombre }}
            </p>
            <h3 class="mt-1 text-xl font-bold text-slate-900">
              {{ selectedLiveDetail.detail?.titulo || "Detalle del resultado" }}
            </h3>
            <p class="mt-1 text-sm text-slate-500">
              Resultado: <span class="font-semibold text-slate-900">{{ selectedLiveDetail.row.resultado_label }}</span>
            </p>
          </div>

          <button
            type="button"
            class="rounded-xl p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
            @click="closeLiveDetail"
            aria-label="Cerrar detalle"
          >
            <XMarkIcon class="h-5 w-5" />
          </button>
        </div>

        <div class="max-h-[72vh] overflow-y-auto p-5">
          <div
            v-if="selectedLiveDetail.detail?.matriz_jueces"
            class="mb-5 overflow-x-auto rounded-xl border border-slate-200"
          >
            <table class="min-w-[760px] w-full text-sm">
              <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                  <th class="px-4 py-3">Juez</th>
                  <th
                    v-for="attempt in selectedLiveDetail.detail.matriz_jueces.intentos"
                    :key="`admin-matrix-head-${attempt.numero}`"
                    class="px-4 py-3"
                  >
                    {{ attempt.label }}
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200">
                <tr
                  v-for="judge in selectedLiveDetail.detail.matriz_jueces.jueces"
                  :key="`admin-matrix-judge-${judge.juez_user_id}`"
                >
                  <td class="px-4 py-3 font-medium text-slate-900">{{ judge.juez_nombre }}</td>
                  <td
                    v-for="attempt in judge.intentos"
                    :key="`admin-matrix-judge-${judge.juez_user_id}-${attempt.numero}`"
                    class="px-4 py-3 text-slate-700"
                  >
                    {{ attempt.label || "-" }}
                  </td>
                </tr>
                <tr class="bg-blue-50 font-bold text-blue-800">
                  <td class="px-4 py-3">Promedio</td>
                  <td
                    v-for="attempt in selectedLiveDetail.detail.matriz_jueces.promedios"
                    :key="`admin-matrix-average-${attempt.numero}`"
                    class="px-4 py-3"
                  >
                    {{ attempt.label || "-" }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <template v-if="selectedLiveDetail.detail?.tipo === 'tabla_enfrentamiento_criterios'">
            <div class="mb-4 grid grid-cols-3 gap-3 rounded-2xl bg-slate-50 p-4 text-center">
              <div>
                <p class="text-xs text-slate-500">{{ selectedLiveDetail.detail.equipo_a }}</p>
                <p class="text-2xl font-black text-slate-900">{{ formatDetailNumber(selectedLiveDetail.detail.total_a) }}</p>
              </div>
              <div class="flex items-center justify-center text-lg font-bold text-slate-400">VS</div>
              <div>
                <p class="text-xs text-slate-500">{{ selectedLiveDetail.detail.equipo_b }}</p>
                <p class="text-2xl font-black text-slate-900">{{ formatDetailNumber(selectedLiveDetail.detail.total_b) }}</p>
              </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200">
              <table class="min-w-[760px] w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                  <tr>
                    <th class="px-4 py-3">Criterio</th>
                    <th class="px-4 py-3">Valor</th>
                    <th class="px-4 py-3">Cant. A</th>
                    <th class="px-4 py-3">Puntaje A</th>
                    <th class="px-4 py-3">Cant. B</th>
                    <th class="px-4 py-3">Puntaje B</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                  <tr v-for="item in selectedLiveDetail.detail.criterios" :key="item.criterio">
                    <td class="px-4 py-3 font-medium" :class="item.es_penalizacion ? 'text-red-600' : 'text-slate-900'">{{ item.criterio }}</td>
                    <td class="px-4 py-3">x {{ formatDetailNumber(item.valor_unitario) }}</td>
                    <td class="px-4 py-3">{{ formatDetailNumber(item.cantidad_a) }}</td>
                    <td class="px-4 py-3">{{ formatDetailNumber(item.puntaje_a) }}</td>
                    <td class="px-4 py-3">{{ formatDetailNumber(item.cantidad_b) }}</td>
                    <td class="px-4 py-3">{{ formatDetailNumber(item.puntaje_b) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <template v-else-if="['tabla_individual_criterios', 'tabla_individual_puntaje_maximo'].includes(selectedLiveDetail.detail?.tipo)">
            <div class="overflow-x-auto rounded-xl border border-slate-200">
              <table class="min-w-[760px] w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                  <tr>
                    <th class="px-4 py-3">Criterio</th>
                    <th class="px-4 py-3">{{ selectedLiveDetail.detail?.tipo === 'tabla_individual_puntaje_maximo' ? 'Máximo' : 'Cantidad' }}</th>
                    <th class="px-4 py-3">{{ selectedLiveDetail.detail?.tipo === 'tabla_individual_puntaje_maximo' ? 'Otorgado' : 'Valor' }}</th>
                    <th class="px-4 py-3">Puntaje</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                  <tr v-for="item in selectedLiveDetail.detail.criterios" :key="item.criterio">
                    <td class="px-4 py-3 font-medium" :class="item.es_penalizacion ? 'text-red-600' : 'text-slate-900'">{{ item.criterio }}</td>
                    <td class="px-4 py-3">{{ formatDetailNumber(selectedLiveDetail.detail?.tipo === 'tabla_individual_puntaje_maximo' ? item.puntaje_maximo : item.cantidad) }}</td>
                    <td class="px-4 py-3">{{ selectedLiveDetail.detail?.tipo === 'tabla_individual_puntaje_maximo' ? formatDetailNumber(item.puntaje) : `x ${formatDetailNumber(item.valor_unitario)}` }}</td>
                    <td class="px-4 py-3">{{ formatDetailNumber(item.puntaje) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
              <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Subtotal</p><p class="text-lg font-bold">{{ formatDetailNumber(selectedLiveDetail.detail.subtotal) }}</p></div>
              <div class="rounded-xl bg-red-50 p-4"><p class="text-xs text-red-500">Penalizaciones</p><p class="text-lg font-bold text-red-700">{{ formatDetailNumber(selectedLiveDetail.detail.penalizaciones) }}</p></div>
              <div class="rounded-xl bg-blue-50 p-4"><p class="text-xs text-blue-500">Resultado final</p><p class="text-lg font-bold text-blue-700">{{ formatDetailNumber(selectedLiveDetail.detail.total) }}</p></div>
            </div>
          </template>

          <template v-else-if="selectedLiveDetail.detail?.tipo === 'marcador'">
            <div class="rounded-2xl bg-slate-950 p-6 text-center text-white">
              <p class="text-sm text-slate-300">Marcador final</p>
              <p class="mt-2 text-5xl font-black">{{ selectedLiveDetail.detail.marcador_a }} - {{ selectedLiveDetail.detail.marcador_b }}</p>
            </div>
          </template>

          <template v-else-if="selectedLiveDetail.detail?.tipo === 'tiempo'">
            <div class="flex justify-center">
              <div class="w-full max-w-md rounded-2xl bg-slate-50 p-6 text-center">
                <p class="text-sm font-medium text-slate-500">Tiempo</p>
                <p class="mt-2 text-4xl font-black text-slate-900">{{ selectedLiveDetail.detail.tiempo_label }}</p>
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
