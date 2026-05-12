<script setup>
import AdminLayout from "@/layouts/AdminLayout.vue";
import { computed, ref, watch } from "vue";
import { useForm, usePage, router } from "@inertiajs/vue3";
import axios from "axios";

import {
  MagnifyingGlassIcon,
  PlusIcon,
  PencilSquareIcon,
  TrashIcon,
  TagIcon,
  CheckCircleIcon,
  XMarkIcon,
  DocumentTextIcon,
  Cog6ToothIcon,
  NoSymbolIcon,
  PhotoIcon,
  AdjustmentsHorizontalIcon,
} from "@heroicons/vue/24/outline";

defineOptions({ layout: AdminLayout });

const page = usePage();

// =====================================================
// FLASH -> TOAST (sin duplicados)
// =====================================================
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const toast = ref({ show: false, type: "info", message: "" });
let toastTimer = null;
let lastFlashKey = "";

function showToast(message, type = "info", ms = 3500) {
  toast.value = { show: true, type, message };
  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => (toast.value.show = false), ms);
}
function closeToast() {
  toast.value = { show: false, type: "info", message: "" };
  if (toastTimer) clearTimeout(toastTimer);
}

function openAlertModal({
  title = "Aviso",
  message,
  confirmText = "Aceptar",
  cancelText = "",
  variant = "info",
  onConfirm = null,
}) {
  alertModal.value = {
    show: true,
    title,
    message,
    confirmText,
    cancelText,
    variant,
    onConfirm,
  };
}

function closeAlertModal() {
  alertModal.value = {
    show: false,
    title: "Aviso",
    message: "",
    confirmText: "Aceptar",
    cancelText: "",
    variant: "info",
    onConfirm: null,
  };
}

function confirmAlertModal() {
  const action = alertModal.value.onConfirm;
  closeAlertModal();
  if (typeof action === "function") action();
}

watch(
  [flashSuccess, flashError],
  ([s, e]) => {
    const msg = s || e;
    if (!msg) return;

    const key = `${s ? "S" : "E"}:${msg}`;
    if (key === lastFlashKey) return;

    lastFlashKey = key;
    showToast(msg, s ? "success" : "error", s ? 3500 : 4500);
  },
  { immediate: true }
);

// =====================================================
// PROPS
// =====================================================
const competenciaId = computed(() => page.props.competenciaId ?? null);
const competenciasProp = computed(() => page.props.competencias ?? []);
const categoriasProp = computed(() => page.props.categorias ?? []);
const mecanismosCalificacion = computed(() => page.props.mecanismosCalificacion ?? []);

// =====================================================
// SELECTOR COMPETENCIA
// =====================================================
const selectedCompetenciaId = ref(competenciaId.value);

watch(competenciaId, (id) => {
  selectedCompetenciaId.value = id;
});

function changeCompetencia() {
  const id = Number(selectedCompetenciaId.value);
  if (!Number.isInteger(id) || id <= 0) return;

  router.get(
    "/admin/categorias",
    { competencia_id: id },
    {
      replace: true,
      preserveScroll: true,
      preserveState: true,
      only: ["competenciaId", "categorias", "competencias", "mecanismosCalificacion", "flash"],
    }
  );
}

// =====================================================
// DATA LOCAL (tabla + stats)
// =====================================================
const categories = ref([]);
const formatoSavingId = ref(null);
const formatoForms = ref({});

watch(
  categoriasProp,
  (val) => {
    categories.value = Array.isArray(val) ? val : [];
  },
  { immediate: true }
);

// =====================================================
// UI STATE
// =====================================================
const search = ref("");
const isModalOpen = ref(false);
const isEditing = ref(false);
const selectedId = ref(null);
const editingHasParticipants = ref(false);
const isHydratingForm = ref(false);
const activeTab = ref("categorias");
const formatoSearch = ref("");
const activeFormatoCategoryId = ref(null);

const pdfInput = ref(null);
const imageInput = ref(null);

const editingPdfUrl = ref(null);
const editingImageUrl = ref(null);
const isRondasModalOpen = ref(false);
const rondasLoading = ref(false);
const rondasSaving = ref(false);
const rondasCategoria = ref(null);
const rondas = ref([]);
const rondaEditingId = ref(null);
const rondaForm = ref({
  nombre: "",
  tipo: "libre",
  estado: "borrador",
  fecha_hora: "",
});
const alertModal = ref({
  show: false,
  title: "Aviso",
  message: "",
  confirmText: "Aceptar",
  cancelText: "",
  variant: "info",
  onConfirm: null,
});

// =====================================================
// FORM
// =====================================================
const form = useForm({
  competencia_id: competenciaId.value,
  nombre: "",
  costo_inscripcion: "0.00",
  estado: true,
  mecanismo_calificacion_id: "",
  unidad_resultado: "",
  orden_ranking: "desc",
  requiere_aprobacion_admin: true,
  visible_publico_en_vivo: false,
  permite_edicion_juez: true,
  pdf: null,
  imagen: null,
});

watch(competenciaId, (id) => {
  form.competencia_id = id;
});

function onPickPDF(e) {
  form.pdf = e.target.files?.[0] || null;
}

function onPickImage(e) {
  form.imagen = e.target.files?.[0] || null;
}

function showFormErrors() {
  const errors = form.errors ?? {};
  if (errors.nombre) return (showToast(errors.nombre, "warning", 4500), true);
  if (errors.nombre_key) return (showToast(errors.nombre_key, "warning", 4500), true);
  if (errors.costo_inscripcion) return (showToast(errors.costo_inscripcion, "warning", 4500), true);
  if (errors.estado) return (showToast(errors.estado, "warning", 4500), true);
  if (errors.mecanismo_calificacion_id) return (showToast(errors.mecanismo_calificacion_id, "warning", 4500), true);
  if (errors.unidad_resultado) return (showToast(errors.unidad_resultado, "warning", 4500), true);
  if (errors.orden_ranking) return (showToast(errors.orden_ranking, "warning", 4500), true);
  if (errors.requiere_aprobacion_admin) return (showToast(errors.requiere_aprobacion_admin, "warning", 4500), true);
  if (errors.visible_publico_en_vivo) return (showToast(errors.visible_publico_en_vivo, "warning", 4500), true);
  if (errors.permite_edicion_juez) return (showToast(errors.permite_edicion_juez, "warning", 4500), true);
  if (errors.pdf) return (showToast(errors.pdf, "warning", 4500), true);
  if (errors.imagen) return (showToast(errors.imagen, "warning", 4500), true);

  if (Object.keys(errors).length) {
    showToast("Revisa los campos del formulario.", "error", 4500);
    return true;
  }
  return false;
}

function normalizeNombreKey(str) {
  return String(str ?? "")
    .trim()
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/\s+/g, " ");
}

const existingKeys = computed(() => {
  return new Map(
    (categories.value || [])
      .filter((c) => c?.nombre_key)
      .map((c) => [String(c.nombre_key), Number(c.id)])
  );
});

// =====================================================
// COMPUTEDS
// =====================================================
const filtered = computed(() => {
  const term = search.value.trim().toLowerCase();
  if (!term) return categories.value;
  return categories.value.filter((c) => (c.nombre ?? "").toLowerCase().includes(term));
});

const filteredFormatoCategories = computed(() => {
  const term = formatoSearch.value.trim().toLowerCase();
  if (!term) return categories.value;
  return categories.value.filter((c) => (c.nombre ?? "").toLowerCase().includes(term));
});

const activeFormatoCategory = computed(() => {
  return categories.value.find((cat) => Number(cat.id) === Number(activeFormatoCategoryId.value)) ?? null;
});

const stats = computed(() => {
  const list = categories.value;
  const active = list.filter((c) => !!c.estado).length;
  const total = list.length;
  const inactive = Math.max(total - active, 0);
  return { total, active, inactive };
});

const canSaveCategory = computed(() => {
  const compId = Number(form.competencia_id);
  const hasCompetencia = Number.isInteger(compId) && compId > 0;
  const hasNombre = String(form.nombre ?? "").trim().length > 0;
  const costo = Number(form.costo_inscripcion ?? 0);
  const hasCostoValido = Number.isFinite(costo) && costo >= 0 && costo <= 999999.99;

  if (!hasCompetencia || !hasNombre || !hasCostoValido) return false;

  if (!isEditing.value) {
    return !!form.pdf && !!form.imagen;
  }

  return (!!form.pdf || !!editingPdfUrl.value) && (!!form.imagen || !!editingImageUrl.value);
});

const selectedMechanism = computed(() => {
  const id = Number(form.mecanismo_calificacion_id || 0);
  return mecanismosCalificacion.value.find((item) => Number(item.id) === id) ?? null;
});

const mechanismDefaults = {
  registro_resultado: { unidad: "", orden: "desc" },
  tabla_evaluacion: { unidad: "pts", orden: "desc" },
  cronometro: { unidad: "s", orden: "asc" },
  puntaje: { unidad: "pts", orden: "desc" },
  puntaje_jueces: { unidad: "pts", orden: "desc" },
  combate: { unidad: "victorias", orden: "desc" },
  combate_llaves: { unidad: "pts", orden: "desc" },
  soccer_goles: { unidad: "dif. goles", orden: "desc" },
  dron_carrera: { unidad: "s", orden: "asc" },
  dron_destreza: { unidad: "pts", orden: "desc" },
  mixto: { unidad: "pts", orden: "desc" },
  solo_registro: { unidad: "", orden: "desc" },
};

const registroTypeOptions = [
  {
    value: "registro_resultado",
    label: "Registro de resultado",
    description: "Para tiempos, ganadores, marcadores, distancia o puntaje final simple.",
  },
  {
    value: "tabla_evaluacion",
    label: "Tabla de evaluacion",
    description: "Para categorias con criterios editables y puntuacion maxima por criterio.",
  },
];

const modalidadOptions = [
  {
    value: "participacion_individual",
    label: "Participacion individual",
    description: "Cada equipo participa solo, uno por uno.",
  },
  {
    value: "enfrentamiento_directo",
    label: "Enfrentamiento directo",
    description: "Dos equipos o robots compiten entre si.",
  },
];

const resultadoTemplateOptions = [
  {
    value: "tiempo",
    label: "Tiempo / cronometro",
    description: "Para categorias donde gana el menor tiempo o se registran penalizaciones.",
  },
  {
    value: "goles",
    label: "Goles / marcador",
    description: "Para soccer u otras categorias con goles a favor y en contra.",
  },
  {
    value: "puntaje",
    label: "Puntaje simple",
    description: "Para registrar un valor numerico final.",
  },
  {
    value: "ganador",
    label: "Ganador",
    description: "Para enfrentamientos donde se registra victoria, derrota o empate.",
  },
  {
    value: "personalizado",
    label: "Personalizado",
    description: "Mantiene los campos actuales para ajustar una categoria especial.",
  },
];

const evaluacionTemplateOptions = [
  {
    value: "tabla_individual_criterios",
    label: "Tabla individual por criterios",
    description: "Para registrar cantidades por criterio y calcular el puntaje de un equipo.",
  },
  {
    value: "tabla_enfrentamiento_criterios",
    label: "Tabla por enfrentamiento",
    description: "Para registrar criterios de Equipo A y Equipo B y calcular totales.",
  },
];

function templateOptionsForTipo(tipoRegistro) {
  return tipoRegistro === "tabla_evaluacion" ? evaluacionTemplateOptions : resultadoTemplateOptions;
}

function normalizeTemplateForTipo(tipoRegistro, plantilla, modalidad = "participacion_individual") {
  const options = templateOptionsForTipo(tipoRegistro);
  if (options.some((option) => option.value === plantilla)) return plantilla;

  if (tipoRegistro === "tabla_evaluacion") {
    return modalidad === "enfrentamiento_directo"
      ? "tabla_enfrentamiento_criterios"
      : "tabla_individual_criterios";
  }

  return "tiempo";
}

const esquemaJuecesOptions = [
  {
    value: "registro_cualquier_juez",
    label: "Registro por cualquier juez",
    description: "Cualquier juez asignado puede registrar el resultado.",
  },
  {
    value: "evaluacion_multi_juez",
    label: "Evaluacion multi-juez",
    description: "Varios jueces califican al mismo equipo con la misma tabla.",
  },
  {
    value: "registro_por_rol",
    label: "Registro por rol",
    description: "Cada juez registra una parte especifica del enfrentamiento.",
  },
];

const oldMechanismMap = {
  registro_resultado: {
    tipo_registro: "registro_resultado",
    modalidad_competencia: "participacion_individual",
    plantilla_resultado: "tiempo",
  },
  tabla_evaluacion: {
    tipo_registro: "tabla_evaluacion",
    modalidad_competencia: "participacion_individual",
    plantilla_resultado: "tabla_individual_criterios",
  },
  cronometro: {
    tipo_registro: "registro_resultado",
    modalidad_competencia: "participacion_individual",
    plantilla_resultado: "tiempo",
  },
  dron_carrera: {
    tipo_registro: "registro_resultado",
    modalidad_competencia: "participacion_individual",
    plantilla_resultado: "tiempo",
  },
  solo_registro: {
    tipo_registro: "registro_resultado",
    modalidad_competencia: "participacion_individual",
    plantilla_resultado: "puntaje",
  },
  puntaje: {
    tipo_registro: "tabla_evaluacion",
    modalidad_competencia: "participacion_individual",
    plantilla_resultado: "tabla_individual_criterios",
  },
  puntaje_jueces: {
    tipo_registro: "tabla_evaluacion",
    modalidad_competencia: "participacion_individual",
    plantilla_resultado: "tabla_individual_criterios",
  },
  dron_destreza: {
    tipo_registro: "tabla_evaluacion",
    modalidad_competencia: "participacion_individual",
    plantilla_resultado: "tabla_individual_criterios",
  },
  mixto: {
    tipo_registro: "tabla_evaluacion",
    modalidad_competencia: "participacion_individual",
    plantilla_resultado: "tabla_individual_criterios",
  },
  combate: {
    tipo_registro: "registro_resultado",
    modalidad_competencia: "enfrentamiento_directo",
    plantilla_resultado: "ganador",
  },
  combate_llaves: {
    tipo_registro: "registro_resultado",
    modalidad_competencia: "enfrentamiento_directo",
    plantilla_resultado: "ganador",
  },
  soccer_goles: {
    tipo_registro: "registro_resultado",
    modalidad_competencia: "enfrentamiento_directo",
    plantilla_resultado: "goles",
  },
};

function findMechanismByCode(code) {
  return mecanismosCalificacion.value.find((item) => item.codigo === code) ?? null;
}

function defaultMechanismId() {
  return findMechanismByCode("registro_resultado")?.id
    ?? findMechanismByCode("tabla_evaluacion")?.id
    ?? mecanismosCalificacion.value[0]?.id
    ?? "";
}

function ensureCategoryEvaluationDefaults() {
  form.mecanismo_calificacion_id = form.mecanismo_calificacion_id || defaultMechanismId();
  form.unidad_resultado = "";
  form.orden_ranking = "desc";
  form.requiere_aprobacion_admin = true;
  form.visible_publico_en_vivo = false;
  form.permite_edicion_juez = true;
}

function applyMechanismDefaults(code) {
  const defaults = mechanismDefaults[code];
  if (!defaults) return;

  form.unidad_resultado = defaults.unidad;
  form.orden_ranking = defaults.orden;
}

function suggestedMechanismCodeForName(name) {
  const normalized = normalizeNombreKey(name);

  if (!normalized) return null;

  if (
    normalized.includes("batalla")
    || normalized.includes("sumo")
    || normalized.includes("pelea")
    || normalized.includes("simulacion de batalla")
    || normalized.includes("persecucion")
    || normalized.includes("soccer")
    || normalized.includes("futbol")
    || normalized.includes("insectos")
  ) {
    return "combate_llaves";
  }

  if (normalized.includes("dron") && normalized.includes("carrera")) {
    return "cronometro";
  }

  if (normalized.includes("dron") && (normalized.includes("autonomo") || normalized.includes("destreza"))) {
    return "puntaje_jueces";
  }

  if (
    normalized.includes("bailarin")
    || normalized.includes("destreza")
  ) {
    return "puntaje_jueces";
  }

  if (
    normalized.includes("carrera")
    || normalized.includes("laberinto")
    || normalized.includes("trepador")
    || normalized.includes("seguidor")
  ) {
    return "cronometro";
  }

  return null;
}

function suggestedRegistroConfigForName(name) {
  const code = suggestedMechanismCodeForName(name);

  if (code === "puntaje_jueces") {
    return {
      tipo_registro: "tabla_evaluacion",
      modalidad_competencia: "participacion_individual",
      plantilla_resultado: "tabla_individual_criterios",
    };
  }

  if (code === "combate_llaves") {
    return {
      tipo_registro: "registro_resultado",
      modalidad_competencia: "enfrentamiento_directo",
      plantilla_resultado: "ganador",
    };
  }

  if (code === "soccer_goles") {
    return {
      tipo_registro: "registro_resultado",
      modalidad_competencia: "enfrentamiento_directo",
      plantilla_resultado: "goles",
    };
  }

  return {
    tipo_registro: "registro_resultado",
    modalidad_competencia: "participacion_individual",
    plantilla_resultado: "tiempo",
  };
}

function suggestedResultadoTemplateForName(name, tipoRegistro, modalidad) {
  const normalized = normalizeNombreKey(name);

  if (tipoRegistro === "tabla_evaluacion") {
    return modalidad === "enfrentamiento_directo"
      ? "tabla_enfrentamiento_criterios"
      : "tabla_individual_criterios";
  }

  if (normalized.includes("soccer") || normalized.includes("futbol")) return "goles";
  if (modalidad === "enfrentamiento_directo") return "ganador";
  if (normalized.includes("puntaje") || normalized.includes("distancia")) return "puntaje";

  return "tiempo";
}

function suggestedJudgeSchemeForName(name, tipoRegistro, modalidad) {
  const normalized = normalizeNombreKey(name);

  if (tipoRegistro === "tabla_evaluacion" && (normalized.includes("bailarin") || normalized.includes("dron"))) {
    return "evaluacion_multi_juez";
  }

  if (modalidad === "enfrentamiento_directo" && (normalized.includes("batalla") || normalized.includes("pelea"))) {
    return "registro_por_rol";
  }

  return "registro_cualquier_juez";
}

function storedRegistroConfig(cat) {
  return cat?.config_calificacion?.reglas_json?.registro ?? {};
}

function registroConfigForCategory(cat) {
  const stored = storedRegistroConfig(cat);
  const rawCode = cat?.config_calificacion?.mecanismo_codigo;
  const mapped = oldMechanismMap[rawCode] ?? suggestedRegistroConfigForName(cat?.nombre);
  const tipoRegistro = stored.tipo_registro ?? mapped.tipo_registro;
  const modalidad = stored.modalidad_competencia ?? mapped.modalidad_competencia;
  const plantilla = normalizeTemplateForTipo(tipoRegistro, stored.plantilla_resultado
    ?? mapped.plantilla_resultado
    ?? suggestedResultadoTemplateForName(cat?.nombre, tipoRegistro, modalidad), modalidad);

  return {
    tipo_registro: tipoRegistro,
    modalidad_competencia: modalidad,
    plantilla_resultado: plantilla,
    esquema_jueces: stored.esquema_jueces ?? suggestedJudgeSchemeForName(cat?.nombre, tipoRegistro, modalidad),
  };
}

function mechanismCodeFromRegistroConfig(tipoRegistro, modalidad) {
  return tipoRegistro === "tabla_evaluacion" ? "tabla_evaluacion" : "registro_resultado";
}

function officialFormatLabel(cat) {
  const config = registroConfigForCategory(cat);
  const type = registroTypeOptions.find((item) => item.value === config.tipo_registro)?.label ?? "Pendiente";
  const modalidad = modalidadOptions.find((item) => item.value === config.modalidad_competencia)?.label ?? "Sin modalidad";
  const esquema = esquemaJuecesOptions.find((item) => item.value === config.esquema_jueces)?.label ?? "Sin esquema";

  return `${type} - ${modalidad} - ${esquema}`;
}

function formatoOptionLabel(options, value, fallback = "Pendiente") {
  return options.find((item) => item.value === value)?.label ?? fallback;
}

function formatoOptionDescription(options, value) {
  return options.find((item) => item.value === value)?.description ?? "";
}

function isGolesFormato(formato) {
  return formato?.tipo_registro === "registro_resultado" && formato?.plantilla_resultado === "goles";
}

function isTiempoFormato(formato) {
  return formato?.tipo_registro === "registro_resultado" && formato?.plantilla_resultado === "tiempo";
}

function isTablaEnfrentamientoFormato(formato) {
  return formato?.tipo_registro === "tabla_evaluacion" && formato?.plantilla_resultado === "tabla_enfrentamiento_criterios";
}

function isTablaIndividualFormato(formato) {
  return formato?.tipo_registro === "tabla_evaluacion" && formato?.plantilla_resultado === "tabla_individual_criterios";
}

function criterioFields(formato) {
  return (formato?.campos_json ?? []).filter((field) => field.key !== "observaciones" && field.type === "number");
}

function sampleCantidad(index, lado) {
  const samples = lado === "A" ? [11, 7, 18, 2, 0, 4] : [6, 1, 9, 4, 0, 8];
  return samples[index % samples.length];
}

function criterioScore(field, index, lado) {
  const raw = sampleCantidad(index, lado) * Number(field?.valor_unitario || 0);
  return field?.es_penalizacion ? -raw : raw;
}

function enfrentamientoSubtotal(formato, lado) {
  return criterioFields(formato)
    .filter((field) => !field.es_penalizacion)
    .reduce((sum, field, index) => sum + criterioScore(field, index, lado), 0);
}

function enfrentamientoPenalizaciones(formato, lado) {
  return criterioFields(formato)
    .filter((field) => field.es_penalizacion)
    .reduce((sum, field, index) => sum + Math.abs(criterioScore(field, index, lado)), 0);
}

function enfrentamientoTotal(formato, lado) {
  return enfrentamientoSubtotal(formato, lado) - enfrentamientoPenalizaciones(formato, lado);
}

function individualSubtotal(formato) {
  return criterioFields(formato)
    .filter((field) => !field.es_penalizacion)
    .reduce((sum, field, index) => sum + criterioScore(field, index, "B"), 0);
}

function individualPenalizaciones(formato) {
  return criterioFields(formato)
    .filter((field) => field.es_penalizacion)
    .reduce((sum, field, index) => sum + Math.abs(criterioScore(field, index, "B")), 0);
}

function individualTotal(formato) {
  return individualSubtotal(formato) - individualPenalizaciones(formato);
}

function formatoConfigSummary(cat) {
  const form = formatoForms.value[cat.id] ?? defaultFormatoForm(cat);
  const configurableFields = Array.isArray(form.campos_json)
    ? form.campos_json.filter((field) => field.key !== "observaciones")
    : [];

  return {
    tipo: formatoOptionLabel(registroTypeOptions, form.tipo_registro),
    plantilla: formatoOptionLabel(templateOptionsForTipo(form.tipo_registro), form.plantilla_resultado, "Sin plantilla"),
    modalidad: formatoOptionLabel(modalidadOptions, form.modalidad_competencia, "Sin modalidad"),
    esquema: formatoOptionLabel(esquemaJuecesOptions, form.esquema_jueces, "Sin esquema"),
    campos: configurableFields.length,
  };
}

function selectFormatoCategory(cat) {
  activeFormatoCategoryId.value = cat?.id ?? null;
}

function officialMechanismIdForCategory(cat) {
  const config = registroConfigForCategory(cat);
  const code = mechanismCodeFromRegistroConfig(config.tipo_registro, config.modalidad_competencia);
  return findMechanismByCode(code)?.id ?? defaultMechanismId();
}

function registroTemplateKey(tipoRegistro, modalidad, plantilla = "tiempo") {
  if (tipoRegistro === "tabla_evaluacion") {
    return registroTemplates[`tabla_evaluacion_${plantilla}`]
      ? `tabla_evaluacion_${plantilla}`
      : "tabla_evaluacion_tabla_individual_criterios";
  }
  if (plantilla && registroTemplates[`registro_resultado_${plantilla}`]) {
    return `registro_resultado_${plantilla}`;
  }
  return modalidad === "enfrentamiento_directo" ? "registro_resultado_ganador" : "registro_resultado_tiempo";
}

const mechanismPreview = computed(() => {
  switch (selectedMechanism.value?.codigo) {
    case "registro_resultado":
      return {
        title: "Vista previa: registro de resultado",
        lines: ["Tiempo, ganador, marcador o puntaje final", "Penalizaciones opcionales", "Campos definidos por modalidad"],
      };
    case "tabla_evaluacion":
      return {
        title: "Vista previa: tabla de evaluacion",
        lines: ["Criterios configurables", "Puntaje maximo por criterio", "Ranking por mayor puntaje neto"],
      };
    case "cronometro":
      return {
        title: "Vista previa: cronómetro",
        lines: ["Tiempo final", "Penalizaciones y avance parcial", "Ranking por menor tiempo total"],
      };
    case "puntaje":
    case "puntaje_jueces":
      return {
        title: "Vista previa: puntaje",
        lines: ["Puntaje numérico", "Penalizaciones opcionales", "Ranking por mayor puntaje neto"],
      };
    case "combate":
      return {
        title: "Vista previa: combate",
        lines: ["Victorias y derrotas", "Penalizaciones opcionales", "Ranking por desempeño del combate"],
      };
    case "combate_llaves":
      return {
        title: "Vista previa: combate por llaves",
        lines: ["Victoria, derrota o empate", "Puntos, amonestaciones y descalificación", "Método de victoria"],
      };
    case "soccer_goles":
      return {
        title: "Vista previa: soccer por goles",
        lines: ["Goles a favor y en contra", "Faltas y amonestaciones", "Ranking por diferencia de goles"],
      };
    case "dron_carrera":
      return {
        title: "Vista previa: dron carrera",
        lines: ["Tiempo final", "Obstáculos no superados y penalización", "Ranking por menor tiempo total"],
      };
    case "dron_destreza":
      return {
        title: "Vista previa: dron destreza",
        lines: ["Puntaje de destreza", "Obstáculos superados", "Ranking por mayor puntaje neto"],
      };
    case "mixto":
      return {
        title: "Vista previa: mixto",
        lines: ["Puntaje principal", "Tiempo o desempate opcional", "Penalizaciones opcionales"],
      };
    case "solo_registro":
      return {
        title: "Vista previa: solo registro",
        lines: ["Resultado libre", "Observaciones", "Consolidación manual o por reglas externas"],
      };
    default:
      return {
        title: "Vista previa",
        lines: ["Selecciona un mecanismo para ver cómo evaluará el juez."],
      };
  }
});

watch(
  () => form.mecanismo_calificacion_id,
  () => {
    if (isHydratingForm.value) return;
    applyMechanismDefaults(selectedMechanism.value?.codigo);
  }
);

watch(
  () => form.nombre,
  (name) => {
    // Configuracion de evaluacion desactivada temporalmente mientras se define el flujo de negocio.
    return;

    if (isEditing.value || isHydratingForm.value || form.mecanismo_calificacion_id) return;

    const mechanism = findMechanismByCode(suggestedMechanismCodeForName(name));
    if (!mechanism) return;

    form.mecanismo_calificacion_id = mechanism.id;
    applyMechanismDefaults(mechanism.codigo);
  }
);

// helpers UI
const statusLabel = (estado) => (estado ? "Activa" : "Inactiva");
const formatPrice = (value) => {
  const amount = Number(value ?? 0);
  if (!Number.isFinite(amount) || amount <= 0) return "Gratis";
  return `$ ${amount.toFixed(2)}`;
};
const statusBadge = (label) => {
  switch (label) {
    case "Activa":
      return "bg-emerald-50 text-emerald-700 ring-emerald-200";
    case "Inactiva":
      return "bg-slate-100 text-slate-700 ring-slate-200";
    default:
      return "bg-slate-100 text-slate-700 ring-slate-200";
  }
};

const registroTemplates = {
  registro_resultado_tiempo: {
    label: "Tiempo / cronometro",
    description: "Campos base para tiempo: tiempo final, penalizacion y observaciones.",
    unidad: "s",
    orden: "asc",
    campos: [
      { key: "tiempo", type: "duration", label: "Tiempo final", required: true },
      { key: "penalizaciones", type: "number", label: "Penalizacion en segundos", required: false },
      { key: "observaciones", type: "textarea", label: "Observaciones", required: false },
    ],
  },
  registro_resultado_goles: {
    label: "Goles / marcador",
    description: "Campos base para soccer: goles a favor, goles en contra y observaciones.",
    unidad: "dif. goles",
    orden: "desc",
    campos: [
      { key: "goles_favor", type: "number", label: "Goles a favor", required: true },
      { key: "goles_contra", type: "number", label: "Goles en contra", required: true },
      { key: "observaciones", type: "textarea", label: "Observaciones", required: false },
    ],
  },
  registro_resultado_puntaje: {
    label: "Puntaje simple",
    description: "Campo base para registrar un puntaje final.",
    unidad: "pts",
    orden: "desc",
    campos: [
      { key: "puntaje", type: "number", label: "Puntaje final", required: true },
      { key: "penalizaciones", type: "number", label: "Penalizacion", required: false },
      { key: "observaciones", type: "textarea", label: "Observaciones", required: false },
    ],
  },
  registro_resultado_ganador: {
    label: "Ganador",
    description: "Campos base para enfrentamiento directo: resultado, puntos y observaciones.",
    unidad: "pts",
    orden: "desc",
    campos: [
      {
        key: "resultado",
        type: "select",
        label: "Resultado",
        required: true,
        options: [
          { value: "victoria", label: "Victoria" },
          { value: "derrota", label: "Derrota" },
          { value: "empate", label: "Empate" },
        ],
      },
      { key: "puntos", type: "number", label: "Puntos obtenidos", required: false },
      { key: "observaciones", type: "textarea", label: "Observaciones", required: false },
    ],
  },
  registro_resultado_personalizado: {
    label: "Personalizado",
    description: "Campos personalizados definidos por el administrador.",
    unidad: "",
    orden: "desc",
    campos: [
      { key: "resultado", type: "text", label: "Resultado", required: true },
      { key: "observaciones", type: "textarea", label: "Observaciones", required: false },
    ],
  },
  tabla_evaluacion_tabla_individual_criterios: {
    label: "Tabla individual por criterios",
    description: "Registra cantidades por criterio y calcula el puntaje final del equipo.",
    unidad: "pts",
    orden: "desc",
    campos: [
      { key: "inmovilizar", type: "number", label: "Inmovilizar al oponente por ataque", required: true, valor_unitario: 20, es_penalizacion: false },
      { key: "embestidas", type: "number", label: "Embestidas", required: true, valor_unitario: 5, es_penalizacion: false },
      { key: "vuelcos", type: "number", label: "Vuelcos", required: true, valor_unitario: 10, es_penalizacion: false },
      { key: "uso_armas", type: "number", label: "Uso de armas", required: true, valor_unitario: 15, es_penalizacion: false },
      { key: "amonestaciones", type: "number", label: "Amonestaciones", required: false, valor_unitario: 5, es_penalizacion: true },
      { key: "observaciones", type: "textarea", label: "Observaciones", required: false },
    ],
  },
  tabla_evaluacion_tabla_enfrentamiento_criterios: {
    label: "Tabla por enfrentamiento",
    description: "Registra criterios para Equipo A y Equipo B y calcula sus totales.",
    unidad: "pts",
    orden: "desc",
    campos: [
      { key: "golpes", type: "number", label: "Golpes", required: true, valor_unitario: 2, es_penalizacion: false },
      { key: "empujes", type: "number", label: "Empujes", required: true, valor_unitario: 1, es_penalizacion: false },
      { key: "salidas", type: "number", label: "Salidas del area", required: false, valor_unitario: 3, es_penalizacion: true },
      { key: "observaciones", type: "textarea", label: "Observaciones", required: false },
    ],
  },
};

function cloneFields(fields) {
  return JSON.parse(JSON.stringify(fields ?? []));
}

function normalizeFieldKey(label, index) {
  const key = String(label || `criterio_${index + 1}`)
    .trim()
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "");

  return key || `criterio_${index + 1}`;
}

function defaultFormatoForm(cat) {
  const rawCodigo = cat?.config_calificacion?.mecanismo_codigo;
  const registroConfig = registroConfigForCategory(cat);
  const safeCodigo = mechanismCodeFromRegistroConfig(registroConfig.tipo_registro, registroConfig.modalidad_competencia);
  const template = registroTemplates[registroTemplateKey(
    registroConfig.tipo_registro,
    registroConfig.modalidad_competencia,
    registroConfig.plantilla_resultado
  )];
  const storedFields = cat?.config_calificacion?.campos_json;
  const shouldReuseStoredFields = rawCodigo === safeCodigo && Array.isArray(storedFields) && storedFields.length;

  return {
    categoria_id: cat.id,
    tipo_registro: registroConfig.tipo_registro,
    modalidad_competencia: registroConfig.modalidad_competencia,
    plantilla_resultado: registroConfig.plantilla_resultado,
    esquema_jueces: registroConfig.esquema_jueces,
    mecanismo_codigo: safeCodigo,
    unidad_resultado: rawCodigo === safeCodigo ? (cat?.config_calificacion?.unidad_resultado ?? template.unidad) : template.unidad,
    orden_ranking: rawCodigo === safeCodigo ? (cat?.config_calificacion?.orden_ranking ?? template.orden) : template.orden,
    requiere_aprobacion_admin: cat?.config_calificacion?.requiere_aprobacion_admin ?? true,
    visible_publico_en_vivo: cat?.config_calificacion?.visible_publico_en_vivo ?? false,
    permite_edicion_juez: cat?.config_calificacion?.permite_edicion_juez ?? true,
    campos_json: shouldReuseStoredFields ? cloneFields(storedFields) : cloneFields(template.campos),
  };
}

function syncFormatoForms() {
  const next = { ...formatoForms.value };

  for (const cat of categories.value) {
    if (!next[cat.id]) {
      next[cat.id] = defaultFormatoForm(cat);
    }
  }

  formatoForms.value = next;
}

watch(
  categories,
  () => {
    syncFormatoForms();
    const hasActive = categories.value.some((cat) => Number(cat.id) === Number(activeFormatoCategoryId.value));
    if (!hasActive) {
      activeFormatoCategoryId.value = categories.value[0]?.id ?? null;
    }
  },
  { immediate: true }
);

function syncMechanismFromRegistroConfig(cat, resetFields = true) {
  const current = formatoForms.value[cat.id];
  if (!current) return;

  const codigo = mechanismCodeFromRegistroConfig(current.tipo_registro, current.modalidad_competencia);
  const template = registroTemplates[registroTemplateKey(
    current.tipo_registro,
    current.modalidad_competencia,
    current.plantilla_resultado
  )];
  if (!template) return;

  formatoForms.value[cat.id] = {
    ...current,
    mecanismo_codigo: codigo,
    unidad_resultado: template.unidad,
    orden_ranking: template.orden,
    campos_json: resetFields ? cloneFields(template.campos) : current.campos_json,
  };
}

function applyRegistroType(cat, tipoRegistro) {
  const current = formatoForms.value[cat.id];
  if (!current) return;

  formatoForms.value[cat.id] = {
    ...current,
    tipo_registro: tipoRegistro,
    plantilla_resultado: suggestedResultadoTemplateForName(cat.nombre, tipoRegistro, current.modalidad_competencia),
  };

  syncMechanismFromRegistroConfig(cat);
}

function applyModalidad(cat, modalidad) {
  const current = formatoForms.value[cat.id];
  if (!current) return;

  formatoForms.value[cat.id] = {
    ...current,
    modalidad_competencia: modalidad,
    plantilla_resultado: current.tipo_registro === "registro_resultado" && current.plantilla_resultado === "ganador"
      ? "ganador"
      : current.plantilla_resultado,
  };

  syncMechanismFromRegistroConfig(cat);
}

function applyResultadoTemplate(cat, plantilla) {
  const current = formatoForms.value[cat.id];
  if (!current) return;

  formatoForms.value[cat.id] = {
    ...current,
    plantilla_resultado: plantilla,
  };

  syncMechanismFromRegistroConfig(cat, plantilla !== "personalizado");
}

function applyEsquemaJueces(cat, esquema) {
  if (!formatoForms.value[cat.id]) return;

  formatoForms.value[cat.id] = {
    ...formatoForms.value[cat.id],
    esquema_jueces: esquema,
  };
}

function addRubricaCriterion(cat) {
  const current = formatoForms.value[cat.id];
  if (!current) return;

  const criteriaCount = current.campos_json.filter((field) => field.type === "number").length + 1;
  current.campos_json.splice(Math.max(current.campos_json.length - 1, 0), 0, {
    key: `criterio_${criteriaCount}`,
    type: "number",
    label: `Criterio ${criteriaCount}`,
    required: true,
    max: 999,
    valor_unitario: 1,
    es_penalizacion: false,
  });
}

function removeRubricaCriterion(cat, index) {
  const current = formatoForms.value[cat.id];
  if (!current) return;

  current.campos_json.splice(index, 1);
}

function normalizeFormatoPayload(raw) {
  const fields = cloneFields(raw.campos_json)
    .map((field, index) => ({
      ...field,
      key: normalizeFieldKey(field.key || field.label, index),
      required: !!field.required,
      max: field.max === "" || field.max === null || field.max === undefined ? undefined : Number(field.max),
      valor_unitario: field.valor_unitario === "" || field.valor_unitario === null || field.valor_unitario === undefined
        ? undefined
        : Number(field.valor_unitario),
      es_penalizacion: !!field.es_penalizacion,
    }))
    .filter((field) => field.key && field.label);

  return {
    ...raw,
    mecanismo_codigo: mechanismCodeFromRegistroConfig(raw.tipo_registro, raw.modalidad_competencia),
    plantilla_resultado: raw.plantilla_resultado || suggestedResultadoTemplateForName("", raw.tipo_registro, raw.modalidad_competencia),
    campos_json: fields,
  };
}

async function saveFormato(cat) {
  const current = formatoForms.value[cat.id];
  if (!current) return;

  formatoSavingId.value = cat.id;

  try {
    await axios.put(`/admin/categorias/${cat.id}/formato-registro`, normalizeFormatoPayload(current));
    showToast("Formato de registro actualizado", "success", 3000);
    router.reload({
      preserveScroll: true,
      preserveState: true,
      only: ["categorias", "flash"],
    });
  } catch (error) {
    const errors = error?.response?.data?.errors ?? {};
    const first = Object.values(errors)[0]?.[0];
    showToast(first || error?.response?.data?.message || "No se pudo guardar el formato.", "error", 4500);
  } finally {
    formatoSavingId.value = null;
  }
}

// =====================================================
// MODAL
// =====================================================
function openCreate() {
  resetForCreate();
  isModalOpen.value = true;
}

function openEdit(cat) {
  resetForEdit(cat);
  isModalOpen.value = true;
}

function closeModal() {
  isModalOpen.value = false;
  form.clearErrors();
  resetForCreate();
}

function resetForCreate() {
  isHydratingForm.value = true;
  isEditing.value = false;
  selectedId.value = null;
  editingHasParticipants.value = false;
  editingPdfUrl.value = null;
  editingImageUrl.value = null;

  form.clearErrors();
  form.competencia_id = competenciaId.value;
  form.nombre = "";
  form.costo_inscripcion = "0.00";
  form.estado = true;
  form.mecanismo_calificacion_id = defaultMechanismId();
  ensureCategoryEvaluationDefaults();
  form.pdf = null;
  form.imagen = null;

  if (pdfInput.value) pdfInput.value.value = null;
  if (imageInput.value) imageInput.value.value = null;
  isHydratingForm.value = false;
}

function resetForEdit(cat) {
  isHydratingForm.value = true;
  isEditing.value = true;

  const id = Number(cat?.id);
  selectedId.value = Number.isInteger(id) && id > 0 ? id : null;
  editingHasParticipants.value = !!cat?.has_participantes;

  editingPdfUrl.value = cat?.reglamento_url ?? null;
  editingImageUrl.value = cat?.imagen_url ?? null;

  form.clearErrors();
  form.competencia_id = competenciaId.value;
  form.nombre = cat?.nombre ?? "";
  form.costo_inscripcion = Number(cat?.costo_inscripcion ?? 0).toFixed(2);
  form.estado = !!cat?.estado;
  form.mecanismo_calificacion_id = officialMechanismIdForCategory(cat);
  ensureCategoryEvaluationDefaults();
  form.pdf = null;
  form.imagen = null;

  if (pdfInput.value) pdfInput.value.value = null;
  if (imageInput.value) imageInput.value.value = null;
  isHydratingForm.value = false;
}

function onToggleEstado() {
  if (form.estado && isEditing.value && editingHasParticipants.value) {
    openAlertModal({
      title: "No disponible",
      message: "No se puede desactivar esta categoría porque tiene participantes registrados.",
      confirmText: "Entendido",
      variant: "warning",
    });
    return;
  }

  form.estado = !form.estado;
}

// =====================================================
// CREATE / UPDATE
// =====================================================
async function save() {
  const compId = Number(form.competencia_id);
  if (!Number.isInteger(compId) || compId <= 0) {
    showToast("Selecciona una competencia válida.", "warning", 3500);
    return;
  }

  if (!form.nombre?.trim()) {
    showToast("Falta el nombre de la categoría.", "warning", 3500);
    return;
  }

  const costo = Number(form.costo_inscripcion ?? 0);
  if (!Number.isFinite(costo) || costo < 0 || costo > 999999.99) {
    showToast("Ingresa un costo de inscripción válido.", "warning", 3500);
    return;
  }

  if (!isEditing.value && !form.imagen) {
    showToast("La imagen es obligatoria.", "warning", 3500);
    return;
  }

  const key = normalizeNombreKey(form.nombre);
  const foundId = existingKeys.value.get(key);

  if (!isEditing.value) {
    if (foundId) {
      showToast("Categoría ya registrada en esta competencia.", "warning", 4000);
      return;
    }
  } else {
    const currentId = Number(selectedId.value);
    if (foundId && foundId !== currentId) {
      showToast("Ya existe otra categoría con ese nombre en esta competencia.", "warning", 4000);
      return;
    }
  }

  // PDF obligatorio SOLO al crear
  if (!isEditing.value && !form.pdf) {
    showToast("El PDF es obligatorio.", "warning", 3500);
    return;
  }

  ensureCategoryEvaluationDefaults();

  if (!form.mecanismo_calificacion_id) {
    showToast("Selecciona un mecanismo de calificación.", "warning", 3500);
    return;
  }

  // CREATE
  if (!isEditing.value) {
    form.transform((data) => ({
      ...data,
      costo_inscripcion: data.costo_inscripcion ?? 0,
      mecanismo_calificacion_id: data.mecanismo_calificacion_id || defaultMechanismId(),
      unidad_resultado: "",
      orden_ranking: "desc",
      requiere_aprobacion_admin: data.requiere_aprobacion_admin ? 1 : 0,
      visible_publico_en_vivo: data.visible_publico_en_vivo ? 1 : 0,
      permite_edicion_juez: data.permite_edicion_juez ? 1 : 0,
    }));

    form.post("/admin/categorias", {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        if (form.hasErrors || showFormErrors()) {
          isModalOpen.value = true;
          return;
        }

        showToast("Categoría creada", "success", 3000);
        closeModal();

        router.reload({
          preserveScroll: true,
          preserveState: true,
          only: ["categorias", "competenciaId", "flash"],
        });
      },
      onError: () => {
        isModalOpen.value = true;
        showFormErrors();
      },
    });

    return;
  }

  // UPDATE
  const id = Number(selectedId.value);
  if (!Number.isInteger(id) || id <= 0) {
    showToast("No se pudo guardar: ID inválido.", "error", 4500);
    return;
  }

  try {
    form.clearErrors();

    const fd = new FormData();
    fd.append("_method", "PUT");
    fd.append("competencia_id", String(form.competencia_id ?? ""));
    fd.append("nombre", String(form.nombre ?? ""));
    fd.append("costo_inscripcion", String(form.costo_inscripcion ?? 0));
    fd.append("estado", form.estado ? "1" : "0");
    fd.append("mecanismo_calificacion_id", String(form.mecanismo_calificacion_id || defaultMechanismId()));
    fd.append("unidad_resultado", "");
    fd.append("orden_ranking", "desc");
    fd.append("requiere_aprobacion_admin", "1");
    fd.append("visible_publico_en_vivo", "0");
    fd.append("permite_edicion_juez", "1");

    if (form.pdf) fd.append("pdf", form.pdf);
    if (form.imagen) fd.append("imagen", form.imagen);

    await axios.post(`/admin/categorias/${id}`, fd, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    showToast("Categoría actualizada", "success", 3000);
    closeModal();

    router.reload({
      preserveScroll: true,
      preserveState: true,
      only: ["categorias", "competenciaId", "flash"],
    });
  } catch (err) {
    if (err?.response?.status === 422 && err.response.data?.errors) {
      form.setError(err.response.data.errors);
      isModalOpen.value = true;
      showFormErrors();
      return;
    }

    console.error(err);
    showToast("No se pudo actualizar la categoría.", "error", 4500);
    isModalOpen.value = true;
  }
}

// =====================================================
// DELETE
// =====================================================
function requestRemoveRow(cat) {
  const idNum = Number(cat?.id);
  if (!Number.isInteger(idNum) || idNum <= 0) {
    showToast("No se pudo eliminar: ID inválido.", "error", 4500);
    return;
  }

  if (cat?.has_participantes) {
    openAlertModal({
      title: "No se puede eliminar",
      message: "No se puede eliminar esta categoría porque tiene participantes registrados.",
      confirmText: "Entendido",
      variant: "warning",
    });
    return;
  }

  openAlertModal({
    title: "Eliminar categoría",
    message: `¿Seguro que deseas eliminar la categoría "${cat?.nombre ?? ""}"? Esta acción no se puede deshacer.`,
    confirmText: "Sí, eliminar",
    cancelText: "Cancelar",
    variant: "danger",
    onConfirm: () => {
      router.delete(`/admin/categorias/${idNum}`, {
        preserveScroll: true,
        onSuccess: () => {
          showToast("Categoría eliminada", "success", 3000);

          router.reload({
            preserveScroll: true,
            preserveState: true,
            only: ["categorias", "flash", "competenciaId"],
          });
        },
        onError: () => {
          showToast("No se pudo eliminar la categoría.", "error", 4500);
        },
      });
    },
  });
}

function removeRow(cat) {
  return requestRemoveRow(cat);

  const idNum = Number(cat?.id);
  if (!Number.isInteger(idNum) || idNum <= 0) {
    showToast("No se pudo eliminar: ID inválido.", "error", 4500);
    return;
  }

  const ok = confirm(`¿Seguro que deseas eliminar la categoría "${cat?.nombre ?? ""}"?`);
  if (!ok) return;

  router.delete(`/admin/categorias/${idNum}`, {
    preserveScroll: true,
    onSuccess: () => {
      showToast("Categoría eliminada ", "success", 3000);

      router.reload({
        preserveScroll: true,
        preserveState: true,
        only: ["categorias", "flash", "competenciaId"],
      });
    },
    onError: () => {
      showToast("No se pudo eliminar la categoría.", "error", 4500);
    },
  });
}

function viewPdf(cat) {
  if (!cat?.reglamento_url) {
    showToast("Esta categoría aún no tiene PDF.", "warning", 3500);
    return;
  }
  window.open(cat.reglamento_url, "_blank");
}

function viewImage(cat) {
  if (!cat?.imagen_url) {
    showToast("Esta categoría aún no tiene imagen.", "warning", 3500);
    return;
  }
  window.open(cat.imagen_url, "_blank");
}
const rondaTipoLabel = (tipo) => ({
  clasificatoria: "Clasificatoria",
  semifinal: "Semifinal",
  final: "Final",
  libre: "Libre",
}[tipo] ?? "Libre");

const rondaEstadoLabel = (estado) => ({
  borrador: "Borrador",
  activa: "Activa",
  cerrada: "Cerrada",
}[estado] ?? "Borrador");

const rondaEstadoBadge = (estado) => ({
  borrador: "bg-slate-100 text-slate-700 ring-slate-200",
  activa: "bg-emerald-50 text-emerald-700 ring-emerald-200",
  cerrada: "bg-amber-50 text-amber-700 ring-amber-200",
}[estado] ?? "bg-slate-100 text-slate-700 ring-slate-200");

function resetRondaForm() {
  rondaEditingId.value = null;
  rondaForm.value = {
    nombre: "",
    tipo: "libre",
    estado: "borrador",
    fecha_hora: "",
  };
}

async function loadRondas(cat) {
  rondasLoading.value = true;

  try {
    const { data } = await axios.get(`/admin/categorias/${cat.id}/rondas`);
    rondasCategoria.value = data.categoria;
    rondas.value = data.rondas ?? [];
  } catch (error) {
    showToast(error?.response?.data?.message || "No se pudieron cargar las rondas.", "error", 4500);
  } finally {
    rondasLoading.value = false;
  }
}

async function openRondas(cat) {
  rondasCategoria.value = {
    id: cat.id,
    nombre: cat.nombre,
    inscripciones_count: cat.inscripciones_count ?? 0,
  };
  rondas.value = [];
  resetRondaForm();
  isRondasModalOpen.value = true;
  await loadRondas(cat);
}

function closeRondasModal() {
  isRondasModalOpen.value = false;
  rondasCategoria.value = null;
  rondas.value = [];
  resetRondaForm();
}

function editRonda(ronda) {
  rondaEditingId.value = ronda.id;
  rondaForm.value = {
    nombre: ronda.nombre ?? "",
    tipo: ronda.tipo ?? "libre",
    estado: ronda.estado ?? "borrador",
    fecha_hora: ronda.fecha_hora ?? "",
  };
}

async function saveRonda() {
  if (!rondasCategoria.value?.id) return;

  if (!String(rondaForm.value.nombre ?? "").trim()) {
    showToast("Ingresa el nombre de la ronda.", "warning", 3500);
    return;
  }

  rondasSaving.value = true;

  try {
    const payload = { ...rondaForm.value };
    const url = rondaEditingId.value
      ? `/admin/categorias/${rondasCategoria.value.id}/rondas/${rondaEditingId.value}`
      : `/admin/categorias/${rondasCategoria.value.id}/rondas`;

    if (rondaEditingId.value) {
      await axios.put(url, payload);
      showToast("Ronda actualizada", "success", 3000);
    } else {
      await axios.post(url, payload);
      showToast("Ronda creada", "success", 3000);
    }

    resetRondaForm();
    await loadRondas(rondasCategoria.value);
    router.reload({
      preserveScroll: true,
      preserveState: true,
      only: ["categorias", "flash"],
    });
  } catch (error) {
    const errors = error?.response?.data?.errors ?? {};
    showToast(
      errors.nombre?.[0] || errors.tipo?.[0] || errors.estado?.[0] || errors.fecha_hora?.[0] || "No se pudo guardar la ronda.",
      "error",
      4500
    );
  } finally {
    rondasSaving.value = false;
  }
}

function requestRemoveRonda(ronda) {
  if (ronda.has_resultados) {
    openAlertModal({
      title: "No se puede eliminar",
      message: "Esta ronda ya tiene evaluaciones registradas. Puedes cerrarla, pero no eliminarla.",
      confirmText: "Entendido",
      variant: "warning",
    });
    return;
  }

  openAlertModal({
    title: "Eliminar ronda",
    message: `Seguro que deseas eliminar la ronda "${ronda.nombre}"?`,
    confirmText: "Si, eliminar",
    cancelText: "Cancelar",
    variant: "danger",
    onConfirm: async () => {
      try {
        await axios.delete(`/admin/categorias/${rondasCategoria.value.id}/rondas/${ronda.id}`);
        showToast("Ronda eliminada", "success", 3000);
        await loadRondas(rondasCategoria.value);
        router.reload({
          preserveScroll: true,
          preserveState: true,
          only: ["categorias", "flash"],
        });
      } catch (error) {
        const errors = error?.response?.data?.errors ?? {};
        showToast(errors.ronda?.[0] || "No se pudo eliminar la ronda.", "error", 4500);
      }
    },
  });
}
</script>

<template>
  <div class="w-full">
    <div class="mx-auto w-full max-w-[1180px] px-4 sm:px-6 lg:px-4 py-6 space-y-6">
      <!-- Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">Gestión de Categorías</h1>
          <p class="text-sm text-slate-500">Administra las categorías para la competencia</p>

          <!-- Selector competencia -->
          <div class="mt-3 flex flex-col sm:flex-row sm:items-center gap-2">
            <label class="text-sm font-medium text-slate-700">Competencia:</label>

            <div class="flex items-center gap-2 w-full sm:w-[320px]">
              <select
                v-model="selectedCompetenciaId"
                @change="changeCompetencia"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option v-for="c in competenciasProp" :key="c.id" :value="c.id">
                  {{ c.nombre }} <span v-if="c.es_principal">— (Evento principal)</span>
                </option>
              </select>
            </div>
          </div>
        </div>

        <button
          type="button"
          @click="openCreate"
          class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition w-full sm:w-auto"
        >
          <PlusIcon class="w-5 h-5" />
          Nueva Categoría
        </button>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm font-semibold text-slate-900">Categorías Totales</p>
              <p class="text-sm text-slate-500 mt-1">Registradas en el sistema</p>
            </div>
            <div class="h-10 w-10 rounded-xl bg-blue-50 flex items-center justify-center">
              <TagIcon class="w-5 h-5 text-blue-600" />
            </div>
          </div>
          <p class="text-3xl font-semibold text-slate-900 mt-4">{{ stats.total }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm font-semibold text-slate-900">Categorías Activas</p>
              <p class="text-sm text-slate-500 mt-1">Disponibles para inscripciones</p>
            </div>
            <div class="h-10 w-10 rounded-xl bg-emerald-50 flex items-center justify-center">
              <Cog6ToothIcon class="w-5 h-5 text-emerald-600" />
            </div>
          </div>
          <p class="text-3xl font-semibold text-slate-900 mt-4">{{ stats.active }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm font-semibold text-slate-900">Categorías Inactivas</p>
              <p class="text-sm text-slate-500 mt-1">No visibles o no disponibles</p>
            </div>
            <div class="h-10 w-10 rounded-xl bg-slate-100 flex items-center justify-center">
              <NoSymbolIcon class="w-5 h-5 text-slate-600" />
            </div>
          </div>
          <p class="text-3xl font-semibold text-slate-900 mt-4">{{ stats.inactive }}</p>
        </div>
      </div>

      <div class="inline-flex w-full rounded-2xl bg-gray-200 p-1 sm:w-auto">
        <button
          type="button"
          class="inline-flex flex-1 items-center justify-center rounded-xl px-4 py-2 text-sm font-medium transition sm:flex-none"
          :class="activeTab === 'categorias' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'categorias'"
        >
          Categorías
        </button>
        <button
          type="button"
          class="inline-flex flex-1 items-center justify-center rounded-xl px-4 py-2 text-sm font-medium transition sm:flex-none"
          :class="activeTab === 'rondas' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'rondas'"
        >
          Gestión de rondas
        </button>
        <button
          type="button"
          class="inline-flex flex-1 items-center justify-center rounded-xl px-4 py-2 text-sm font-medium transition sm:flex-none"
          :class="activeTab === 'formato' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'formato'"
        >
          Formato de registro del juez
        </button>
      </div>

      <!-- Rondas -->
      <div v-if="activeTab === 'rondas'" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 p-5 sm:p-6">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="text-lg font-semibold text-slate-900">Gestión de rondas</h2>
              <p class="mt-1 text-xs text-slate-500">
                Los jueces solo verán rondas en estado Activa para registrar resultados.
              </p>
              <p class="mt-1 text-sm text-slate-500">
                Define cuántas rondas tendrá cada categoría y activa la ronda que evaluarán los jueces.
              </p>
            </div>
          </div>
        </div>

        <div v-if="categories.length" class="divide-y divide-slate-200">
          <div
            v-for="cat in categories"
            :key="`rondas-${cat.id ?? cat.nombre}`"
            class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
          >
            <div class="min-w-0">
              <p class="font-semibold text-slate-900">{{ cat.nombre }}</p>
              <div class="mt-2 flex flex-wrap gap-2">
                <span
                  class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1"
                  :class="statusBadge(statusLabel(cat.estado))"
                >
                  {{ statusLabel(cat.estado) }}
                </span>
                <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 ring-1 ring-blue-200">
                  {{ cat.rondas_count ?? 0 }} rondas configuradas
                </span>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200">
                  {{ cat.inscripciones_count ?? 0 }} equipos inscritos
                </span>
              </div>
            </div>

            <button
              type="button"
              @click="openRondas(cat)"
              class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
            >
              <Cog6ToothIcon class="h-5 w-5" />
              Gestionar rondas
            </button>
          </div>
        </div>

        <div v-else class="px-6 py-10 text-center text-sm text-slate-500">
          Crea una categoría para poder configurar sus rondas.
        </div>
      </div>

      <!-- Tabla -->
      <div v-if="activeTab === 'categorias'" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-slate-200 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Categorías</h2>
          </div>

          <div class="relative w-full sm:w-[420px]">
            <input
              v-model="search"
              type="text"
              class="w-full pl-11 pr-3 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Buscar categoría..."
            />
            <div class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
              <MagnifyingGlassIcon class="w-5 h-5 text-slate-400" />
            </div>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-white">
              <tr class="text-left text-black border-b border-slate-200">
                <th class="px-6 py-4 font-medium">Categoría</th>
                <th class="px-6 py-4 font-medium">Precio</th>
                <th class="px-6 py-4 font-medium">Estado</th>
                <th class="px-6 py-4 font-medium">Reglamento (PDF)</th>
                <th class="px-6 py-4 font-medium">Imagen</th>
                <th class="px-6 py-4 font-medium">Acciones</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-200">
              <tr v-for="cat in filtered" :key="cat.id ?? cat.nombre" class="hover:bg-slate-50/60">
                <td class="px-6 py-4">
                  <p class="font-semibold text-slate-900 leading-tight">{{ cat.nombre }}</p>
                </td>

                <td class="px-6 py-4">
                  <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                    {{ formatPrice(cat.costo_inscripcion) }}
                  </span>
                </td>

                <td class="px-6 py-4">
                  <span
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ring-1"
                    :class="statusBadge(statusLabel(cat.estado))"
                  >
                    {{ statusLabel(cat.estado) }}
                  </span>
                </td>

                <td class="px-6 py-4">
                  <button
                    type="button"
                    @click.stop.prevent="viewPdf(cat)"
                    class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 transition text-xs"
                  >
                    <DocumentTextIcon class="w-4 h-4 text-slate-700" />
                    Ver PDF
                  </button>
                </td>

                <td class="px-6 py-4">
                  <button
                    type="button"
                    @click.stop.prevent="viewImage(cat)"
                    class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 transition text-xs"
                  >
                    <PhotoIcon class="w-4 h-4 text-slate-700" />
                    Ver imagen
                  </button>
                </td>

                <td class="px-6 py-4">
                  <div class="flex items-center gap-2">
                    <button
                      type="button"
                      @click.stop.prevent="openEdit(cat)"
                      class="h-9 w-9 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 transition flex items-center justify-center"
                      title="Editar"
                    >
                      <PencilSquareIcon class="w-5 h-5 text-slate-700" />
                    </button>

                    <button
                      type="button"
                      @click.stop.prevent="openRondas(cat)"
                      class="inline-flex h-9 items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-3 text-xs font-medium text-blue-700 transition hover:bg-blue-100"
                      title="Rondas"
                    >
                      <Cog6ToothIcon class="w-5 h-5 text-blue-600" />
                      Rondas {{ cat.rondas_count ?? 0 }}
                    </button>

                    <button
                      type="button"
                      @click.stop.prevent="removeRow(cat)"
                      class="h-9 w-9 rounded-xl border border-slate-200 bg-white hover:bg-red-50 transition flex items-center justify-center"
                      title="Eliminar"
                    >
                      <TrashIcon class="w-5 h-5 text-red-600" />
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="filtered.length === 0">
                <td colspan="6" class="px-6 py-10 text-center text-slate-500">
                  No se encontraron categorías con ese criterio.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="false && activeTab === 'formato'" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 p-5 sm:p-6">
          <h2 class="text-lg font-semibold text-slate-900">Formato de registro del juez</h2>
          <p class="mt-1 text-sm text-slate-500">
            Define qué formulario verá el juez para registrar resultados por categoría.
          </p>
        </div>

        <div class="divide-y divide-slate-200">
          <div
            v-for="cat in categories"
            :key="`formato-${cat.id ?? cat.nombre}`"
            class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
          >
            <div>
              <p class="font-semibold text-slate-900">{{ cat.nombre }}</p>
              <p class="mt-1 text-sm text-slate-500">
                Formato actual: {{ officialFormatLabel(cat) }}
              </p>
            </div>

            <span class="inline-flex w-fit items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700 ring-1 ring-amber-200">
              Configuración pausada
            </span>
          </div>

          <div v-if="!categories.length" class="px-6 py-10 text-center text-sm text-slate-500">
            Crea una categoría para configurar su formato de registro.
          </div>
        </div>
      </div>

      <div v-if="activeTab === 'formato'" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 p-5 sm:p-6">
          <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
              <div class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-200">
                <AdjustmentsHorizontalIcon class="h-4 w-4" />
                {{ categories.length }} categorias configurables
              </div>
              <h2 class="mt-3 text-lg font-semibold text-slate-900">Formato de registro del juez</h2>
              <p class="mt-1 max-w-3xl text-sm text-slate-500">
                Selecciona una categoria para abrir su configuracion. Las demas quedan como tarjetas de resumen para navegar rapido cuando existan muchas categorias.
              </p>
            </div>

            <div class="relative w-full xl:w-[360px]">
              <input
                v-model="formatoSearch"
                type="text"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-11 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Buscar categoria..."
              />
              <div class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
                <MagnifyingGlassIcon class="h-5 w-5 text-slate-400" />
              </div>
            </div>
          </div>
        </div>

        <div v-if="categories.length" class="flex flex-col">
          <div v-if="activeFormatoCategory && formatoForms[activeFormatoCategory.id]" class="order-2 p-5 sm:p-6">
            <div class="mb-5 flex flex-col gap-4 border-b border-slate-200 pb-5 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <p class="text-xs font-semibold uppercase text-blue-600">Categoria seleccionada</p>
                <h3 class="mt-1 text-2xl font-semibold text-slate-950">{{ activeFormatoCategory.nombre }}</h3>
                <p class="mt-2 max-w-3xl text-sm text-slate-500">
                  Configuracion actual: {{ officialFormatLabel(activeFormatoCategory) }}
                </p>
              </div>

              <button
                type="button"
                @click="saveFormato(activeFormatoCategory)"
                :disabled="formatoSavingId === activeFormatoCategory.id"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <CheckCircleIcon class="h-5 w-5" />
                {{ formatoSavingId === activeFormatoCategory.id ? "Guardando..." : "Guardar formato" }}
              </button>
            </div>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[320px_minmax(0,1fr)] 2xl:grid-cols-[340px_minmax(0,1fr)]">
              <section class="space-y-3">
                <div>
                  <p class="text-sm font-semibold text-slate-900">Tipo de registro</p>
                  <p class="mt-1 text-xs text-slate-500">Define la base del formulario que vera el juez.</p>
                </div>

                <button
                  v-for="option in registroTypeOptions"
                  :key="`${activeFormatoCategory.id}-${option.value}`"
                  type="button"
                  class="w-full rounded-2xl border px-4 py-4 text-left transition"
                  :class="formatoForms[activeFormatoCategory.id]?.tipo_registro === option.value ? 'border-blue-300 bg-blue-50 ring-2 ring-blue-100' : 'border-slate-200 bg-white hover:bg-slate-50'"
                  @click="applyRegistroType(activeFormatoCategory, option.value)"
                >
                  <span class="block text-sm font-semibold text-slate-900">{{ option.label }}</span>
                  <span class="mt-1 block text-xs leading-5 text-slate-500">{{ option.description }}</span>
                </button>
              </section>

              <section class="min-w-0 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="grid grid-cols-1 gap-3 xl:grid-cols-2">
                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Modalidad de competencia</label>
                    <select
                      v-model="formatoForms[activeFormatoCategory.id].modalidad_competencia"
                      class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      @change="applyModalidad(activeFormatoCategory, formatoForms[activeFormatoCategory.id].modalidad_competencia)"
                    >
                      <option v-for="option in modalidadOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                      </option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">
                      {{ formatoOptionDescription(modalidadOptions, formatoForms[activeFormatoCategory.id].modalidad_competencia) }}
                    </p>
                  </div>

                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Esquema de jueces</label>
                    <select
                      v-model="formatoForms[activeFormatoCategory.id].esquema_jueces"
                      class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      @change="applyEsquemaJueces(activeFormatoCategory, formatoForms[activeFormatoCategory.id].esquema_jueces)"
                    >
                      <option v-for="option in esquemaJuecesOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                      </option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">
                      {{ formatoOptionDescription(esquemaJuecesOptions, formatoForms[activeFormatoCategory.id].esquema_jueces) }}
                    </p>
                  </div>

                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Plantilla de resultado</label>
                    <select
                      v-model="formatoForms[activeFormatoCategory.id].plantilla_resultado"
                      class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      @change="applyResultadoTemplate(activeFormatoCategory, formatoForms[activeFormatoCategory.id].plantilla_resultado)"
                    >
                      <option v-for="option in templateOptionsForTipo(formatoForms[activeFormatoCategory.id].tipo_registro)" :key="option.value" :value="option.value">
                        {{ option.label }}
                      </option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">
                      {{ formatoOptionDescription(templateOptionsForTipo(formatoForms[activeFormatoCategory.id].tipo_registro), formatoForms[activeFormatoCategory.id].plantilla_resultado) }}
                    </p>
                  </div>

                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Unidad</label>
                    <input
                      v-model="formatoForms[activeFormatoCategory.id].unidad_resultado"
                      class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="Ej: s, pts"
                    />
                  </div>

                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Orden del ranking</label>
                    <select
                      v-model="formatoForms[activeFormatoCategory.id].orden_ranking"
                      class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                      <option value="desc">Mayor valor primero</option>
                      <option value="asc">Menor valor primero</option>
                    </select>
                  </div>
                </div>

                <div class="mt-5">
                  <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                      <p class="text-sm font-semibold text-slate-900">Campos que vera el juez</p>
                      <p class="text-xs text-slate-500">
                        {{ isGolesFormato(formatoForms[activeFormatoCategory.id])
                          ? "Vista previa del marcador que vera el juez."
                          : isTiempoFormato(formatoForms[activeFormatoCategory.id])
                            ? "Vista previa del cronometro que vera el juez."
                          : "Ajusta la tabla cuando el tipo sea Tabla de evaluacion." }}
                      </p>
                    </div>
                    <button
                      v-if="formatoForms[activeFormatoCategory.id].tipo_registro === 'tabla_evaluacion'"
                      type="button"
                      @click="addRubricaCriterion(activeFormatoCategory)"
                      class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 transition hover:bg-slate-50"
                    >
                      Agregar criterio
                    </button>
                  </div>

                  <div
                    v-if="isGolesFormato(formatoForms[activeFormatoCategory.id])"
                    class="max-w-full overflow-hidden rounded-2xl border border-slate-800 bg-slate-950 text-white shadow-sm"
                  >
                    <div class="h-1.5 bg-blue-600"></div>

                    <div class="px-5 py-5 sm:px-6">
                      <div class="mb-5 flex items-center justify-between gap-3 text-sm">
                        <span class="text-slate-300">Vista previa del marcador</span>
                        <span class="rounded-full bg-slate-800 px-3 py-1 font-semibold text-slate-100">
                          En registro
                        </span>
                      </div>

                      <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3 sm:gap-6">
                        <div class="min-w-0 text-center">
                          <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-800 text-lg font-black text-blue-200 ring-1 ring-slate-700">
                            A
                          </div>
                          <p class="mt-3 truncate text-base font-semibold text-white">Equipo A</p>
                          <p class="mt-1 truncate text-xs text-slate-400">Institucion A</p>
                        </div>

                        <div class="flex items-center justify-center gap-2 sm:gap-4">
                          <div class="flex h-16 w-20 items-center justify-center rounded-2xl border border-slate-700 bg-slate-900 text-4xl font-bold text-white sm:h-20 sm:w-24 sm:text-5xl">
                            0
                          </div>
                          <span class="text-3xl font-bold text-slate-400 sm:text-5xl">-</span>
                          <div class="flex h-16 w-20 items-center justify-center rounded-2xl border border-slate-700 bg-slate-900 text-4xl font-bold text-white sm:h-20 sm:w-24 sm:text-5xl">
                            0
                          </div>
                        </div>

                        <div class="min-w-0 text-center">
                          <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-800 text-lg font-black text-rose-200 ring-1 ring-slate-700">
                            B
                          </div>
                          <p class="mt-3 truncate text-base font-semibold text-white">Equipo B</p>
                          <p class="mt-1 truncate text-xs text-slate-400">Institucion B</p>
                        </div>
                      </div>

                      <div class="mt-4 rounded-xl bg-slate-900/80 px-3 py-2 text-xs text-slate-300">
                        El juez registrara los goles desde esta vista. Los campos se guardan como goles a favor y goles en contra.
                      </div>
                    </div>
                  </div>

                  <div
                    v-else-if="isTiempoFormato(formatoForms[activeFormatoCategory.id])"
                    class="max-w-full overflow-hidden rounded-2xl border border-blue-500/30 bg-black text-green-400 shadow-sm"
                  >
                    <div class="h-1.5 bg-blue-600"></div>
                    <div class="px-5 py-6 sm:px-6">
                      <div class="mb-4 flex items-center justify-between gap-3 text-sm">
                        <span class="font-semibold text-slate-200">Vista previa del cronometro</span>
                        <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-slate-200 ring-1 ring-white/15">
                          Tiempo final
                        </span>
                      </div>
                      <div class="chrono-preview rounded-2xl border border-white/10 bg-[#101010] px-4 py-6 text-slate-100 sm:px-6">
                        <div class="flex min-w-0 items-end justify-center gap-4 sm:gap-8">
                          <div class="flex items-end gap-1">
                            <span class="chrono-preview-number">00</span>
                            <span class="chrono-preview-unit">h</span>
                          </div>
                          <div class="flex items-end gap-1">
                            <span class="chrono-preview-number">07</span>
                            <span class="chrono-preview-unit">m</span>
                          </div>
                          <div class="flex items-end gap-1">
                            <span class="chrono-preview-number">35</span>
                            <span class="chrono-preview-unit">s</span>
                          </div>
                        </div>
                      </div>
                      <div class="mt-4 rounded-xl bg-white/5 px-3 py-2 text-xs text-slate-300 ring-1 ring-white/10">
                        El juez digitara solo numeros. Ejemplo: 735 se muestra como 00h 07m 35s.
                      </div>
                    </div>
                  </div>

                  <div
                    v-else-if="isTablaIndividualFormato(formatoForms[activeFormatoCategory.id])"
                    class="space-y-4"
                  >
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                      <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-sm font-semibold text-slate-900">Criterios individuales</p>
                        <p class="mt-1 text-xs text-slate-500">Define el valor por unidad y marca si el criterio resta como penalizacion.</p>
                      </div>

                      <div class="space-y-2 p-3">
                        <div
                          v-for="(field, index) in formatoForms[activeFormatoCategory.id].campos_json"
                          :key="`${activeFormatoCategory.id}-individual-${field.key}-${index}`"
                        >
                          <div
                            v-if="field.key !== 'observaciones'"
                            class="grid grid-cols-1 gap-2 rounded-xl border border-slate-200 bg-white p-3 lg:grid-cols-[minmax(0,1fr)_110px_130px_90px_auto]"
                          >
                            <input
                              v-model="field.label"
                              class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500"
                              :class="field.es_penalizacion ? 'text-red-600' : 'text-slate-900'"
                              placeholder="Criterio"
                            />
                            <input
                              v-model="field.valor_unitario"
                              type="number"
                              min="0"
                              step="0.01"
                              class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Valor"
                            />
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700">
                              <input v-model="field.es_penalizacion" type="checkbox" class="rounded border-slate-300 text-red-600 focus:ring-red-500" />
                              Penaliza
                            </label>
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700">
                              <input v-model="field.required" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                              Req.
                            </label>
                            <button
                              type="button"
                              @click="removeRubricaCriterion(activeFormatoCategory, index)"
                              class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50"
                            >
                              Quitar
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-[#1f1f1f] text-white shadow-sm">
                      <div class="grid grid-cols-[1.15fr_1fr] items-center border-b border-white/10 px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-200">
                        <span>Estadísticas</span>
                        <span>Equipo</span>
                      </div>

                      <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                          <thead>
                            <tr class="text-xs uppercase tracking-wide text-slate-300">
                              <th class="px-3 py-3 text-center">Criterio</th>
                              <th class="px-3 py-3 text-center">Valor</th>
                              <th class="px-3 py-3 text-center text-emerald-300">Cantidad</th>
                              <th class="px-3 py-3 text-center text-yellow-300">Puntaje</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr
                              v-for="(field, index) in criterioFields(formatoForms[activeFormatoCategory.id]).filter((item) => !item.es_penalizacion)"
                              :key="`individual-preview-${field.key}-${index}`"
                              class="border-t border-white/5"
                            >
                              <td class="px-3 py-3 text-center">
                                <p class="font-semibold text-white">{{ field.label }}</p>
                                <p class="text-xs text-slate-400">Suma al subtotal</p>
                              </td>
                              <td class="px-3 py-3 text-center text-slate-200">x {{ Number(field.valor_unitario || 0) }}</td>
                              <td class="px-3 py-3 text-center">
                                <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-emerald-500 px-2 py-1 font-bold text-white">
                                  {{ sampleCantidad(index, 'B') }}
                                </span>
                              </td>
                              <td class="px-3 py-3 text-center">
                                <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                                  {{ criterioScore(field, index, 'B') }}
                                </span>
                              </td>
                            </tr>
                            <tr class="border-t-4 border-red-600 bg-[#242424] text-sm font-bold">
                              <td colspan="3" class="px-3 py-4 text-center text-white">Subtotal</td>
                              <td class="px-3 py-4 text-center text-yellow-100">
                                {{ individualSubtotal(formatoForms[activeFormatoCategory.id]) }}
                              </td>
                            </tr>
                            <tr
                              v-for="(field, index) in criterioFields(formatoForms[activeFormatoCategory.id]).filter((item) => item.es_penalizacion)"
                              :key="`individual-preview-penalty-${field.key}-${index}`"
                              class="border-t border-white/5"
                            >
                              <td class="px-3 py-3 text-center">
                                <p class="font-semibold text-red-400">{{ field.label }}</p>
                                <p class="text-xs text-red-300">Resta al subtotal</p>
                              </td>
                              <td class="px-3 py-3 text-center text-slate-200">x {{ Number(field.valor_unitario || 0) }}</td>
                              <td class="px-3 py-3 text-center">
                                <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-emerald-500 px-2 py-1 font-bold text-white">
                                  {{ sampleCantidad(index, 'B') }}
                                </span>
                              </td>
                              <td class="px-3 py-3 text-center">
                                <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                                  {{ criterioScore(field, index, 'B') }}
                                </span>
                              </td>
                            </tr>
                          </tbody>
                          <tfoot>
                            <tr class="border-t-4 border-red-600 bg-[#242424] text-base font-bold">
                              <td colspan="3" class="px-3 py-5 text-center uppercase tracking-wide text-white">
                                Resultado final
                              </td>
                              <td class="px-3 py-5 text-center text-yellow-100">
                                {{ individualTotal(formatoForms[activeFormatoCategory.id]) }}
                              </td>
                            </tr>
                          </tfoot>
                        </table>
                      </div>
                    </div>
                  </div>

                  <div
                    v-else-if="isTablaEnfrentamientoFormato(formatoForms[activeFormatoCategory.id])"
                    class="space-y-4"
                  >
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                      <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-sm font-semibold text-slate-900">Criterios del enfrentamiento</p>
                        <p class="mt-1 text-xs text-slate-500">Define el valor por unidad y marca si el criterio resta como penalizacion.</p>
                      </div>

                      <div class="space-y-2 p-3">
                        <div
                          v-for="(field, index) in formatoForms[activeFormatoCategory.id].campos_json"
                          :key="`${activeFormatoCategory.id}-fight-${field.key}-${index}`"
                        >
                          <div
                            v-if="field.key !== 'observaciones'"
                            class="grid grid-cols-1 gap-2 rounded-xl border border-slate-200 bg-white p-3 lg:grid-cols-[minmax(0,1fr)_110px_130px_90px_auto]"
                          >
                            <input
                              v-model="field.label"
                              class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500"
                              :class="field.es_penalizacion ? 'text-red-600' : 'text-slate-900'"
                              placeholder="Criterio"
                            />
                            <input
                              v-model="field.valor_unitario"
                              type="number"
                              min="0"
                              step="0.01"
                              class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Valor"
                            />
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700">
                              <input v-model="field.es_penalizacion" type="checkbox" class="rounded border-slate-300 text-red-600 focus:ring-red-500" />
                              Penaliza
                            </label>
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700">
                              <input v-model="field.required" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                              Req.
                            </label>
                            <button
                              type="button"
                              @click="removeRubricaCriterion(activeFormatoCategory, index)"
                              class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50"
                            >
                              Quitar
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-[#1f1f1f] text-white shadow-sm">
                      <div class="grid grid-cols-[1fr_1.15fr_1fr] items-center border-b border-white/10 px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-200">
                        <span>Equipo A</span>
                        <span>Estadísticas</span>
                        <span>Equipo B</span>
                      </div>

                      <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                          <thead>
                            <tr class="text-xs uppercase tracking-wide text-slate-300">
                              <th class="px-3 py-3 text-center text-yellow-300">Puntaje</th>
                              <th class="px-3 py-3 text-center text-emerald-300">Cantidad</th>
                              <th class="px-3 py-3 text-center">Criterio</th>
                              <th class="px-3 py-3 text-center">Valor</th>
                              <th class="px-3 py-3 text-center text-emerald-300">Cantidad</th>
                              <th class="px-3 py-3 text-center text-yellow-300">Puntaje</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr
                              v-for="(field, index) in criterioFields(formatoForms[activeFormatoCategory.id]).filter((item) => !item.es_penalizacion)"
                              :key="`fight-preview-${field.key}-${index}`"
                              class="border-t border-white/5"
                            >
                              <td class="px-3 py-2 text-center">
                                <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                                  {{ criterioScore(field, index, 'A') }}
                                </span>
                              </td>
                              <td class="px-3 py-2 text-center">
                                <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-emerald-500 px-2 py-1 font-bold text-white">
                                  {{ sampleCantidad(index, 'A') }}
                                </span>
                              </td>
                              <td class="px-3 py-2 text-center">
                                <p class="font-semibold" :class="field.es_penalizacion ? 'text-red-400' : 'text-white'">{{ field.label }}</p>
                                <p class="text-xs" :class="field.es_penalizacion ? 'text-red-300' : 'text-slate-400'">
                                  {{ field.es_penalizacion ? 'Resta al subtotal' : 'Suma al subtotal' }}
                                </p>
                              </td>
                              <td class="px-3 py-2 text-center text-slate-200">x {{ Number(field.valor_unitario || 0) }}</td>
                              <td class="px-3 py-2 text-center">
                                <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-emerald-500 px-2 py-1 font-bold text-white">
                                  {{ sampleCantidad(index, 'B') }}
                                </span>
                              </td>
                              <td class="px-3 py-2 text-center">
                                <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                                  {{ criterioScore(field, index, 'B') }}
                                </span>
                              </td>
                            </tr>
                            <tr class="border-t-4 border-red-600 bg-[#242424] text-sm font-bold">
                              <td colspan="2" class="px-3 py-3 text-center text-yellow-100">
                                {{ enfrentamientoSubtotal(formatoForms[activeFormatoCategory.id], 'A') }}
                              </td>
                              <td colspan="2" class="px-3 py-3 text-center text-white">Subtotal</td>
                              <td colspan="2" class="px-3 py-3 text-center text-yellow-100">
                                {{ enfrentamientoSubtotal(formatoForms[activeFormatoCategory.id], 'B') }}
                              </td>
                            </tr>
                            <tr
                              v-for="(field, index) in criterioFields(formatoForms[activeFormatoCategory.id]).filter((item) => item.es_penalizacion)"
                              :key="`fight-preview-penalty-${field.key}-${index}`"
                              class="border-t border-white/5"
                            >
                              <td class="px-3 py-2 text-center">
                                <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                                  {{ criterioScore(field, index, 'A') }}
                                </span>
                              </td>
                              <td class="px-3 py-2 text-center">
                                <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-emerald-500 px-2 py-1 font-bold text-white">
                                  {{ sampleCantidad(index, 'A') }}
                                </span>
                              </td>
                              <td class="px-3 py-2 text-center">
                                <p class="font-semibold text-red-400">{{ field.label }}</p>
                                <p class="text-xs text-red-300">Resta al subtotal</p>
                              </td>
                              <td class="px-3 py-2 text-center text-slate-200">x {{ Number(field.valor_unitario || 0) }}</td>
                              <td class="px-3 py-2 text-center">
                                <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-emerald-500 px-2 py-1 font-bold text-white">
                                  {{ sampleCantidad(index, 'B') }}
                                </span>
                              </td>
                              <td class="px-3 py-2 text-center">
                                <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                                  {{ criterioScore(field, index, 'B') }}
                                </span>
                              </td>
                            </tr>
                          </tbody>
                          <tfoot>
                            <tr class="border-t-4 border-red-600 bg-[#242424] text-base font-bold">
                              <td colspan="2" class="px-3 py-4 text-center text-yellow-100">
                                {{ enfrentamientoTotal(formatoForms[activeFormatoCategory.id], 'A') }}
                              </td>
                              <td colspan="2" class="px-3 py-4 text-center uppercase tracking-wide text-white">
                                Resultado final
                              </td>
                              <td colspan="2" class="px-3 py-4 text-center text-yellow-100">
                                {{ enfrentamientoTotal(formatoForms[activeFormatoCategory.id], 'B') }}
                              </td>
                            </tr>
                          </tfoot>
                        </table>
                      </div>
                    </div>
                  </div>

                  <div v-else class="space-y-2">
                    <div
                      v-for="(field, index) in formatoForms[activeFormatoCategory.id].campos_json"
                      :key="`${activeFormatoCategory.id}-${field.key}-${index}`"
                      class="grid grid-cols-1 gap-2 rounded-xl border border-slate-200 bg-white p-3 sm:grid-cols-[1fr_110px_90px_auto]"
                    >
                      <input
                        v-model="field.label"
                        class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-slate-100"
                        placeholder="Nombre del campo"
                        :disabled="field.type === 'textarea' || field.type === 'select' || field.type === 'duration'"
                      />
                      <input
                        v-model="field.max"
                        type="number"
                        min="0"
                        step="0.01"
                        class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-slate-100"
                        placeholder="Max."
                        :disabled="field.type !== 'number'"
                      />
                      <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700">
                        <input v-model="field.required" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                        Req.
                      </label>
                      <button
                        type="button"
                        @click="removeRubricaCriterion(activeFormatoCategory, index)"
                        class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="formatoForms[activeFormatoCategory.id].tipo_registro !== 'tabla_evaluacion' || ['observaciones'].includes(field.key)"
                      >
                        Quitar
                      </button>
                    </div>
                  </div>
                </div>
              </section>
            </div>
          </div>

          <div v-else class="px-6 py-10 text-center text-sm text-slate-500">
            Selecciona una categoria para configurar su formato.
          </div>

          <div class="order-1 border-b border-slate-200 bg-slate-50/70">
            <div class="format-category-scroll overflow-x-auto p-4">
            <div v-if="filteredFormatoCategories.length" class="flex min-w-max gap-3">
              <button
                v-for="cat in filteredFormatoCategories"
                :key="`formato-card-${cat.id ?? cat.nombre}`"
                type="button"
                class="group min-h-[180px] w-[290px] shrink-0 rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md sm:w-[320px]"
                :class="Number(activeFormatoCategoryId) === Number(cat.id) ? 'border-blue-300 ring-2 ring-blue-100' : 'border-slate-200'"
                @click="selectFormatoCategory(cat)"
              >
                <div class="flex items-start gap-3">
                  <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-950">{{ cat.nombre }}</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ formatoConfigSummary(cat).tipo }}</p>
                  </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                  <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200">
                    {{ formatoConfigSummary(cat).modalidad }}
                  </span>
                  <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 ring-1 ring-indigo-200">
                    {{ formatoConfigSummary(cat).campos }} campos
                  </span>
                  <span
                    class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 ring-1 ring-blue-200"
                  >
                    {{ formatoConfigSummary(cat).plantilla }}
                  </span>
                </div>

                <p class="mt-3 line-clamp-2 text-xs leading-5 text-slate-500">
                  {{ formatoConfigSummary(cat).esquema }}
                </p>
              </button>
            </div>

            <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-10 text-center text-sm text-slate-500">
              No se encontraron categorias con ese criterio.
            </div>
            </div>
          </div>
        </div>

        <div v-else class="px-6 py-10 text-center text-sm text-slate-500">
          Crea una categoria para configurar su formato de registro.
        </div>
      </div>

      <div v-if="false && activeTab === 'formato'" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 p-5 sm:p-6">
          <h2 class="text-lg font-semibold text-slate-900">Formato de registro del juez</h2>
          <p class="mt-1 text-sm text-slate-500">
            Define que formulario vera el juez para registrar resultados por categoria.
          </p>
        </div>

        <div class="divide-y divide-slate-200">
          <div
            v-for="cat in categories"
            :key="`formato-activo-${cat.id ?? cat.nombre}`"
            class="grid grid-cols-1 gap-5 px-5 py-5 lg:grid-cols-[0.85fr_1.4fr] sm:px-6"
          >
            <div class="space-y-3">
              <p class="font-semibold text-slate-900">{{ cat.nombre }}</p>
              <p class="text-sm text-slate-500">
                Configuracion actual: {{ officialFormatLabel(cat) }}
              </p>

              <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo de registro</p>
                <button
                  v-for="option in registroTypeOptions"
                  :key="`${cat.id}-${option.value}`"
                  type="button"
                  class="w-full rounded-xl border px-4 py-3 text-left transition"
                  :class="formatoForms[cat.id]?.tipo_registro === option.value ? 'border-blue-300 bg-blue-50 ring-2 ring-blue-100' : 'border-slate-200 bg-white hover:bg-slate-50'"
                  @click="applyRegistroType(cat, option.value)"
                >
                  <span class="block text-sm font-semibold text-slate-900">{{ option.label }}</span>
                  <span class="mt-1 block text-xs leading-5 text-slate-500">{{ option.description }}</span>
                </button>
              </div>
            </div>

            <div v-if="formatoForms[cat.id]" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
              <div class="grid grid-cols-1 gap-3 xl:grid-cols-2">
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Modalidad de competencia</label>
                  <select
                    v-model="formatoForms[cat.id].modalidad_competencia"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    @change="applyModalidad(cat, formatoForms[cat.id].modalidad_competencia)"
                  >
                    <option v-for="option in modalidadOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                  <p class="mt-1 text-xs text-slate-500">
                    {{ modalidadOptions.find((item) => item.value === formatoForms[cat.id].modalidad_competencia)?.description }}
                  </p>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Esquema de jueces</label>
                  <select
                    v-model="formatoForms[cat.id].esquema_jueces"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    @change="applyEsquemaJueces(cat, formatoForms[cat.id].esquema_jueces)"
                  >
                    <option v-for="option in esquemaJuecesOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                  <p class="mt-1 text-xs text-slate-500">
                    {{ esquemaJuecesOptions.find((item) => item.value === formatoForms[cat.id].esquema_jueces)?.description }}
                  </p>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Unidad</label>
                  <input
                    v-model="formatoForms[cat.id].unidad_resultado"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Ej: s, pts"
                  />
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Orden del ranking</label>
                  <select
                    v-model="formatoForms[cat.id].orden_ranking"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="desc">Mayor valor primero</option>
                    <option value="asc">Menor valor primero</option>
                  </select>
                </div>
              </div>

              <div class="mt-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold text-slate-900">Campos que vera el juez</p>
                    <p class="text-xs text-slate-500">
                      {{ isGolesFormato(formatoForms[cat.id])
                        ? "Vista previa del marcador que vera el juez."
                        : isTiempoFormato(formatoForms[cat.id])
                          ? "Vista previa del cronometro que vera el juez."
                        : "Ajusta la tabla cuando el tipo sea Tabla de evaluacion." }}
                    </p>
                  </div>
                  <button
                    v-if="formatoForms[cat.id].tipo_registro === 'tabla_evaluacion'"
                    type="button"
                    @click="addRubricaCriterion(cat)"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 transition hover:bg-slate-50"
                  >
                    Agregar criterio
                  </button>
                </div>

                <div
                  v-if="isGolesFormato(formatoForms[cat.id])"
                  class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-950 text-white shadow-sm"
                >
                  <div class="h-1.5 bg-blue-600"></div>

                  <div class="px-5 py-5 sm:px-6">
                    <div class="mb-5 flex items-center justify-between gap-3 text-sm">
                      <span class="text-slate-300">Vista previa del marcador</span>
                      <span class="rounded-full bg-slate-800 px-3 py-1 font-semibold text-slate-100">
                        En registro
                      </span>
                    </div>

                    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 sm:gap-6">
                      <div class="min-w-0 text-center">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-800 text-lg font-black text-blue-200 ring-1 ring-slate-700">
                          A
                        </div>
                        <p class="mt-3 truncate text-base font-semibold text-white">Equipo A</p>
                        <p class="mt-1 truncate text-xs text-slate-400">Institucion A</p>
                      </div>

                      <div class="flex items-center justify-center gap-2 sm:gap-4">
                        <div class="flex h-16 w-20 items-center justify-center rounded-2xl border border-slate-700 bg-slate-900 text-4xl font-bold text-white sm:h-20 sm:w-24 sm:text-5xl">
                          0
                        </div>
                        <span class="text-3xl font-bold text-slate-400 sm:text-5xl">-</span>
                        <div class="flex h-16 w-20 items-center justify-center rounded-2xl border border-slate-700 bg-slate-900 text-4xl font-bold text-white sm:h-20 sm:w-24 sm:text-5xl">
                          0
                        </div>
                      </div>

                      <div class="min-w-0 text-center">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-800 text-lg font-black text-rose-200 ring-1 ring-slate-700">
                          B
                        </div>
                        <p class="mt-3 truncate text-base font-semibold text-white">Equipo B</p>
                        <p class="mt-1 truncate text-xs text-slate-400">Institucion B</p>
                      </div>
                    </div>

                    <div class="mt-4 rounded-xl bg-slate-900/80 px-3 py-2 text-xs text-slate-300">
                      El juez registrara los goles desde esta vista. Los campos se guardan como goles a favor y goles en contra.
                    </div>
                  </div>
                </div>

                <div
                  v-else-if="isTiempoFormato(formatoForms[cat.id])"
                  class="overflow-hidden rounded-2xl border border-blue-500/30 bg-black text-green-400 shadow-sm"
                >
                  <div class="h-1.5 bg-blue-600"></div>
                  <div class="px-5 py-6 sm:px-6">
                    <div class="mb-4 flex items-center justify-between gap-3 text-sm">
                      <span class="font-semibold text-slate-200">Vista previa del cronometro</span>
                      <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-slate-200 ring-1 ring-white/15">
                        Tiempo final
                      </span>
                    </div>
                    <div class="chrono-preview rounded-2xl border border-white/10 bg-[#101010] px-4 py-6 text-slate-100 sm:px-6">
                      <div class="flex items-end justify-center gap-4 sm:gap-8">
                        <div class="flex items-end gap-1">
                          <span class="chrono-preview-number">00</span>
                          <span class="chrono-preview-unit">h</span>
                        </div>
                        <div class="flex items-end gap-1">
                          <span class="chrono-preview-number">07</span>
                          <span class="chrono-preview-unit">m</span>
                        </div>
                        <div class="flex items-end gap-1">
                          <span class="chrono-preview-number">35</span>
                          <span class="chrono-preview-unit">s</span>
                        </div>
                      </div>
                    </div>
                    <div class="mt-4 rounded-xl bg-white/5 px-3 py-2 text-xs text-slate-300 ring-1 ring-white/10">
                      El juez digitara solo numeros. Ejemplo: 735 se muestra como 00h 07m 35s.
                    </div>
                  </div>
                </div>

                <div v-else class="space-y-2">
                  <div
                    v-for="(field, index) in formatoForms[cat.id].campos_json"
                    :key="`${cat.id}-${field.key}-${index}`"
                    class="grid grid-cols-1 gap-2 rounded-xl border border-slate-200 bg-white p-3 sm:grid-cols-[1fr_110px_90px_auto]"
                  >
                    <input
                      v-model="field.label"
                      class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-slate-100"
                      placeholder="Nombre del campo"
                      :disabled="field.type === 'textarea' || field.type === 'select' || field.type === 'duration'"
                    />
                    <input
                      v-model="field.max"
                      type="number"
                      min="0"
                      step="0.01"
                      class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-slate-100"
                      placeholder="Max."
                      :disabled="field.type !== 'number'"
                    />
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700">
                      <input v-model="field.required" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                      Req.
                    </label>
                    <button
                      type="button"
                      @click="removeRubricaCriterion(cat, index)"
                      class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                      :disabled="formatoForms[cat.id].tipo_registro !== 'tabla_evaluacion' || ['observaciones'].includes(field.key)"
                    >
                      Quitar
                    </button>
                  </div>
                </div>
              </div>

              <div class="mt-4 flex justify-end">
                <button
                  type="button"
                  @click="saveFormato(cat)"
                  :disabled="formatoSavingId === cat.id"
                  class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  <CheckCircleIcon class="h-5 w-5" />
                  {{ formatoSavingId === cat.id ? "Guardando..." : "Guardar formato" }}
                </button>
              </div>
            </div>
          </div>

          <div v-if="!categories.length" class="px-6 py-10 text-center text-sm text-slate-500">
            Crea una categoria para configurar su formato de registro.
          </div>
        </div>
      </div>

      <!-- MODAL -->
      <Teleport to="body">
        <div v-if="isModalOpen" class="fixed inset-0 z-[9999]">
          <div class="absolute inset-0 bg-black/40" @click="closeModal"></div>

          <div class="relative h-full w-full grid place-items-center p-4 sm:p-6">
            <div class="w-full max-w-2xl rounded-2xl bg-white border border-slate-200 shadow-xl overflow-hidden max-h-[90vh] flex flex-col">
              <div class="p-5 border-b border-slate-200 flex items-start justify-between gap-4">
                <h2 class="text-lg font-semibold text-slate-900">
                  {{ isEditing ? "Editar Categoría" : "Crear Nueva Categoría" }}
                </h2>

                <button
                  type="button"
                  class="h-9 w-9 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-center justify-center"
                  @click="closeModal"
                >
                  <XMarkIcon class="w-5 h-5 text-slate-600" />
                </button>
              </div>

              <div class="p-5 space-y-4 overflow-y-auto">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Nombre</label>
                  <input
                    v-model="form.nombre"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Ej: Seguidor de Línea"
                  />
                  <p v-if="form.errors.nombre" class="text-xs text-red-600 mt-1">{{ form.errors.nombre }}</p>
                  <p v-if="form.errors.nombre_key" class="text-xs text-red-600 mt-1">{{ form.errors.nombre_key }}</p>
                  <p v-if="form.errors.estado" class="text-xs text-red-600 mt-1">{{ form.errors.estado }}</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Costo de inscripción</label>
                  <input
                    v-model="form.costo_inscripcion"
                    type="number"
                    step="0.01"
                    min="0"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Ej: 5.00"
                  />
                  <p v-if="form.errors.costo_inscripcion" class="text-xs text-red-600 mt-1">
                    {{ form.errors.costo_inscripcion }}
                  </p>
                </div>

                <div v-if="false" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Mecanismo de calificación</label>
                    <select
                      v-model="form.mecanismo_calificacion_id"
                      class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                      <option disabled value="">Selecciona un mecanismo</option>
                      <option v-for="item in mecanismosCalificacion" :key="item.id" :value="item.id">
                        {{ item.nombre }}
                      </option>
                    </select>
                    <p v-if="form.errors.mecanismo_calificacion_id" class="text-xs text-red-600 mt-1">
                      {{ form.errors.mecanismo_calificacion_id }}
                    </p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Unidad de resultado</label>
                    <input
                      v-model="form.unidad_resultado"
                      class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="Ej: s, pts, victorias"
                    />
                    <p v-if="form.errors.unidad_resultado" class="text-xs text-red-600 mt-1">
                      {{ form.errors.unidad_resultado }}
                    </p>
                  </div>
                </div>

                <div v-if="false" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Orden del ranking</label>
                    <select
                      v-model="form.orden_ranking"
                      class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                      <option value="desc">Mayor valor primero</option>
                      <option value="asc">Menor valor primero</option>
                    </select>
                    <p v-if="form.errors.orden_ranking" class="text-xs text-red-600 mt-1">
                      {{ form.errors.orden_ranking }}
                    </p>
                  </div>

                  <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-sm font-medium text-slate-800">{{ mechanismPreview.title }}</p>
                    <ul class="mt-2 space-y-1 text-xs text-slate-600">
                      <li v-for="line in mechanismPreview.lines" :key="line">- {{ line }}</li>
                    </ul>
                  </div>
                </div>

                <div v-if="false" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                  <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <input v-model="form.requiere_aprobacion_admin" type="checkbox" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                    <span>
                      <span class="block text-sm font-medium text-slate-800">Requiere aprobación admin</span>
                      <span class="block text-xs text-slate-600">El resultado no se considera oficial hasta revisión.</span>
                    </span>
                  </label>

                  <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <input v-model="form.visible_publico_en_vivo" type="checkbox" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                    <span>
                      <span class="block text-sm font-medium text-slate-800">Visible al público en vivo</span>
                      <span class="block text-xs text-slate-600">Permite mostrar avances antes del cierre final.</span>
                    </span>
                  </label>

                  <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <input v-model="form.permite_edicion_juez" type="checkbox" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                    <span>
                      <span class="block text-sm font-medium text-slate-800">Permitir edición del juez</span>
                      <span class="block text-xs text-slate-600">El juez podrá corregir sus registros mientras la ronda siga abierta.</span>
                    </span>
                  </label>
                </div>

                <div class="flex items-center justify-between rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
                  <div>
                    <p class="text-sm font-medium text-slate-800">Categoría Activa</p>
                    <p class="text-xs text-slate-600">Permitir inscripciones</p>
                  </div>

                  <button
                    type="button"
                    @click="onToggleEstado"
                    class="w-12 h-7 rounded-full transition relative"
                    :class="form.estado ? 'bg-emerald-500' : 'bg-slate-300'"
                  >
                    <span
                      class="absolute top-0.5 h-6 w-6 rounded-full bg-white transition"
                      :class="form.estado ? 'left-6' : 'left-0.5'"
                    />
                  </button>
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Reglamento oficial (PDF)</label>
                  <input
                    ref="pdfInput"
                    type="file"
                    accept="application/pdf"
                    @change="onPickPDF"
                    class="block w-full text-sm text-slate-700 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-slate-900 file:text-white hover:file:bg-slate-800"
                  />
                  <p v-if="isEditing && editingPdfUrl" class="text-xs text-slate-500 mt-2">
                    (Si no sube uno nuevo, se mantiene el actual)
                  </p>
                  <p v-if="form.pdf" class="text-xs text-slate-600 mt-2">
                    Seleccionado: <span class="font-medium">{{ form.pdf.name }}</span>
                  </p>
                  <p v-if="form.errors.pdf" class="text-xs text-red-600 mt-1">{{ form.errors.pdf }}</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Imagen de la categoría</label>
                  <input
                    ref="imageInput"
                    type="file"
                    accept="image/*"
                    @change="onPickImage"
                    class="block w-full text-sm text-slate-700 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-slate-900 file:text-white hover:file:bg-slate-800"
                  />

                  <p v-if="isEditing && editingImageUrl" class="text-xs text-slate-500 mt-2">
                    (Si no sube una nueva, se mantiene la actual)
                  </p>

                  <p v-if="form.imagen" class="text-xs text-slate-600 mt-2">
                    Seleccionado: <span class="font-medium">{{ form.imagen.name }}</span>
                  </p>

                  <p v-if="form.errors.imagen" class="text-xs text-red-600 mt-1">{{ form.errors.imagen }}</p>

                  <div v-if="isEditing && editingImageUrl" class="mt-3">
                    <p class="text-xs text-slate-500 mb-2">Imagen actual:</p>
                    <img
                      :src="editingImageUrl"
                      alt="Imagen actual de categoría"
                      class="h-28 w-full max-w-xs object-cover rounded-xl border border-slate-200"
                    />
                  </div>
                </div>
              </div>

              <div class="p-5 border-t border-slate-200 flex justify-end gap-3">
                <button
                  type="button"
                  @click="closeModal"
                  class="px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 transition"
                >
                  Cancelar
                </button>

                <button
                  type="button"
                  @click="save"
                  :disabled="!canSaveCategory || form.processing"
                  class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-blue-600"
                >
                  <CheckCircleIcon class="w-5 h-5" />
                  {{ isEditing ? "Guardar Cambios" : "Crear Categoría" }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </div>

  <Teleport to="body">
    <div v-if="isRondasModalOpen" class="fixed inset-0 z-[10030]">
      <div class="absolute inset-0 bg-black/40" @click="closeRondasModal"></div>

      <div class="relative grid h-full w-full place-items-center p-4 sm:p-6">
        <div class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
          <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-5">
            <div>
              <h2 class="text-lg font-semibold text-slate-900">Gestión de rondas</h2>
              <p class="mt-1 text-sm text-slate-500">
                {{ rondasCategoria?.nombre ?? "" }} · {{ rondasCategoria?.inscripciones_count ?? 0 }} equipos inscritos
              </p>
            </div>

            <button
              type="button"
              class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 hover:bg-slate-50"
              @click="closeRondasModal"
            >
              <XMarkIcon class="h-5 w-5 text-slate-600" />
            </button>
          </div>

          <div class="grid min-h-0 grid-cols-1 gap-5 overflow-y-auto p-5 lg:grid-cols-[1fr_1.25fr]">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <h3 class="font-semibold text-slate-900">
                  {{ rondaEditingId ? "Editar ronda" : "Nueva ronda" }}
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                  Usa Borrador para preparar, Activa para habilitar al juez y Cerrada al terminar la evaluación.
                </p>

                <div class="mt-4 space-y-4">
                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nombre</label>
                    <input
                      v-model="rondaForm.nombre"
                      class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="Ej: Clasificatoria, Final, Ronda 1"
                    />
                  </div>

                  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                      <label class="mb-1 block text-sm font-medium text-slate-700">Tipo</label>
                      <select
                        v-model="rondaForm.tipo"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      >
                        <option value="libre">Libre</option>
                        <option value="clasificatoria">Clasificatoria</option>
                        <option value="semifinal">Semifinal</option>
                        <option value="final">Final</option>
                      </select>
                    </div>

                    <div>
                      <label class="mb-1 block text-sm font-medium text-slate-700">Estado</label>
                      <select
                        v-model="rondaForm.estado"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      >
                        <option value="borrador">Borrador</option>
                        <option value="activa">Activa</option>
                        <option value="cerrada">Cerrada</option>
                      </select>
                    </div>
                  </div>

                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Fecha y hora</label>
                    <input
                      v-model="rondaForm.fecha_hora"
                      type="datetime-local"
                      class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>

                  <div class="flex gap-3 pt-2">
                    <button
                      type="button"
                      @click="saveRonda"
                      :disabled="rondasSaving"
                      class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                      <CheckCircleIcon class="h-5 w-5" />
                      {{ rondasSaving ? "Guardando..." : (rondaEditingId ? "Guardar cambios" : "Crear ronda") }}
                    </button>

                    <button
                      type="button"
                      @click="resetRondaForm"
                      class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 transition hover:bg-slate-50"
                    >
                      Limpiar
                    </button>
                  </div>
                </div>
              </div>

            <div class="min-h-0">
              <div v-if="rondasLoading" class="rounded-2xl border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500">
                Cargando rondas...
              </div>

              <div v-else-if="rondas.length" class="space-y-3">
                <div
                  v-for="ronda in rondas"
                  :key="ronda.id"
                  class="rounded-2xl border border-slate-200 bg-white p-4"
                >
                  <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                      <p class="font-semibold text-slate-900">{{ ronda.nombre }}</p>
                      <div class="mt-2 flex flex-wrap gap-2">
                        <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 ring-1 ring-blue-200">
                          {{ rondaTipoLabel(ronda.tipo) }}
                        </span>
                        <span
                          class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1"
                          :class="rondaEstadoBadge(ronda.estado)"
                        >
                          {{ rondaEstadoLabel(ronda.estado) }}
                        </span>
                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200">
                          {{ ronda.resultados_count }} evaluaciones
                        </span>
                      </div>
                      <p class="mt-2 text-xs text-slate-500">
                        {{ ronda.fecha_hora ? ronda.fecha_hora.replace('T', ' ') : "Sin fecha programada" }}
                      </p>
                    </div>

                    <div class="flex gap-2">
                      <button
                        type="button"
                        @click="editRonda(ronda)"
                        class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white transition hover:bg-slate-50"
                        title="Editar ronda"
                      >
                        <PencilSquareIcon class="h-5 w-5 text-slate-700" />
                      </button>

                      <button
                        type="button"
                        @click="requestRemoveRonda(ronda)"
                        class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="ronda.has_resultados"
                        title="Eliminar ronda"
                      >
                        <TrashIcon class="h-5 w-5 text-red-600" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-500">
                No hay rondas creadas para esta categoria.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>

  <Teleport to="body">
    <div v-if="alertModal.show" class="fixed inset-0 z-[10040] grid place-items-center p-4">
      <div class="absolute inset-0 bg-black/40" @click="closeAlertModal"></div>

      <div class="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-xl p-6">
        <div class="flex items-start gap-3">
          <div
            class="h-11 w-11 rounded-2xl flex items-center justify-center shrink-0"
            :class="{
              'bg-slate-100 text-slate-700': alertModal.variant === 'info',
              'bg-amber-50 text-amber-700': alertModal.variant === 'warning',
              'bg-red-50 text-red-700': alertModal.variant === 'danger',
            }"
          >
            <TagIcon v-if="alertModal.variant === 'info'" class="w-6 h-6" />
            <NoSymbolIcon v-else-if="alertModal.variant === 'warning'" class="w-6 h-6" />
            <TrashIcon v-else class="w-6 h-6" />
          </div>

          <div class="flex-1">
            <p class="text-lg font-semibold text-slate-900">{{ alertModal.title }}</p>
            <p class="mt-2 text-sm leading-6 text-slate-600">{{ alertModal.message }}</p>
          </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <button
            v-if="alertModal.cancelText"
            type="button"
            @click="closeAlertModal"
            class="px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 transition"
          >
            {{ alertModal.cancelText }}
          </button>

          <button
            type="button"
            @click="confirmAlertModal"
            class="px-4 py-2.5 rounded-xl text-white transition"
            :class="alertModal.variant === 'danger' ? 'bg-red-600 hover:bg-red-700' : 'bg-slate-900 hover:bg-slate-800'"
          >
            {{ alertModal.confirmText }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- TOAST -->
  <Teleport to="body">
    <div v-if="toast.show" class="fixed inset-0 z-[10050] grid place-items-center p-4">
      <div class="absolute inset-0 bg-black/30" @click="closeToast"></div>

      <div
        class="relative w-full max-w-md rounded-2xl border bg-white shadow-xl p-5"
        :class="{
          'border-emerald-200': toast.type === 'success',
          'border-amber-200': toast.type === 'warning',
          'border-red-200': toast.type === 'error',
          'border-slate-200': toast.type === 'info',
        }"
      >
        <div class="flex items-start gap-3">
          <div
            class="h-10 w-10 rounded-xl flex items-center justify-center"
            :class="{
              'bg-emerald-50 text-emerald-700': toast.type === 'success',
              'bg-amber-50 text-amber-700': toast.type === 'warning',
              'bg-red-50 text-red-700': toast.type === 'error',
              'bg-slate-100 text-slate-700': toast.type === 'info',
            }"
          >
            <CheckCircleIcon v-if="toast.type === 'success'" class="w-6 h-6" />
            <NoSymbolIcon v-else-if="toast.type === 'warning'" class="w-6 h-6" />
            <XMarkIcon v-else-if="toast.type === 'error'" class="w-6 h-6" />
            <TagIcon v-else class="w-6 h-6" />
          </div>

          <div class="flex-1">
            <p class="font-semibold text-slate-900">Aviso</p>
            <p class="text-sm text-slate-600 mt-1">{{ toast.message }}</p>
          </div>

          <button
            type="button"
            class="h-9 w-9 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-center justify-center"
            @click="closeToast"
          >
            <XMarkIcon class="w-5 h-5 text-slate-600" />
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.format-category-scroll {
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 #f8fafc;
}

.format-category-scroll::-webkit-scrollbar {
  height: 8px;
  width: 8px;
}

.format-category-scroll::-webkit-scrollbar-track {
  background: #f8fafc;
}

.format-category-scroll::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border: 2px solid #f8fafc;
  border-radius: 999px;
}

.format-category-scroll::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

.chrono-preview {
  box-shadow: inset 0 0 28px rgba(255, 255, 255, 0.03);
}

.chrono-preview-number {
  font-family: Arial, Helvetica, sans-serif;
  font-size: clamp(3.5rem, 6.5vw, 7rem);
  font-weight: 800;
  line-height: 0.9;
  letter-spacing: 0;
  color: #f2f2f2;
}

.chrono-preview-unit {
  margin-bottom: 0.1em;
  font-family: Arial, Helvetica, sans-serif;
  font-size: clamp(1.6rem, 2.8vw, 2.8rem);
  font-weight: 800;
  line-height: 1;
  color: #f2f2f2;
}
</style>
