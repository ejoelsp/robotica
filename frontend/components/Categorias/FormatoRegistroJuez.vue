<script setup>
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import axios from "axios";
import {
  AdjustmentsHorizontalIcon,
  CheckCircleIcon,
  MagnifyingGlassIcon,
} from "@heroicons/vue/24/outline";

const props = defineProps({
  categorias: { type: Array, default: () => [] },
});

const categories = computed(() => props.categorias ?? []);

const formatoSavingId = ref(null);
const formatoForms = ref({});
const formatoBaselines = ref({});
const formatoSearch = ref("");
const activeFormatoCategoryId = ref(null);
const toast = ref({ show: false, type: "info", message: "" });
let toastTimer = null;

const registroTypeOptions = [
  {
    value: "registro_resultado",
    label: "Registro de resultado",
    description: "Para tiempos, marcadores o resultados finales simples.",
  },
  {
    value: "tabla_evaluacion",
    label: "Tabla de evaluación",
    description: "Para categorías con criterios editables y puntuación máxima por criterio.",
  },
];

const modalidadOptions = [
  {
    value: "participacion_individual",
    label: "Participación individual",
    description: "Cada equipo participa solo, uno por uno.",
  },
  {
    value: "enfrentamiento_directo",
    label: "Enfrentamiento directo",
    description: "Dos equipos o robots compiten entre sí.",
  },
];

const resultadoTemplateOptions = [
  {
    value: "tiempo",
    label: "Tiempo / cronometro",
    description: "Para categorías donde gana el menor tiempo.",
  },
  {
    value: "marcador",
    label: "Marcador",
    description: "Para registrar solo el marcador del Equipo A contra el Equipo B.",
  },
];

const evaluacionTemplateOptions = [
  {
    value: "tabla_individual_criterios",
    label: "Tabla individual por criterios",
    description: "Para registrar cantidades por criterio y calcular el puntaje de un equipo.",
  },
  {
    value: "tabla_individual_puntaje_maximo",
    label: "Tabla individual por puntaje máximo",
    description: "Para que el juez ingrese puntos directos sin superar el máximo de cada criterio.",
  },
  {
    value: "tabla_enfrentamiento_criterios",
    label: "Tabla por enfrentamiento",
    description: "Para registrar criterios de Equipo A y Equipo B y calcular totales.",
  },
];

const esquemaJuecesOptions = [
  {
    value: "registro_cualquier_juez",
    label: "Registro por cualquier juez",
    description: "Cualquier juez asignado puede registrar el resultado.",
  },
  {
    value: "evaluacion_multi_juez",
    label: "Evaluación multi-juez",
    description: "Varios jueces califican al mismo equipo con la misma tabla.",
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
    plantilla_resultado: "tiempo",
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
    plantilla_resultado: "tiempo",
  },
  combate_llaves: {
    tipo_registro: "registro_resultado",
    modalidad_competencia: "enfrentamiento_directo",
    plantilla_resultado: "tiempo",
  },
  soccer_goles: {
    tipo_registro: "registro_resultado",
    modalidad_competencia: "enfrentamiento_directo",
    plantilla_resultado: "marcador",
  },
};

const registroTemplates = {
  registro_resultado_tiempo: {
    unidad: "s",
    orden: "asc",
    campos: [
      { key: "tiempo", type: "duration", label: "Tiempo final", required: true },
      { key: "penalizaciones", type: "number", label: "Penalización en segundos", required: false },
      { key: "observaciones", type: "textarea", label: "Observaciones", required: false },
    ],
  },
  registro_resultado_marcador: {
    unidad: "marcador",
    orden: "desc",
    campos: [
      { key: "marcador_equipo_a", type: "number", label: "Marcador equipo A", required: true },
      { key: "marcador_equipo_b", type: "number", label: "Marcador equipo B", required: true },
    ],
  },
  tabla_evaluacion_tabla_individual_criterios: {
    unidad: "pts",
    orden: "desc",
    campos: [
      { key: "inmovilizar", type: "number", label: "Inmovilizar al oponente por ataque", required: true, valor_unitario: 20, es_penalizacion: false },
      { key: "embestidas", type: "number", label: "Embestidas", required: true, valor_unitario: 5, es_penalizacion: false },
      { key: "vuelcos", type: "number", label: "Vuelcos", required: true, valor_unitario: 10, es_penalizacion: false },
      { key: "uso_armas", type: "number", label: "Uso de armas", required: true, valor_unitario: 15, es_penalizacion: false },
      { key: "amonestaciones", type: "number", label: "Amonestaciones", required: false, valor_unitario: 5, es_penalizacion: true },
      { key: "tiempo", type: "duration", label: "Tiempo de recorrido", required: false },
      { key: "observaciones", type: "textarea", label: "Observaciones", required: false },
    ],
  },
  tabla_evaluacion_tabla_individual_puntaje_maximo: {
    unidad: "pts",
    orden: "desc",
    campos: [
      { key: "sincronizacion", type: "number", label: "Sincronización", required: true, valor_unitario: 30, es_penalizacion: false },
      { key: "vestuario", type: "number", label: "Vestuario", required: true, valor_unitario: 15, es_penalizacion: false },
      { key: "complejidad_movimientos", type: "number", label: "Complejidad en movimientos", required: true, valor_unitario: 30, es_penalizacion: false },
      { key: "originalidad", type: "number", label: "Originalidad", required: true, valor_unitario: 15, es_penalizacion: false },
      { key: "escenario", type: "number", label: "Escenario", required: true, valor_unitario: 10, es_penalizacion: false },
      { key: "observaciones", type: "textarea", label: "Observaciones", required: false },
    ],
  },
  tabla_evaluacion_tabla_enfrentamiento_criterios: {
    unidad: "pts",
    orden: "desc",
    campos: [
      { key: "golpes", type: "number", label: "Golpes", required: true, valor_unitario: 2, es_penalizacion: false },
      { key: "empujes", type: "number", label: "Empujes", required: true, valor_unitario: 1, es_penalizacion: false },
      { key: "salidas", type: "number", label: "Salidas del área", required: false, valor_unitario: 3, es_penalizacion: true },
      { key: "observaciones", type: "textarea", label: "Observaciones", required: false },
    ],
  },
};

const filteredFormatoCategories = computed(() => {
  const term = formatoSearch.value.trim().toLowerCase();
  if (!term) return categories.value;
  return categories.value.filter((c) => (c.nombre ?? "").toLowerCase().includes(term));
});

const activeFormatoCategory = computed(() => {
  return categories.value.find((cat) => Number(cat.id) === Number(activeFormatoCategoryId.value)) ?? null;
});

function showToast(message, type = "info", ms = 3500) {
  toast.value = { show: true, type, message };
  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => (toast.value.show = false), ms);
}

function templateOptionsForTipo(tipoRegistro) {
  return tipoRegistro === "tabla_evaluacion" ? evaluacionTemplateOptions : resultadoTemplateOptions;
}

function normalizeTemplateForTipo(tipoRegistro, plantilla, modalidad = "participacion_individual") {
  if (plantilla === "goles") return "marcador";

  if (templateOptionsForTipo(tipoRegistro).some((option) => option.value === plantilla)) return plantilla;

  if (tipoRegistro === "tabla_evaluacion") {
    return modalidad === "enfrentamiento_directo"
      ? "tabla_enfrentamiento_criterios"
      : "tabla_individual_criterios";
  }

  return "tiempo";
}

function normalizeNombreKey(str) {
  return String(str ?? "")
    .trim()
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/\s+/g, " ");
}

function suggestedRegistroConfigForName(name) {
  const normalized = normalizeNombreKey(name);

  if (normalized.includes("soccer") || normalized.includes("futbol")) {
    return {
      tipo_registro: "registro_resultado",
      modalidad_competencia: "enfrentamiento_directo",
      plantilla_resultado: "marcador",
    };
  }

  if (normalized.includes("batalla") || normalized.includes("sumo") || normalized.includes("pelea")) {
    return {
      tipo_registro: "registro_resultado",
      modalidad_competencia: "enfrentamiento_directo",
      plantilla_resultado: "tiempo",
    };
  }

  if (normalized.includes("bailarin") || normalized.includes("impacto tecnologico")) {
    return {
      tipo_registro: "tabla_evaluacion",
      modalidad_competencia: "participacion_individual",
      plantilla_resultado: "tabla_individual_puntaje_maximo",
    };
  }

  if (normalized.includes("destreza")) {
    return {
      tipo_registro: "tabla_evaluacion",
      modalidad_competencia: "participacion_individual",
      plantilla_resultado: "tabla_individual_criterios",
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
    if (normalized.includes("bailarin") || normalized.includes("impacto tecnologico")) {
      return "tabla_individual_puntaje_maximo";
    }

    return modalidad === "enfrentamiento_directo"
      ? "tabla_enfrentamiento_criterios"
      : "tabla_individual_criterios";
  }

  return normalized.includes("soccer") || normalized.includes("futbol") ? "marcador" : "tiempo";
}

function suggestedJudgeSchemeForName(name, tipoRegistro, modalidad) {
  const normalized = normalizeNombreKey(name);

  if (tipoRegistro === "tabla_evaluacion" && (normalized.includes("bailarin") || normalized.includes("dron"))) {
    return "evaluacion_multi_juez";
  }

  return "registro_cualquier_juez";
}

function normalizeJudgeScheme(value) {
  return esquemaJuecesOptions.some((option) => option.value === value)
    ? value
    : "registro_cualquier_juez";
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
  const plantilla = normalizeTemplateForTipo(
    tipoRegistro,
    stored.plantilla_resultado ?? mapped.plantilla_resultado ?? suggestedResultadoTemplateForName(cat?.nombre, tipoRegistro, modalidad),
    modalidad
  );

  const esquemaJueces = normalizeJudgeScheme(stored.esquema_jueces ?? suggestedJudgeSchemeForName(cat?.nombre, tipoRegistro, modalidad));

  return {
    tipo_registro: tipoRegistro,
    modalidad_competencia: modalidad,
    plantilla_resultado: plantilla,
    esquema_jueces: esquemaJueces,
    promediar_jueces: esquemaJueces === "evaluacion_multi_juez",
  };
}

function mechanismCodeFromRegistroConfig(tipoRegistro) {
  return tipoRegistro === "tabla_evaluacion" ? "tabla_evaluacion" : "registro_resultado";
}

function formatoOptionLabel(options, value, fallback = "Pendiente") {
  return options.find((item) => item.value === value)?.label ?? fallback;
}

function formatoOptionDescription(options, value) {
  return options.find((item) => item.value === value)?.description ?? "";
}

function isMarcadorFormato(formato) {
  return formato?.tipo_registro === "registro_resultado" && formato?.plantilla_resultado === "marcador";
}

function isTiempoFormato(formato) {
  return formato?.tipo_registro === "registro_resultado" && formato?.plantilla_resultado === "tiempo";
}

function isTablaIndividualFormato(formato) {
  return formato?.tipo_registro === "tabla_evaluacion" && formato?.plantilla_resultado === "tabla_individual_criterios";
}

function isTablaPuntajeMaximoFormato(formato) {
  return formato?.tipo_registro === "tabla_evaluacion" && formato?.plantilla_resultado === "tabla_individual_puntaje_maximo";
}

function isTablaEnfrentamientoFormato(formato) {
  return formato?.tipo_registro === "tabla_evaluacion" && formato?.plantilla_resultado === "tabla_enfrentamiento_criterios";
}

function isEvaluacionMultiJuez(formato) {
  return formato?.esquema_jueces === "evaluacion_multi_juez";
}

function criterioFields(formato) {
  return (formato?.campos_json ?? []).filter((field) => field.key !== "observaciones" && field.type === "number");
}

function individualScoringFields(formato) {
  return criterioFields(formato).filter((field) => !field.es_penalizacion);
}

function individualPenaltyFields(formato) {
  return criterioFields(formato).filter((field) => field.es_penalizacion);
}

function previewCantidad(index, side = "A") {
  const values = side === "A" ? [0, 0, 0, 0, 0, 0] : [0, 0, 0, 0, 0, 0];
  return values[index % values.length];
}

function previewScore(field, index, side = "A") {
  if (isTablaPuntajeMaximoFormato(formatoForms.value[activeFormatoCategoryId.value])) {
    return 0;
  }

  const value = previewCantidad(index, side) * Number(field?.valor_unitario || 0);
  return field?.es_penalizacion ? -value : value;
}

function previewSubtotal(formato, side = "A") {
  return criterioFields(formato)
    .filter((field) => !field.es_penalizacion)
    .reduce((total, field, index) => total + previewScore(field, index, side), 0);
}

function previewPenaltyTotal(formato, side = "A") {
  return criterioFields(formato)
    .filter((field) => field.es_penalizacion)
    .reduce((total, field, index) => total + Math.abs(previewScore(field, index, side)), 0);
}

function previewFinalTotal(formato, side = "A") {
  return previewSubtotal(formato, side) - previewPenaltyTotal(formato, side);
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

function registroTemplateKey(tipoRegistro, modalidad, plantilla = "tiempo") {
  if (tipoRegistro === "tabla_evaluacion") {
    return registroTemplates[`tabla_evaluacion_${plantilla}`]
      ? `tabla_evaluacion_${plantilla}`
      : "tabla_evaluacion_tabla_individual_criterios";
  }

  return registroTemplates[`registro_resultado_${plantilla}`]
    ? `registro_resultado_${plantilla}`
    : "registro_resultado_tiempo";
}

function cloneFields(fields) {
  return JSON.parse(JSON.stringify(fields ?? []));
}

function ensureIndividualCriteriaTieBreakTimeField(fields) {
  const cloned = cloneFields(fields);
  const remaining = [];
  let timeField = null;
  let observationsField = null;

  for (const field of cloned) {
    if (!timeField && field.key === "tiempo" && field.type === "duration") {
      timeField = field;
      continue;
    }

    if (!observationsField && field.key === "observaciones") {
      observationsField = field;
      continue;
    }

    remaining.push(field);
  }

  if (!timeField) {
    timeField = {
      key: "tiempo",
      type: "duration",
      label: "Tiempo de recorrido",
      required: false,
    };
  }

  remaining.push(timeField);

  if (observationsField) {
    remaining.push(observationsField);
  }

  return remaining;
}

function fieldsForFormatoTemplate(plantilla, fields) {
  return plantilla === "tabla_individual_criterios"
    ? ensureIndividualCriteriaTieBreakTimeField(fields)
    : cloneFields(fields);
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
  const safeCodigo = mechanismCodeFromRegistroConfig(registroConfig.tipo_registro);
  const template = registroTemplates[registroTemplateKey(
    registroConfig.tipo_registro,
    registroConfig.modalidad_competencia,
    registroConfig.plantilla_resultado
  )];
  const storedFields = cat?.config_calificacion?.campos_json;
  const storedFieldKeys = Array.isArray(storedFields) ? storedFields.map((field) => field.key) : [];
  const shouldReuseStoredFields = rawCodigo === safeCodigo
    && Array.isArray(storedFields)
    && storedFields.length
    && (
      registroConfig.plantilla_resultado !== "marcador"
      || (
        storedFieldKeys.includes("marcador_equipo_a")
        && storedFieldKeys.includes("marcador_equipo_b")
      )
    );

  return {
    categoria_id: cat.id,
    tipo_registro: registroConfig.tipo_registro,
    modalidad_competencia: registroConfig.modalidad_competencia,
    plantilla_resultado: registroConfig.plantilla_resultado,
    esquema_jueces: registroConfig.esquema_jueces,
    promediar_jueces: registroConfig.esquema_jueces === "evaluacion_multi_juez",
    mecanismo_codigo: safeCodigo,
    unidad_resultado: rawCodigo === safeCodigo ? (cat?.config_calificacion?.unidad_resultado ?? template.unidad) : template.unidad,
    orden_ranking: rawCodigo === safeCodigo ? (cat?.config_calificacion?.orden_ranking ?? template.orden) : template.orden,
    promediar_resultado_final: false,
    requiere_aprobacion_admin: cat?.config_calificacion?.requiere_aprobacion_admin ?? true,
    visible_publico_en_vivo: cat?.config_calificacion?.visible_publico_en_vivo ?? false,
    permite_edicion_juez: cat?.config_calificacion?.permite_edicion_juez ?? true,
    campos_json: shouldReuseStoredFields
      ? fieldsForFormatoTemplate(registroConfig.plantilla_resultado, storedFields)
      : fieldsForFormatoTemplate(registroConfig.plantilla_resultado, template.campos),
  };
}

function syncFormatoForms() {
  const next = { ...formatoForms.value };

  for (const cat of categories.value) {
    next[cat.id] = next[cat.id] ?? defaultFormatoForm(cat);
  }

  formatoForms.value = next;

  const baselines = {};
  for (const cat of categories.value) {
    if (!next[cat.id]) continue;
    baselines[cat.id] = JSON.stringify(comparableFormatoPayload(next[cat.id]));
  }
  formatoBaselines.value = baselines;
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

function selectFormatoCategory(cat) {
  activeFormatoCategoryId.value = cat?.id ?? null;
}

function syncMechanismFromRegistroConfig(cat, resetFields = true) {
  const current = formatoForms.value[cat.id];
  if (!current) return;

  const codigo = mechanismCodeFromRegistroConfig(current.tipo_registro);
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
    promediar_resultado_final: false,
    campos_json: resetFields
      ? fieldsForFormatoTemplate(current.plantilla_resultado, template.campos)
      : fieldsForFormatoTemplate(current.plantilla_resultado, current.campos_json),
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
    plantilla_resultado: normalizeTemplateForTipo(current.tipo_registro, current.plantilla_resultado, modalidad),
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

  syncMechanismFromRegistroConfig(cat);
}

function applyJudgeScheme(cat, esquema) {
  const current = formatoForms.value[cat.id];
  if (!current) return;
  const normalizedScheme = normalizeJudgeScheme(esquema);

  formatoForms.value[cat.id] = {
    ...current,
    esquema_jueces: normalizedScheme,
    promediar_jueces: normalizedScheme === "evaluacion_multi_juez",
  };
}

function addRubricaCriterion(cat) {
  const current = formatoForms.value[cat.id];
  if (!current) return;

  const criteriaCount = current.campos_json.filter((field) => field.type === "number").length + 1;
  const fixedTailStart = current.campos_json.findIndex((field) => field.key === "tiempo" || field.key === "observaciones");
  const insertAt = fixedTailStart >= 0 ? fixedTailStart : current.campos_json.length;

  current.campos_json.splice(insertAt, 0, {
    key: `criterio_${criteriaCount}`,
    type: "number",
    label: `Criterio ${criteriaCount}`,
    required: true,
    valor_unitario: 1,
    es_penalizacion: false,
  });
}

function removeRubricaCriterion(cat, index) {
  const current = formatoForms.value[cat.id];
  if (!current) return;

  current.campos_json.splice(index, 1);
}

function sanitizePositiveIntegerValue(value) {
  const digits = String(value ?? "").replace(/\D/g, "").replace(/^0+/, "");
  return digits === "" ? "" : Number(digits);
}

function blockNonPositiveIntegerInput(event) {
  if (!event.data) return;

  if (!/^\d+$/.test(event.data)) {
    event.preventDefault();
  }
}

function onCriterionValueInput(field, event) {
  field.valor_unitario = sanitizePositiveIntegerValue(event.target.value);
  event.target.value = field.valor_unitario === "" ? "" : String(field.valor_unitario);
}

function hasInvalidCriterionValues(formato) {
  if (formato?.tipo_registro !== "tabla_evaluacion") return false;

  return (formato.campos_json ?? []).some((field) => {
    if (field.type !== "number") return false;

    const value = Number(field.valor_unitario);
    return !Number.isInteger(value) || value <= 0;
  });
}

function criterionValuePlaceholder(formato) {
  return isTablaPuntajeMaximoFormato(formato) ? "Máximo" : "Valor";
}

function normalizeFormatoPayload(raw) {
  const rawFields = raw.plantilla_resultado === "marcador"
    ? registroTemplates.registro_resultado_marcador.campos
    : raw.campos_json;
  const fields = cloneFields(rawFields)
    .map((field, index) => ({
      ...field,
      key: normalizeFieldKey(field.key || field.label, index),
      required: !!field.required,
      valor_unitario: field.valor_unitario === "" || field.valor_unitario === null || field.valor_unitario === undefined
        ? undefined
        : sanitizePositiveIntegerValue(field.valor_unitario),
      es_penalizacion: field.type !== "number" || isTablaPuntajeMaximoFormato(raw) ? false : !!field.es_penalizacion,
    }))
    .filter((field) => field.key && field.label);

  return {
    ...raw,
    mecanismo_codigo: mechanismCodeFromRegistroConfig(raw.tipo_registro),
    plantilla_resultado: raw.plantilla_resultado || suggestedResultadoTemplateForName("", raw.tipo_registro, raw.modalidad_competencia),
    esquema_jueces: normalizeJudgeScheme(raw.esquema_jueces),
    promediar_jueces: normalizeJudgeScheme(raw.esquema_jueces) === "evaluacion_multi_juez",
    promediar_resultado_final: false,
    campos_json: fieldsForFormatoTemplate(raw.plantilla_resultado, fields),
  };
}

function comparableFormatoPayload(raw) {
  const payload = normalizeFormatoPayload(raw);

  return {
    categoria_id: Number(payload.categoria_id),
    tipo_registro: payload.tipo_registro,
    modalidad_competencia: payload.modalidad_competencia,
    plantilla_resultado: payload.plantilla_resultado,
    esquema_jueces: payload.esquema_jueces,
    promediar_jueces: !!payload.promediar_jueces,
    mecanismo_codigo: payload.mecanismo_codigo,
    unidad_resultado: payload.unidad_resultado ?? "",
    orden_ranking: payload.orden_ranking ?? "desc",
    promediar_resultado_final: !!payload.promediar_resultado_final,
    requiere_aprobacion_admin: !!payload.requiere_aprobacion_admin,
    visible_publico_en_vivo: !!payload.visible_publico_en_vivo,
    permite_edicion_juez: !!payload.permite_edicion_juez,
    campos_json: payload.campos_json.map((field) => ({
      key: field.key,
      type: field.type,
      label: field.label,
      required: !!field.required,
      valor_unitario: field.valor_unitario ?? null,
      es_penalizacion: !!field.es_penalizacion,
    })),
  };
}

function hasFormatoChanges(cat) {
  if (!cat?.id || !formatoForms.value[cat.id]) return false;

  const current = JSON.stringify(comparableFormatoPayload(formatoForms.value[cat.id]));
  const baseline = formatoBaselines.value[cat.id]
    ?? JSON.stringify(comparableFormatoPayload(defaultFormatoForm(cat)));

  return current !== baseline;
}

async function saveFormato(cat) {
  const current = formatoForms.value[cat.id];
  if (!current || !hasFormatoChanges(cat)) return;

  if (hasInvalidCriterionValues(current)) {
    showToast("El valor de cada criterio debe ser un número entero positivo.", "warning", 4500);
    return;
  }

  formatoSavingId.value = cat.id;

  try {
    await axios.put(`/admin/categorias/${cat.id}/formato-registro`, normalizeFormatoPayload(current));
    formatoBaselines.value[cat.id] = JSON.stringify(comparableFormatoPayload(current));
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
</script>

<template>
  <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 p-5 sm:p-6">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div>
          <div class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-200">
            <AdjustmentsHorizontalIcon class="h-4 w-4" />
            {{ categories.length }} categorías configurables
          </div>
          <h2 class="mt-3 text-lg font-semibold text-slate-900">Formato de registro del juez</h2>
          <p class="mt-1 max-w-3xl text-sm text-slate-500">
            Selecciona una categoría para definir que formulario verá el juez al registrar resultados.
          </p>
        </div>

        <div class="relative w-full xl:w-[360px]">
          <input
            v-model="formatoSearch"
            type="text"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-11 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Buscar categoría..."
          />
          <div class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
            <MagnifyingGlassIcon class="h-5 w-5 text-slate-400" />
          </div>
        </div>
      </div>
    </div>

    <div v-if="categories.length" class="flex flex-col">
      <div class="order-2 p-5 sm:p-6" v-if="activeFormatoCategory && formatoForms[activeFormatoCategory.id]">
        <div class="mb-5 flex flex-col gap-4 border-b border-slate-200 pb-5 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase text-blue-600">Categoría seleccionada</p>
            <h3 class="mt-1 text-2xl font-semibold text-slate-950">{{ activeFormatoCategory.nombre }}</h3>
          </div>

          <button
            type="button"
            @click="saveFormato(activeFormatoCategory)"
            :disabled="formatoSavingId === activeFormatoCategory.id || !hasFormatoChanges(activeFormatoCategory)"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500"
          >
            <CheckCircleIcon class="h-5 w-5" />
            {{ formatoSavingId === activeFormatoCategory.id ? "Guardando..." : "Guardar formato" }}
          </button>
        </div>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-[320px_minmax(0,1fr)]">
          <section class="space-y-3">
            <div>
              <p class="text-sm font-semibold text-slate-900">Tipo de registro</p>
              <p class="mt-1 text-xs text-slate-500">Define la base del formulario que verá el juez.</p>
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
                  @change="applyJudgeScheme(activeFormatoCategory, formatoForms[activeFormatoCategory.id].esquema_jueces)"
                >
                  <option v-for="option in esquemaJuecesOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
                <p
                  v-if="isEvaluacionMultiJuez(formatoForms[activeFormatoCategory.id])"
                  class="mt-2 rounded-xl border border-blue-100 bg-blue-50 px-3 py-2 text-xs leading-5 text-blue-800"
                >
                  Varios jueces califican al mismo equipo con la misma tabla. El sistema calculará automáticamente el promedio de las calificaciones registradas por los jueces asignados.
                </p>
                <p v-else class="mt-1 text-xs text-slate-500">
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

            </div>

            <div class="mt-5">
              <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                  <p class="text-sm font-semibold text-slate-900">Campos que verá el juez</p>
                  <p class="text-xs text-slate-500">
                    {{
                      isMarcadorFormato(formatoForms[activeFormatoCategory.id])
                        ? "Vista previa del marcador que vera el juez."
                        : isTiempoFormato(formatoForms[activeFormatoCategory.id])
                          ? "Vista previa del cronometro que vera el juez."
                          : "Ajusta la tabla cuando el tipo sea Tabla de evaluación."
                    }}
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
                v-if="isMarcadorFormato(formatoForms[activeFormatoCategory.id])"
                class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-950 text-white shadow-sm"
              >
                <div class="h-1.5 bg-blue-600"></div>
                <div class="px-5 py-5 sm:px-6">
                  <div class="mb-5 flex items-center justify-between gap-3 text-sm">
                    <span class="text-slate-300">Vista previa del marcador</span>
                    <span class="rounded-full bg-slate-800 px-3 py-1 font-semibold text-slate-100">En registro</span>
                  </div>

                  <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 sm:gap-6">
                    <div class="min-w-0 text-center">
                      <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-800 text-lg font-black text-blue-200 ring-1 ring-slate-700">
                        A
                      </div>
                      <p class="mt-3 truncate text-base font-semibold text-white">Equipo A</p>
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
                    </div>
                  </div>

                  <div class="mt-4 rounded-xl bg-slate-900/80 px-3 py-2 text-xs text-slate-300">
                    El juez registrará solo el marcador del Equipo A y el marcador del Equipo B.
                  </div>
                </div>
              </div>

              <div
                v-else-if="isTiempoFormato(formatoForms[activeFormatoCategory.id])"
                class="overflow-hidden rounded-2xl border border-blue-500/30 bg-black text-white shadow-sm"
              >
                <div class="h-2 bg-blue-600"></div>
                <div class="px-5 py-6 sm:px-6">
                  <div class="mb-5 flex items-center justify-between gap-3 text-sm">
                    <span class="font-semibold text-slate-100">Cronometro digital</span>
                    <span class="rounded-full bg-white/10 px-4 py-1.5 text-xs font-bold text-slate-100 ring-1 ring-white/15">
                      Tiempo final
                    </span>
                  </div>

                  <div class="rounded-2xl border border-white/10 bg-[#101010] px-4 py-8 shadow-[inset_0_0_32px_rgba(255,255,255,0.04)] sm:px-6 sm:py-10">
                    <div class="flex items-end justify-center gap-2 text-white sm:gap-4">
                      <div class="flex items-end gap-1">
                        <span class="text-[4rem] font-black leading-none tracking-normal sm:text-[7rem]">00</span>
                        <span class="-mb-1 text-4xl font-black leading-none sm:text-6xl">h</span>
                      </div>
                      <div class="flex items-end gap-1">
                        <span class="text-[4rem] font-black leading-none tracking-normal sm:text-[7rem]">00</span>
                        <span class="-mb-1 text-4xl font-black leading-none sm:text-6xl">m</span>
                      </div>
                      <div class="flex items-end gap-1">
                        <span class="text-[4rem] font-black leading-none tracking-normal sm:text-[7rem]">00</span>
                        <span class="-mb-1 text-4xl font-black leading-none sm:text-6xl">s</span>
                      </div>
                    </div>
                  </div>

                  <div class="mt-4 rounded-xl border border-blue-500/30 bg-slate-950 px-4 py-3 text-center text-sm font-semibold text-slate-400">
                    Digite solo números. Ej: 735 = 00h 07m 35s
                  </div>

                  <div class="mt-4 grid grid-cols-1 gap-3 text-xs text-slate-200 sm:grid-cols-2">
                    <div class="rounded-xl border border-white/10 bg-[#101010] px-4 py-3">
                      Se completa de derecha a izquierda: HH MM SS.
                    </div>
                    <div class="rounded-xl border border-white/10 bg-[#101010] px-4 py-3">
                      Ejemplo: 735 se guarda como 00:07:35.
                    </div>
                  </div>
                </div>
              </div>

              <div
                v-else-if="isTablaIndividualFormato(formatoForms[activeFormatoCategory.id]) || isTablaPuntajeMaximoFormato(formatoForms[activeFormatoCategory.id])"
                class="overflow-hidden rounded-2xl border border-slate-800 bg-[#1f1f1f] text-white shadow-sm"
              >
                <div class="grid grid-cols-[1.15fr_1fr] items-center border-b border-white/10 px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-200">
                  <span>Estadísticas</span>
                  <span>Equipo</span>
                </div>

                <div class="overflow-x-auto">
                  <table class="min-w-full text-sm">
                    <thead>
                    <tr class="text-xs uppercase tracking-wide text-slate-300">
                      <th class="px-3 py-3 text-center">Criterio</th>
                      <th class="px-3 py-3 text-center">
                        {{ isTablaPuntajeMaximoFormato(formatoForms[activeFormatoCategory.id]) ? "Puntaje máximo" : "Valor" }}
                      </th>
                      <th class="px-3 py-3 text-center text-emerald-300">
                        {{ isTablaPuntajeMaximoFormato(formatoForms[activeFormatoCategory.id]) ? "Puntaje" : "Cantidad" }}
                      </th>
                      <th
                        v-if="!isTablaPuntajeMaximoFormato(formatoForms[activeFormatoCategory.id])"
                        class="px-3 py-3 text-center text-yellow-300"
                      >
                        Puntaje
                      </th>
                    </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="(field, index) in individualScoringFields(formatoForms[activeFormatoCategory.id])"
                        :key="`admin-individual-${field.key}`"
                        class="border-t border-white/5"
                      >
                        <td class="px-3 py-3 text-center">
                          <p class="font-semibold text-white">{{ field.label }}</p>
                          <p class="text-xs text-slate-400">
                            {{ isTablaPuntajeMaximoFormato(formatoForms[activeFormatoCategory.id]) ? "Máximo permitido" : "Suma al subtotal" }}
                          </p>
                        </td>
                        <td class="px-3 py-3 text-center text-slate-200">
                          {{ isTablaPuntajeMaximoFormato(formatoForms[activeFormatoCategory.id]) ? Number(field.valor_unitario || 0) : `x ${Number(field.valor_unitario || 0)}` }}
                        </td>
                        <td class="px-3 py-3 text-center">
                          <span class="inline-flex h-9 w-16 items-center justify-center rounded-lg border-2 border-emerald-500 bg-transparent text-center font-bold text-white"></span>
                        </td>
                        <td v-if="!isTablaPuntajeMaximoFormato(formatoForms[activeFormatoCategory.id])" class="px-3 py-3 text-center">
                          <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                            {{ Math.max(previewScore(field, index), 0) }}
                          </span>
                        </td>
                      </tr>

                      <tr class="border-t-4 border-red-600 bg-[#242424] text-sm font-bold">
                        <td class="px-3 py-4 text-center text-white">
                          Subtotal
                        </td>
                        <td class="px-3 py-4"></td>
                        <td v-if="!isTablaPuntajeMaximoFormato(formatoForms[activeFormatoCategory.id])" class="px-3 py-4"></td>
                        <td class="px-3 py-4 text-center text-yellow-100">{{ previewSubtotal(formatoForms[activeFormatoCategory.id]) }}</td>
                      </tr>

                      <tr
                        v-for="(field, index) in individualPenaltyFields(formatoForms[activeFormatoCategory.id])"
                        :key="`admin-individual-penalty-${field.key}`"
                        class="border-t border-white/5"
                      >
                        <td class="px-3 py-3 text-center">
                          <p class="font-semibold text-red-400">{{ field.label }}</p>
                          <p class="text-xs text-red-300">Resta al subtotal</p>
                        </td>
                        <td class="px-3 py-3 text-center text-slate-200">x {{ Number(field.valor_unitario || 0) }}</td>
                        <td class="px-3 py-3 text-center">
                          <span class="inline-flex h-9 w-16 items-center justify-center rounded-lg border-2 border-emerald-500 bg-transparent text-center font-bold text-white"></span>
                        </td>
                        <td class="px-3 py-3 text-center">
                          <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                            {{ Math.abs(previewScore(field, index)) }}
                          </span>
                        </td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr class="border-t-4 border-red-600 bg-[#242424] text-base font-bold">
                        <td class="px-3 py-5 text-center uppercase tracking-wide text-white">
                          Resultado final
                        </td>
                        <td class="px-3 py-5"></td>
                        <td v-if="!isTablaPuntajeMaximoFormato(formatoForms[activeFormatoCategory.id])" class="px-3 py-5"></td>
                        <td class="px-3 py-5 text-center text-yellow-100">{{ previewFinalTotal(formatoForms[activeFormatoCategory.id]) }}</td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>

              <div
                v-else-if="isTablaEnfrentamientoFormato(formatoForms[activeFormatoCategory.id])"
                class="overflow-hidden rounded-2xl border border-slate-800 bg-[#1f1f1f] text-white shadow-sm"
              >
                <div class="grid grid-cols-[1fr_1.15fr_1fr] items-center border-b border-white/10 px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-200">
                  <span>A</span>
                  <span>Estadísticas</span>
                  <span>B</span>
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
                        :key="`admin-fight-${field.key}`"
                        class="border-t border-white/5"
                      >
                        <td class="px-3 py-2 text-center">
                          <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                            {{ Math.max(previewScore(field, index, 'A'), 0) }}
                          </span>
                        </td>
                        <td class="px-3 py-2 text-center">
                          <span class="inline-flex h-9 w-16 items-center justify-center rounded-lg border-2 border-emerald-500 bg-transparent text-center font-bold text-white"></span>
                        </td>
                        <td class="px-3 py-2 text-center">
                          <p class="font-semibold text-white">{{ field.label }}</p>
                          <p class="text-xs text-slate-400">Suma al subtotal</p>
                        </td>
                        <td class="px-3 py-2 text-center text-slate-200">x {{ Number(field.valor_unitario || 0) }}</td>
                        <td class="px-3 py-2 text-center">
                          <span class="inline-flex h-9 w-16 items-center justify-center rounded-lg border-2 border-emerald-500 bg-transparent text-center font-bold text-white"></span>
                        </td>
                        <td class="px-3 py-2 text-center">
                          <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                            {{ Math.max(previewScore(field, index, 'B'), 0) }}
                          </span>
                        </td>
                      </tr>

                      <tr class="border-t-4 border-red-600 bg-[#242424] text-sm font-bold">
                        <td class="px-3 py-3 text-center text-yellow-100">{{ previewSubtotal(formatoForms[activeFormatoCategory.id], 'A') }}</td>
                        <td class="px-3 py-3"></td>
                        <td class="px-3 py-3 text-center text-white">Subtotal</td>
                        <td class="px-3 py-3"></td>
                        <td class="px-3 py-3"></td>
                        <td class="px-3 py-3 text-center text-yellow-100">{{ previewSubtotal(formatoForms[activeFormatoCategory.id], 'B') }}</td>
                      </tr>

                      <tr
                        v-for="(field, index) in criterioFields(formatoForms[activeFormatoCategory.id]).filter((item) => item.es_penalizacion)"
                        :key="`admin-fight-penalty-${field.key}`"
                        class="border-t border-white/5"
                      >
                        <td class="px-3 py-2 text-center">
                          <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                            {{ Math.abs(previewScore(field, index, 'A')) }}
                          </span>
                        </td>
                        <td class="px-3 py-2 text-center">
                          <span class="inline-flex h-9 w-16 items-center justify-center rounded-lg border-2 border-emerald-500 bg-transparent text-center font-bold text-white"></span>
                        </td>
                        <td class="px-3 py-2 text-center">
                          <p class="font-semibold text-red-400">{{ field.label }}</p>
                          <p class="text-xs text-red-300">Resta al subtotal</p>
                        </td>
                        <td class="px-3 py-2 text-center text-slate-200">x {{ Number(field.valor_unitario || 0) }}</td>
                        <td class="px-3 py-2 text-center">
                          <span class="inline-flex h-9 w-16 items-center justify-center rounded-lg border-2 border-emerald-500 bg-transparent text-center font-bold text-white"></span>
                        </td>
                        <td class="px-3 py-2 text-center">
                          <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                            {{ Math.abs(previewScore(field, index, 'B')) }}
                          </span>
                        </td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr class="border-t-4 border-red-600 bg-[#242424] text-base font-bold">
                        <td class="px-3 py-4 text-center text-yellow-100">{{ previewFinalTotal(formatoForms[activeFormatoCategory.id], 'A') }}</td>
                        <td class="px-3 py-4"></td>
                        <td class="px-3 py-4 text-center uppercase tracking-wide text-white">Resultado final</td>
                        <td class="px-3 py-4"></td>
                        <td class="px-3 py-4"></td>
                        <td class="px-3 py-4 text-center text-yellow-100">{{ previewFinalTotal(formatoForms[activeFormatoCategory.id], 'B') }}</td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>

              <div v-if="formatoForms[activeFormatoCategory.id].tipo_registro === 'tabla_evaluacion'" class="mt-4 space-y-2">
                <p class="text-sm font-semibold text-slate-900">Configuración de criterios</p>
                <div
                  v-for="(field, index) in formatoForms[activeFormatoCategory.id].campos_json"
                  :key="`${activeFormatoCategory.id}-${field.key}-${index}`"
                  class="grid grid-cols-1 gap-2 rounded-xl border border-slate-200 bg-white p-3 sm:grid-cols-[1fr_110px_90px_128px_auto]"
                >
                  <input
                    v-model="field.label"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-slate-100"
                    placeholder="Nombre del campo"
                    :disabled="field.type === 'textarea' || field.type === 'select' || field.type === 'duration'"
                  />
                  <input
                    :value="field.valor_unitario"
                    type="text"
                    inputmode="numeric"
                    pattern="[1-9][0-9]*"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-slate-100"
                    :placeholder="criterionValuePlaceholder(formatoForms[activeFormatoCategory.id])"
                    :disabled="field.type !== 'number'"
                    @beforeinput="blockNonPositiveIntegerInput"
                    @input="onCriterionValueInput(field, $event)"
                  />
                  <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700">
                    <input v-model="field.required" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                    Req.
                  </label>
                  <label
                    class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium transition"
                    :class="field.es_penalizacion ? 'bg-red-50 text-red-700 ring-1 ring-red-200' : 'text-slate-700'"
                  >
                    <input
                      v-model="field.es_penalizacion"
                      type="checkbox"
                      class="rounded border-slate-300 text-red-600 focus:ring-red-500 disabled:cursor-not-allowed disabled:opacity-50"
                      :disabled="field.type !== 'number' || isTablaPuntajeMaximoFormato(formatoForms[activeFormatoCategory.id])"
                    />
                    Penalización
                  </label>
                  <button
                    type="button"
                    @click="removeRubricaCriterion(activeFormatoCategory, index)"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="formatoForms[activeFormatoCategory.id].tipo_registro !== 'tabla_evaluacion' || ['observaciones', 'tiempo'].includes(field.key)"
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
        Selecciona una categoría para configurar su formato.
      </div>

      <div class="order-1 border-b border-slate-200 bg-slate-50/70">
        <div class="overflow-x-auto p-4">
          <div v-if="filteredFormatoCategories.length" class="flex min-w-max gap-3">
            <button
              v-for="cat in filteredFormatoCategories"
              :key="`formato-card-${cat.id ?? cat.nombre}`"
              type="button"
              class="group min-h-[180px] w-[290px] shrink-0 rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md sm:w-[320px]"
              :class="Number(activeFormatoCategoryId) === Number(cat.id) ? 'border-blue-300 ring-2 ring-blue-100' : 'border-slate-200'"
              @click="selectFormatoCategory(cat)"
            >
              <p class="truncate text-sm font-semibold text-slate-950">{{ cat.nombre }}</p>
              <p class="mt-1 text-xs leading-5 text-slate-500">{{ formatoConfigSummary(cat).tipo }}</p>

              <div class="mt-4 flex flex-wrap gap-2">
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200">
                  {{ formatoConfigSummary(cat).modalidad }}
                </span>
                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 ring-1 ring-indigo-200">
                  {{ formatoConfigSummary(cat).campos }} campos
                </span>
                <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 ring-1 ring-blue-200">
                  {{ formatoConfigSummary(cat).plantilla }}
                </span>
              </div>

              <p class="mt-3 line-clamp-2 text-xs leading-5 text-slate-500">
                {{ formatoConfigSummary(cat).esquema }}
              </p>
            </button>
          </div>

          <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-10 text-center text-sm text-slate-500">
            No se encontraron categorías con ese criterio.
          </div>
        </div>
      </div>
    </div>

    <div v-else class="px-6 py-10 text-center text-sm text-slate-500">
      Crea una categoría para configurar su formato de registro.
    </div>
  </div>

  <Teleport to="body">
    <div
      v-if="toast.show"
      class="fixed right-4 top-4 z-[10000] max-w-md rounded-2xl border bg-white px-4 py-3 text-sm shadow-xl"
      :class="toast.type === 'success' ? 'border-emerald-200 text-emerald-800' : 'border-red-200 text-red-800'"
    >
      {{ toast.message }}
    </div>
  </Teleport>
</template>
