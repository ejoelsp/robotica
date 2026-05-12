<script setup>
import axios from "axios";
import { computed, onMounted, reactive, ref, watch } from "vue";

import {
  EyeIcon,
  TrophyIcon,
  CheckCircleIcon,
  XMarkIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ClipboardDocumentListIcon,
  Squares2X2Icon,
  ClockIcon,
  InformationCircleIcon,
  SparklesIcon,
} from "@heroicons/vue/24/outline";

const props = defineProps({
  competitions: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  initialCompetitionId: { type: [String, Number], default: "" },
  mode: {
    type: String,
    default: "mock",
    validator: (value) => ["mock", "remote"].includes(value),
  },
});

const isRemoteMode = computed(() => props.mode === "remote");
const catScroller = ref(null);

const mockSelectedCompetition = ref(
  props.initialCompetitionId || props.competitions?.[0]?.id || ""
);
const mockSelectedCategoryId = ref(props.categories?.[0]?.id ?? null);

const remoteLoading = ref(false);
const remoteSaving = ref(false);
const remoteDrawGenerating = ref(false);
const remoteNotice = ref({ type: "", message: "" });
const remoteContext = ref(null);
const remoteFormDefinition = ref(null);
const remoteErrors = ref({});
const remoteSelectedCategoryId = ref(null);
const remoteSelectedRondaId = ref(null);
const remoteSelectedTeamId = ref("");
const timeInputDigits = ref("");

const mockNotice = ref({ type: "", message: "" });
const mockErrors = ref({});
const mockForm = reactive({
  subcategoria: "",
  equipo_id: "",
  observaciones: "",
  payload: {},
});

const remoteForm = reactive({
  observaciones: "",
  motivo_cambio: "",
  version: 0,
  payload: {},
});

const mockTeams = [
  { id: "1", nombre: "ESPOCH Team A" },
  { id: "2", nombre: "ESPOCH Warriors" },
  { id: "3", nombre: "EPN Robotics" },
  { id: "4", nombre: "UCE Force" },
  { id: "5", nombre: "PUCE Tech" },
  { id: "6", nombre: "UTA Fighters" },
];

function makeSvgDataUri(title) {
  const safe = String(title)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");

  const svg = `
  <svg xmlns="http://www.w3.org/2000/svg" width="640" height="360">
    <defs>
      <linearGradient id="g" x1="0" x2="1" y1="0" y2="1">
        <stop offset="0" stop-color="#1d4ed8"/>
        <stop offset="1" stop-color="#0f766e"/>
      </linearGradient>
    </defs>
    <rect width="100%" height="100%" fill="url(#g)"/>
    <circle cx="520" cy="90" r="110" fill="rgba(255,255,255,0.12)"/>
    <circle cx="120" cy="300" r="140" fill="rgba(255,255,255,0.08)"/>
    <text x="42" y="320" font-size="40" font-family="Arial, sans-serif" fill="white" opacity="0.95" font-weight="700">
      ${safe}
    </text>
  </svg>`;

  return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
}

const categoryThumb = (cat) => cat.imagen_url || makeSvgDataUri(cat.nombre);

function scrollCats(dir) {
  if (!catScroller.value) return;
  catScroller.value.scrollBy({
    left: dir === "left" ? -420 : 420,
    behavior: "smooth",
  });
}

const remoteCategories = computed(() => remoteContext.value?.categorias ?? []);

const currentCategories = computed(() => {
  if (isRemoteMode.value) {
    return remoteCategories.value.map((item) => ({
      id: item.categoria.id,
      nombre: item.categoria.nombre,
      imagen_url: item.categoria.imagen_url,
      subtitle: `${item.rondas?.length ?? 0} rondas disponibles`,
      mechanism: item.config_calificacion?.mecanismo_nombre ?? "Sin mecanismo",
    }));
  }

  return props.categories.map((item) => ({
    id: item.id,
    nombre: item.nombre,
    imagen_url: item.imagen_url,
    subtitle: item.sub?.length ? `${item.sub.length} subcategorías` : "Sin subcategorías",
    mechanism: item.tipoEvaluacion ?? "registro",
  }));
});

const selectedCategoryId = computed({
  get() {
    return isRemoteMode.value ? remoteSelectedCategoryId.value : mockSelectedCategoryId.value;
  },
  set(value) {
    if (isRemoteMode.value) {
      remoteSelectedCategoryId.value = value;
      return;
    }

    mockSelectedCategoryId.value = value;
  },
});

const selectedCategory = computed(() => {
  if (isRemoteMode.value) {
    return remoteCategories.value.find(
      (item) => Number(item.categoria.id) === Number(remoteSelectedCategoryId.value)
    );
  }

  return props.categories.find((item) => item.id === mockSelectedCategoryId.value) ?? null;
});

const remoteSelectedCompetition = computed(() => {
  return selectedCategory.value?.categoria?.competencia_nombre ?? "";
});

const remoteRounds = computed(() => selectedCategory.value?.rondas ?? []);

const remoteTeams = computed(() => remoteContext.value?.equipos ?? []);

const currentSorteo = computed(() => remoteContext.value?.sorteo ?? null);

const hasCurrentSorteo = computed(() => !isRemoteMode.value || !!currentSorteo.value);

const pendingRemoteTeams = computed(() => {
  if (!isRemoteMode.value) return [];

  return remoteTeams.value.filter((team) => !team.resultado_id);
});

const currentRemoteTeam = computed(() => {
  if (!isRemoteMode.value) return null;

  return remoteTeams.value.find(
    (team) => String(team.equipo_id) === String(remoteSelectedTeamId.value)
  ) ?? null;
});

const completedRemoteTeamsCount = computed(() => {
  if (!isRemoteMode.value) return 0;

  return remoteTeams.value.filter((team) => !!team.resultado_id).length;
});

const currentSorteoLabel = computed(() => {
  if (!currentSorteo.value) return "Sorteo pendiente";
  return currentSorteo.value.tipo_sorteo === "enfrentamiento"
    ? "Llaves de enfrentamiento"
    : "Orden de participacion";
});

const sorteoGroups = computed(() => {
  const detalles = currentSorteo.value?.detalles ?? [];

  if (currentSorteo.value?.tipo_sorteo !== "enfrentamiento") {
    return [];
  }

  return Object.values(
    detalles.reduce((acc, detalle) => {
      const key = detalle.grupo ?? detalle.orden;
      acc[key] ??= { grupo: key, items: [] };
      acc[key].items.push(detalle);
      return acc;
    }, {})
  ).sort((a, b) => Number(a.grupo) - Number(b.grupo));
});

const mockAvailableSubcats = computed(() => selectedCategory.value?.sub ?? []);

const mockEvaluationFields = computed(() => {
  switch (selectedCategory.value?.tipoEvaluacion) {
    case "combate":
      return [
        { key: "victorias", type: "number", label: "Victorias", required: true },
        { key: "derrotas", type: "number", label: "Derrotas", required: false },
        { key: "penalizaciones", type: "number", label: "Penalizaciones", required: false },
      ];
    case "puntos":
      return [{ key: "puntos", type: "number", label: "Puntos", required: true }];
    case "tiempo":
    default:
      return [
        { key: "tiempo", type: "duration", label: "Tiempo", required: true },
        { key: "puntos", type: "number", label: "Puntos opcionales", required: false },
      ];
  }
});

const currentFields = computed(() => {
  if (isRemoteMode.value) {
    return remoteFormDefinition.value?.config_calificacion?.campos ?? [];
  }

  return mockEvaluationFields.value;
});

const isRubricFormat = computed(() => {
  return isRemoteMode.value && ["tabla_evaluacion", "puntaje_jueces"].includes(remoteFormDefinition.value?.config_calificacion?.mecanismo_codigo);
});

const isTablaEnfrentamientoTemplate = computed(() => {
  return isRemoteMode.value
    && remoteFormDefinition.value?.config_calificacion?.mecanismo_codigo === "tabla_evaluacion"
    && remoteFormDefinition.value?.config_calificacion?.plantilla_resultado === "tabla_enfrentamiento_criterios";
});

const isTablaIndividualTemplate = computed(() => {
  return isRemoteMode.value
    && remoteFormDefinition.value?.config_calificacion?.mecanismo_codigo === "tabla_evaluacion"
    && remoteFormDefinition.value?.config_calificacion?.plantilla_resultado === "tabla_individual_criterios";
});

const rubricFields = computed(() => {
  if (!isRubricFormat.value || isTablaEnfrentamientoTemplate.value || isTablaIndividualTemplate.value) return [];

  return currentFields.value.filter((field) => field.type === "number" && field.key !== "penalizaciones");
});

const fightCriterionFields = computed(() => {
  if (!isTablaEnfrentamientoTemplate.value) return [];

  return currentFields.value.filter((field) => field.type === "number" && field.key !== "penalizaciones");
});

const individualCriterionFields = computed(() => {
  if (!isTablaIndividualTemplate.value) return [];

  return currentFields.value.filter((field) => field.type === "number" && field.key !== "penalizaciones");
});

const standardFields = computed(() => {
  if (isTablaEnfrentamientoTemplate.value || isTablaIndividualTemplate.value) {
    return currentFields.value.filter((field) => field.key === "observaciones" || field.type !== "number");
  }

  const fields = isRubricFormat.value
    ? currentFields.value.filter((field) => !rubricFields.value.some((rubric) => rubric.key === field.key))
    : currentFields.value;

  if (isGolesTemplate.value) {
    return fields.filter((field) => !["goles_favor", "goles_contra"].includes(field.key));
  }

  if (isTiempoTemplate.value) {
    return fields.filter((field) => field.key !== "tiempo");
  }

  return fields;
});

const currentFieldValues = computed(() => {
  return isRemoteMode.value ? remoteForm.payload : mockForm.payload;
});

const selectedCompetitionLabel = computed(() => {
  if (isRemoteMode.value) {
    return remoteSelectedCompetition.value || "Competencia asignada";
  }

  const found = props.competitions.find(
    (item) => String(item.id) === String(mockSelectedCompetition.value)
  );

  return found?.nombre ?? "Competencia";
});

const selectedTeamLabel = computed(() => {
  if (isRemoteMode.value) {
    return remoteFormDefinition.value?.equipo?.nombre ?? "Sin equipo";
  }

  return mockTeams.find((item) => item.id === mockForm.equipo_id)?.nombre ?? "Sin equipo";
});

const currentMechanismLabel = computed(() => {
  if (isRemoteMode.value) {
    return remoteFormDefinition.value?.config_calificacion?.mecanismo_nombre ?? "Configuración dinámica";
  }

  return selectedCategory.value?.tipoEvaluacion ?? "registro";
});

const currentUnitLabel = computed(() => {
  if (isRemoteMode.value) {
    return remoteFormDefinition.value?.config_calificacion?.unidad_resultado || "sin unidad";
  }

  return selectedCategory.value?.tipoEvaluacion === "tiempo" ? "segundos" : "puntos";
});

const hasRemoteObservacionesField = computed(() => {
  if (!isRemoteMode.value) return false;
  return currentFields.value.some((field) => field.key === "observaciones");
});

const isGolesTemplate = computed(() => {
  return isRemoteMode.value
    && remoteFormDefinition.value?.config_calificacion?.mecanismo_codigo === "registro_resultado"
    && remoteFormDefinition.value?.config_calificacion?.plantilla_resultado === "goles";
});

const isTiempoTemplate = computed(() => {
  return isRemoteMode.value
    && remoteFormDefinition.value?.config_calificacion?.mecanismo_codigo === "registro_resultado"
    && remoteFormDefinition.value?.config_calificacion?.plantilla_resultado === "tiempo";
});

const timeDisplayParts = computed(() => {
  const padded = String(timeInputDigits.value || "").replace(/\D/g, "").slice(-6).padStart(6, "0");

  return {
    hours: padded.slice(0, 2),
    minutes: padded.slice(2, 4),
    seconds: padded.slice(4, 6),
  };
});

function durationDigitsFromValue(value) {
  if (value === null || value === undefined || value === "") return "";

  const raw = String(value).trim();

  if (/^\d+$/.test(raw)) {
    const seconds = Number(raw);
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const rest = Math.floor(seconds % 60);
    return `${String(hours).padStart(2, "0")}${String(minutes).padStart(2, "0")}${String(rest).padStart(2, "0")}`.replace(/^0+(?=\d)/, "");
  }

  const parts = raw.split(":").map((part) => part.replace(/\D/g, ""));

  if (parts.length === 3) {
    return `${parts[0].padStart(2, "0")}${parts[1].padStart(2, "0")}${parts[2].padStart(2, "0")}`.slice(-6).replace(/^0+(?=\d)/, "");
  }

  if (parts.length === 2) {
    return `00${parts[0].padStart(2, "0")}${parts[1].padStart(2, "0")}`.slice(-6).replace(/^0+(?=\d)/, "");
  }

  return raw.replace(/\D/g, "").slice(-6).replace(/^0+(?=\d)/, "");
}

function digitsToDuration(digits) {
  const padded = String(digits || "").replace(/\D/g, "").slice(-6).padStart(6, "0");
  return `${padded.slice(0, 2)}:${padded.slice(2, 4)}:${padded.slice(4, 6)}`;
}

function onTimeDigitsInput(event) {
  const digits = String(event.target.value || "").replace(/\D/g, "").slice(-6).replace(/^0+(?=\d)/, "");
  event.target.value = digits;
  timeInputDigits.value = digits;
  currentFieldValues.value.tiempo = digits ? digitsToDuration(digits) : "";
}

function blockNonNumericInput(event) {
  if (event.data && /\D/.test(event.data)) {
    event.preventDefault();
  }
}

function onTimeDigitsPaste(event) {
  event.preventDefault();
  const text = event.clipboardData?.getData("text") ?? "";
  const digits = text.replace(/\D/g, "").slice(-6).replace(/^0+(?=\d)/, "");
  timeInputDigits.value = digits;
  currentFieldValues.value.tiempo = digits ? digitsToDuration(digits) : "";
}

const currentMatchItems = computed(() => {
  if (!isRemoteMode.value || currentSorteo.value?.tipo_sorteo !== "enfrentamiento" || !currentRemoteTeam.value?.sorteo_grupo) return [];

  const group = sorteoGroups.value.find(
    (item) => Number(item.grupo) === Number(currentRemoteTeam.value.sorteo_grupo)
  );

  return (group?.items ?? []).slice().sort((left, right) => {
    const order = { A: 1, B: 2, BYE: 3 };
    return (order[left.lado] ?? 9) - (order[right.lado] ?? 9);
  });
});

const scoreboardTeamA = computed(() => {
  return currentMatchItems.value.find((item) => item.lado === "A") ?? currentMatchItems.value[0] ?? currentRemoteTeam.value;
});

const scoreboardTeamB = computed(() => {
  return currentMatchItems.value.find((item) => item.lado === "B") ?? null;
});

function scoreFieldForSide(side) {
  const currentSide = currentRemoteTeam.value?.sorteo_lado;
  return currentSide === side ? "goles_favor" : "goles_contra";
}

function fightFieldKey(field, side) {
  return `${field.key}_${String(side).toLowerCase()}`;
}

function fightQuantity(field, side) {
  return Number(currentFieldValues.value[fightFieldKey(field, side)] || 0);
}

function fightScore(field, side) {
  const raw = fightQuantity(field, side) * Number(field?.valor_unitario || 0);
  return field?.es_penalizacion ? -raw : raw;
}

function fightSubtotal(side) {
  return fightCriterionFields.value
    .filter((field) => !field.es_penalizacion)
    .reduce((sum, field) => sum + fightScore(field, side), 0);
}

function fightPenaltyTotal(side) {
  return fightCriterionFields.value
    .filter((field) => field.es_penalizacion)
    .reduce((sum, field) => sum + Math.abs(fightScore(field, side)), 0);
}

function fightTotal(side) {
  return fightSubtotal(side) - fightPenaltyTotal(side);
}

function individualQuantity(field) {
  return Number(currentFieldValues.value[field.key] || 0);
}

function individualScore(field) {
  const raw = individualQuantity(field) * Number(field?.valor_unitario || 0);
  return field?.es_penalizacion ? -raw : raw;
}

function individualSubtotal() {
  return individualCriterionFields.value
    .filter((field) => !field.es_penalizacion)
    .reduce((sum, field) => sum + individualScore(field), 0);
}

function individualPenaltyTotal() {
  return individualCriterionFields.value
    .filter((field) => field.es_penalizacion)
    .reduce((sum, field) => sum + Math.abs(individualScore(field)), 0);
}

function individualTotal() {
  return individualSubtotal() - individualPenaltyTotal();
}

const currentVersion = computed(() => {
  return isRemoteMode.value ? Number(remoteForm.version || 0) : 0;
});

const currentResultState = computed(() => {
  return remoteFormDefinition.value?.resultado_actual?.estado ?? null;
});

const canEditRemoteResult = computed(() => {
  if (!isRemoteMode.value) return true;

  if (!remoteFormDefinition.value?.resultado_actual) {
    return true;
  }

  return !!remoteFormDefinition.value?.config_calificacion?.permite_edicion_juez;
});

const scorePreview = computed(() => {
  const values = currentFieldValues.value;

  if (isRemoteMode.value) {
    const mechanism = remoteFormDefinition.value?.config_calificacion?.mecanismo_codigo;
    const template = remoteFormDefinition.value?.config_calificacion?.plantilla_resultado;

    if (mechanism === "registro_resultado") {
      if (template === "goles") {
        return `${Number(values.goles_favor || 0)} - ${Number(values.goles_contra || 0)}`;
      }

      if (template === "ganador") {
        const result = values.resultado || "sin resultado";
        const points = Number(values.puntos || 0);
        return points ? `${result} (${points})` : result;
      }

      if (values.resultado) {
        const points = Number(values.puntos || 0);
        return points ? `${values.resultado} (${points})` : values.resultado;
      }

      const time = Number(values.tiempo || 0);
      const penalty = Number(values.penalizaciones || 0);
      return time + penalty || values.valor_principal || values.puntaje || values.tiempo || "0";
    }

    if (mechanism === "tabla_evaluacion") {
      if (template === "tabla_individual_criterios") {
        return individualTotal();
      }

      if (template === "tabla_enfrentamiento_criterios") {
        const side = currentRemoteTeam.value?.sorteo_lado === "B" ? "B" : "A";
        return fightTotal(side);
      }

      const total = dynamicFields.value
        .filter((field) => field.type === "number" && !["penalizaciones"].includes(field.key))
        .reduce((sum, field) => sum + Number(values[field.key] || 0), 0);
      return total - Number(values.penalizaciones || 0);
    }

    if (mechanism === "combate") {
      return Number(values.victorias || 0) - Number(values.derrotas || 0);
    }

    if (mechanism === "combate_llaves") {
      const result = values.resultado || "sin resultado";
      const points = Number(values.puntos || 0);
      return points ? `${result} (${points})` : result;
    }

    if (mechanism === "soccer_goles") {
      return `${Number(values.goles_favor || 0)} - ${Number(values.goles_contra || 0)}`;
    }

    if (["cronometro", "dron_carrera"].includes(mechanism)) {
      const time = Number(values.tiempo || 0);
      const penalty = Number(values.penalizaciones || values.penalizaciones_segundos || 0);
      return time + penalty || values.tiempo || remoteFormDefinition.value?.resultado_actual?.payload?.tiempo || "0";
    }

    if (["puntaje", "puntaje_jueces", "mixto", "dron_destreza"].includes(mechanism)) {
      return Number(values.puntaje || 0) - Number(values.penalizaciones || 0);
    }

    return values.valor_principal || values.puntaje || values.tiempo || "N/A";
  }

  if (selectedCategory.value?.tipoEvaluacion === "combate") {
    return Math.max(0, Number(values.victorias || 0) * 30 - Number(values.derrotas || 0) * 10);
  }

  if (selectedCategory.value?.tipoEvaluacion === "puntos") {
    return Number(values.puntos || 0);
  }

  return values.tiempo || "0";
});

function setNotice(target, type, message) {
  target.value = { type, message };
}

function resetRemoteErrors() {
  remoteErrors.value = {};
}

function resetMockErrors() {
  mockErrors.value = {};
}

function clearRemoteForm() {
  remoteFormDefinition.value = null;
  remoteForm.observaciones = "";
  remoteForm.motivo_cambio = "";
  remoteForm.version = 0;
  remoteForm.payload = {};
  timeInputDigits.value = "";
  resetRemoteErrors();
}

function initializeFieldPayload(fields, source = {}) {
  const payload = {};

  for (const field of fields) {
    const value = source?.[field.key];
    payload[field.key] = value ?? (isBooleanField(field) ? false : "");
  }

  return payload;
}

function isBooleanField(field) {
  return ["checkbox", "boolean"].includes(field?.type);
}

function isSelectField(field) {
  return field?.type === "select";
}

function hydrateRemoteForm(definition) {
  remoteFormDefinition.value = definition;
  remoteForm.version = definition?.resultado_actual?.version ?? 0;
  remoteForm.observaciones = definition?.resultado_actual?.observaciones ?? "";
  remoteForm.motivo_cambio = "";
  remoteForm.payload = initializeFieldPayload(
    definition?.config_calificacion?.campos ?? [],
    definition?.resultado_actual?.payload ?? {}
  );

  if (isTablaEnfrentamientoTemplate.value) {
    for (const field of fightCriterionFields.value) {
      remoteForm.payload[fightFieldKey(field, "A")] = definition?.resultado_actual?.payload?.[fightFieldKey(field, "A")] ?? "";
      remoteForm.payload[fightFieldKey(field, "B")] = definition?.resultado_actual?.payload?.[fightFieldKey(field, "B")] ?? "";
    }
  }

  timeInputDigits.value = durationDigitsFromValue(remoteForm.payload.tiempo);
  resetRemoteErrors();
}

function getFieldError(key) {
  return remoteErrors.value[`payload.${key}`] || mockErrors.value[`payload.${key}`] || "";
}

function getTopLevelError(key) {
  return remoteErrors.value[key] || mockErrors.value[key] || "";
}

async function loadRemoteContext(options = {}) {
  remoteLoading.value = true;
  setNotice(remoteNotice, "", "");

  try {
    const params = {};

    if (options.categoriaId ?? remoteSelectedCategoryId.value) {
      params.categoria_id = Number(options.categoriaId ?? remoteSelectedCategoryId.value);
    }

    if (options.rondaId ?? remoteSelectedRondaId.value) {
      params.ronda_id = Number(options.rondaId ?? remoteSelectedRondaId.value);
    }

    const { data } = await axios.get("/juez/evaluaciones/contexto", { params });
    const previousTeamId = options.preserveTeam ? String(remoteSelectedTeamId.value || "") : "";

    remoteContext.value = data;
    remoteSelectedCategoryId.value = data?.seleccion?.categoria_id ?? null;
    remoteSelectedRondaId.value = data?.seleccion?.ronda_id ?? null;

    const nextPendingTeam = data?.equipos?.find((item) => !item.resultado_id);
    const stillPending = data?.equipos?.some(
      (item) => String(item.equipo_id) === previousTeamId && !item.resultado_id
    );

    remoteSelectedTeamId.value = stillPending
      ? previousTeamId
      : String(nextPendingTeam?.equipo_id ?? "");

    if (!data?.sorteo) {
      clearRemoteForm();
      return;
    }

    if (remoteSelectedTeamId.value) {
      await loadRemoteForm();
    } else {
      clearRemoteForm();
    }
  } catch (error) {
    clearRemoteForm();
    remoteContext.value = null;
    setNotice(
      remoteNotice,
      "error",
      error?.response?.data?.message || "No se pudo cargar el contexto de evaluación del juez."
    );
  } finally {
    remoteLoading.value = false;
  }
}

async function loadRemoteForm() {
  if (isRemoteMode.value && !currentSorteo.value) {
    clearRemoteForm();
    return;
  }

  if (!remoteSelectedRondaId.value || !remoteSelectedTeamId.value) {
    clearRemoteForm();
    return;
  }

  try {
    const { data } = await axios.get("/juez/evaluaciones/formulario", {
      params: {
        ronda_id: Number(remoteSelectedRondaId.value),
        equipo_id: Number(remoteSelectedTeamId.value),
      },
    });

    hydrateRemoteForm(data);
  } catch (error) {
    clearRemoteForm();
    setNotice(
      remoteNotice,
      "error",
      error?.response?.data?.message || "No se pudo cargar el formulario de evaluación."
    );
  }
}

async function onRemoteCategoryChange() {
  await loadRemoteContext({
    categoriaId: remoteSelectedCategoryId.value,
  });
}

async function onRemoteRondaChange() {
  await loadRemoteContext({
    categoriaId: remoteSelectedCategoryId.value,
    rondaId: remoteSelectedRondaId.value,
  });
}

async function onRemoteTeamChange() {
  await loadRemoteForm();
}

async function generarSorteoRemoto() {
  if (!remoteSelectedRondaId.value) {
    setNotice(remoteNotice, "error", "Selecciona una ronda antes de generar el sorteo.");
    return;
  }

  const regenerar = !!currentSorteo.value;

  if (
    regenerar &&
    !window.confirm("Se generará un nuevo sorteo para esta ronda. El sorteo anterior quedará anulado. ¿Deseas continuar?")
  ) {
    return;
  }

  remoteDrawGenerating.value = true;
  setNotice(remoteNotice, "", "");

  try {
    await axios.post("/juez/evaluaciones/sorteo", {
      ronda_id: Number(remoteSelectedRondaId.value),
      regenerar,
    });

    await loadRemoteContext({
      categoriaId: remoteSelectedCategoryId.value,
      rondaId: remoteSelectedRondaId.value,
    });

    setNotice(
      remoteNotice,
      "success",
      regenerar ? "Sorteo generado nuevamente." : "Sorteo generado correctamente."
    );
  } catch (error) {
    setNotice(
      remoteNotice,
      "error",
      error?.response?.data?.message ||
        Object.values(error?.response?.data?.errors ?? {})?.[0]?.[0] ||
        (regenerar ? "No se pudo generar nuevamente el sorteo." : "No se pudo generar el sorteo.")
    );
  } finally {
    remoteDrawGenerating.value = false;
  }
}

function pickCategory(categoryId) {
  selectedCategoryId.value = categoryId;

  if (isRemoteMode.value) {
    onRemoteCategoryChange();
    return;
  }

  mockForm.subcategoria = "";
  mockForm.equipo_id = "";
  mockForm.payload = initializeFieldPayload(mockEvaluationFields.value);
  resetMockErrors();
}

function normalizeRemotePayload() {
  const payload = {};
  const currentSide = currentRemoteTeam.value?.sorteo_lado === "B" ? "B" : "A";

  for (const field of currentFields.value) {
    const value = isTablaEnfrentamientoTemplate.value && field.type === "number"
      ? remoteForm.payload[fightFieldKey(field, currentSide)]
      : remoteForm.payload[field.key];

    payload[field.key] = isBooleanField(field) ? Boolean(value) : value === "" ? null : value;

    if (isTablaEnfrentamientoTemplate.value && field.type === "number") {
      payload[fightFieldKey(field, "A")] = remoteForm.payload[fightFieldKey(field, "A")] ?? null;
      payload[fightFieldKey(field, "B")] = remoteForm.payload[fightFieldKey(field, "B")] ?? null;
    }
  }

  return payload;
}

async function registrarResultadoRemoto() {
  if (!currentSorteo.value) {
    setNotice(remoteNotice, "error", "Genera el sorteo antes de registrar resultados.");
    return;
  }

  if (!remoteSelectedRondaId.value || !remoteSelectedTeamId.value) {
    setNotice(remoteNotice, "error", "Selecciona una ronda y un equipo antes de guardar.");
    return;
  }

  if (!canEditRemoteResult.value) {
    setNotice(
      remoteNotice,
      "warning",
      "Esta categoría no permite editar el resultado del juez una vez guardado."
    );
    return;
  }

  remoteSaving.value = true;
  resetRemoteErrors();
  setNotice(remoteNotice, "", "");

  try {
    const payload = normalizeRemotePayload();
    const observaciones =
      payload.observaciones !== undefined && payload.observaciones !== null
        ? String(payload.observaciones)
        : remoteForm.observaciones || null;

    const { data } = await axios.post("/juez/evaluaciones", {
      ronda_id: Number(remoteSelectedRondaId.value),
      equipo_id: Number(remoteSelectedTeamId.value),
      version: Number(remoteForm.version || 0),
      observaciones,
      motivo_cambio: remoteForm.motivo_cambio || null,
      payload,
    });

    hydrateRemoteForm(data);
    await loadRemoteContext();
    setNotice(
      remoteNotice,
      "success",
      remoteSelectedTeamId.value
        ? "Evaluacion guardada correctamente. Se cargó el siguiente participante."
        : "Evaluacion guardada correctamente. Ya no hay participantes pendientes."
    );
  } catch (error) {
    if (error?.response?.status === 409) {
      setNotice(
        remoteNotice,
        "warning",
        error?.response?.data?.message ||
          "La evaluación fue actualizada por otra sesión. Se recargó el formulario."
      );
      await loadRemoteContext({ preserveTeam: true });
      return;
    }

    if (error?.response?.status === 422) {
      remoteErrors.value = error.response.data?.errors ?? {};
      setNotice(remoteNotice, "error", "Revisa los datos del formulario.");
      return;
    }

    setNotice(
      remoteNotice,
      "error",
      error?.response?.data?.message || "No se pudo guardar la evaluación."
    );
  } finally {
    remoteSaving.value = false;
  }
}

function resetMockForm() {
  mockForm.subcategoria = "";
  mockForm.equipo_id = "";
  mockForm.observaciones = "";
  mockForm.payload = initializeFieldPayload(mockEvaluationFields.value);
  resetMockErrors();
}

function registrarResultadoMock() {
  resetMockErrors();
  setNotice(mockNotice, "", "");

  if (!mockSelectedCategoryId.value) {
    mockErrors.value.categoria_id = "Selecciona una categoría.";
  }

  if (mockAvailableSubcats.value.length && !mockForm.subcategoria) {
    mockErrors.value.subcategoria = "Selecciona una subcategoría.";
  }

  if (!mockForm.equipo_id) {
    mockErrors.value.equipo_id = "Selecciona un equipo.";
  }

  for (const field of mockEvaluationFields.value) {
    if (field.required && !String(mockForm.payload[field.key] ?? "").trim()) {
      mockErrors.value[`payload.${field.key}`] = `El campo ${field.label} es obligatorio.`;
    }
  }

  if (Object.keys(mockErrors.value).length > 0) {
    setNotice(mockNotice, "error", "Completa los campos obligatorios del demo.");
    return;
  }

  setNotice(mockNotice, "success", "Registro mock listo. Luego lo conectamos al flujo admin.");
}

async function registrarResultado() {
  if (isRemoteMode.value) {
    await registrarResultadoRemoto();
    return;
  }

  registrarResultadoMock();
}

function limpiarRegistro() {
  if (isRemoteMode.value) {
    if (remoteFormDefinition.value) {
      hydrateRemoteForm(remoteFormDefinition.value);
    } else {
      clearRemoteForm();
    }
    setNotice(remoteNotice, "", "");
    return;
  }

  resetMockForm();
  setNotice(mockNotice, "", "");
}

function openPublicView() {
  if (isRemoteMode.value) return;
  setNotice(mockNotice, "warning", "La vista pública se conectará más adelante.");
}

watch(
  () => props.categories,
  () => {
    if (!isRemoteMode.value) {
      mockSelectedCategoryId.value = props.categories?.[0]?.id ?? null;
      resetMockForm();
    }
  },
  { immediate: true }
);

watch(
  () => mockSelectedCategoryId.value,
  () => {
    if (!isRemoteMode.value) {
      resetMockForm();
    }
  }
);

onMounted(async () => {
  if (isRemoteMode.value) {
    await loadRemoteContext();
    return;
  }

  resetMockForm();
});
</script>

<template>
  <div class="space-y-6">
    <div
      class="rounded-2xl border border-slate-200 bg-white shadow-sm p-4"
      :class="isRemoteMode ? 'border-blue-200 bg-blue-50/40' : ''"
    >
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex-1">
          <label class="block text-sm font-medium text-slate-700 mb-1">
            {{ isRemoteMode ? "Competencia asignada" : "Competencia" }}
          </label>

          <template v-if="isRemoteMode">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-900">
              {{ selectedCompetitionLabel }}
            </div>
          </template>

          <template v-else>
            <select
              v-model="mockSelectedCompetition"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option disabled value="">Seleccionar competencia</option>
              <option v-for="competition in competitions" :key="competition.id" :value="competition.id">
                {{ competition.nombre }}
              </option>
            </select>
          </template>
        </div>

        <button
          v-if="!isRemoteMode"
          type="button"
          @click="openPublicView"
          class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 transition hover:bg-slate-50"
        >
          <EyeIcon class="h-5 w-5 text-slate-700" />
          Vista pública
        </button>

        <div
          v-else
          class="rounded-xl border border-blue-200 bg-white px-4 py-3 text-sm text-slate-700"
        >
          Tus categorías y equipos se cargan desde las asignaciones activas del juez.
        </div>
      </div>
    </div>

    <div v-if="(isRemoteMode && remoteNotice.message) || (!isRemoteMode && mockNotice.message)"
      class="rounded-2xl border px-4 py-3 text-sm"
      :class="{
        'border-emerald-200 bg-emerald-50 text-emerald-700': (isRemoteMode ? remoteNotice.type : mockNotice.type) === 'success',
        'border-amber-200 bg-amber-50 text-amber-800': (isRemoteMode ? remoteNotice.type : mockNotice.type) === 'warning',
        'border-red-200 bg-red-50 text-red-700': (isRemoteMode ? remoteNotice.type : mockNotice.type) === 'error',
      }"
    >
      {{ isRemoteMode ? remoteNotice.message : mockNotice.message }}
    </div>

    <div
      v-if="isRemoteMode && remoteLoading && !remoteContext"
      class="rounded-2xl border border-slate-200 bg-white px-6 py-8 text-center text-slate-500 shadow-sm"
    >
      Cargando asignaciones y formulario de evaluación...
    </div>

    <template v-else>
      <div
        v-if="isRemoteMode && remoteLoading && remoteContext"
        class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-700"
      >
        Actualizando la categoría seleccionada...
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-4">
        <div class="mb-3 flex items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <Squares2X2Icon class="h-5 w-5 text-slate-700" />
            <h3 class="text-sm font-semibold text-slate-900">Categorías</h3>
            <span class="text-xs text-slate-500">
              {{ isRemoteMode ? "Tus categorías asignadas" : "Selecciona una para registrar resultados" }}
            </span>
          </div>

          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="scrollCats('left')"
              class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white transition hover:bg-slate-50"
            >
              <ChevronLeftIcon class="h-5 w-5 text-slate-700" />
            </button>

            <button
              type="button"
              @click="scrollCats('right')"
              class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white transition hover:bg-slate-50"
            >
              <ChevronRightIcon class="h-5 w-5 text-slate-700" />
            </button>
          </div>
        </div>

        <div
          v-if="currentCategories.length"
          ref="catScroller"
          class="flex gap-4 overflow-x-auto pb-2 scroll-smooth snap-x snap-mandatory"
        >
          <button
            v-for="category in currentCategories"
            :key="category.id"
            type="button"
            @click="pickCategory(category.id)"
            class="w-[220px] shrink-0 snap-start overflow-hidden rounded-2xl border text-left transition hover:shadow-md"
            :class="Number(selectedCategoryId) === Number(category.id) ? 'border-blue-400 ring-2 ring-blue-100' : 'border-slate-200'"
          >
            <div class="h-[110px] w-full bg-cover bg-center" :style="{ backgroundImage: `url(${categoryThumb(category)})` }" />

            <div class="p-3">
              <p class="font-semibold text-slate-900 leading-tight">{{ category.nombre }}</p>
              <p class="mt-1 text-xs text-slate-500">{{ category.subtitle }}</p>
              <p class="mt-2 text-xs font-medium text-slate-700">{{ category.mechanism }}</p>
            </div>
          </button>
        </div>

        <div
          v-else
          class="rounded-2xl border border-dashed border-slate-300 px-6 py-8 text-center text-slate-500"
        >
          No hay categorías disponibles para este módulo todavía.
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white shadow-sm p-5 sm:p-6">
          <div class="mb-5 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <ClipboardDocumentListIcon class="h-5 w-5 text-blue-600" />
              <h3 class="text-lg font-semibold text-slate-900">Registro de evaluación</h3>
            </div>

            <span
              v-if="selectedCategory"
              class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-200"
            >
              {{ isRemoteMode ? selectedCategory.categoria.nombre : selectedCategory.nombre }}
            </span>
          </div>

          <div class="space-y-4">
            <div v-if="isRemoteMode">
              <label class="mb-1 block text-sm font-semibold text-slate-800">
                Ronda <span class="text-red-500">*</span>
              </label>
              <select
                v-model="remoteSelectedRondaId"
                @change="onRemoteRondaChange"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option disabled value="">Selecciona una ronda</option>
                <option v-for="round in remoteRounds" :key="round.id" :value="round.id">
                  {{ round.nombre }}
                </option>
              </select>
              <p v-if="getTopLevelError('ronda_id')" class="mt-1 text-xs text-red-600">
                {{ getTopLevelError("ronda_id") }}
              </p>
            </div>

            <div
              v-if="isRemoteMode"
              class="rounded-2xl border p-4"
              :class="currentSorteo ? 'border-blue-200 bg-blue-50/50' : 'border-amber-200 bg-amber-50'"
            >
              <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-3">
                  <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl"
                    :class="currentSorteo ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'"
                  >
                    <SparklesIcon class="h-6 w-6" />
                  </div>

                  <div>
                    <p class="text-sm font-semibold text-slate-900">Sorteo de la ronda</p>
                    <p class="mt-1 text-sm text-slate-600">
                      {{ currentSorteo ? currentSorteoLabel : "Genera el sorteo antes de registrar resultados." }}
                    </p>
                  </div>
                </div>

                <button
                  type="button"
                  @click="generarSorteoRemoto"
                  :disabled="remoteDrawGenerating || !remoteSelectedRondaId"
                  class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50"
                  :class="currentSorteo ? 'border border-blue-200 bg-white text-blue-700 hover:bg-blue-50' : 'bg-blue-600 text-white hover:bg-blue-700'"
                >
                  <SparklesIcon class="h-5 w-5" />
                  {{ remoteDrawGenerating ? "Generando..." : currentSorteo ? "Generar nuevamente" : "Generar sorteo" }}
                </button>
              </div>

              <div v-if="currentSorteo?.tipo_sorteo === 'individual'" class="mt-4 overflow-hidden rounded-2xl border border-blue-100 bg-white">
                <div class="max-h-80 overflow-y-auto">
                  <table class="min-w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-blue-50 text-left text-xs uppercase tracking-wide text-blue-700">
                      <tr>
                        <th class="w-20 px-4 py-3 font-semibold">Orden</th>
                        <th class="px-4 py-3 font-semibold">Participante</th>
                        <th class="px-4 py-3 font-semibold">Institucion</th>
                        <th class="w-28 px-4 py-3 font-semibold">Estado</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                      <tr
                        v-for="detalle in currentSorteo.detalles"
                        :key="`orden-${detalle.id}`"
                        class="transition"
                        :class="String(detalle.equipo_id) === String(remoteSelectedTeamId) ? 'bg-blue-50/80' : 'bg-white'"
                      >
                        <td class="px-4 py-3">
                          <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">
                            {{ detalle.orden }}
                          </span>
                        </td>
                        <td class="px-4 py-3 font-semibold text-slate-900">{{ detalle.equipo_nombre }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ detalle.institucion || "Sin institucion" }}</td>
                        <td class="px-4 py-3">
                          <span
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="remoteTeams.some((team) => Number(team.equipo_id) === Number(detalle.equipo_id) && team.resultado_id)
                              ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
                              : String(detalle.equipo_id) === String(remoteSelectedTeamId)
                                ? 'bg-blue-600 text-white'
                                : 'bg-slate-100 text-slate-600'"
                          >
                            {{
                              remoteTeams.some((team) => Number(team.equipo_id) === Number(detalle.equipo_id) && team.resultado_id)
                                ? "Calificado"
                                : String(detalle.equipo_id) === String(remoteSelectedTeamId)
                                  ? "Actual"
                                  : "Pendiente"
                            }}
                          </span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <div v-else-if="currentSorteo?.tipo_sorteo === 'enfrentamiento'" class="mt-4 overflow-hidden rounded-2xl border border-blue-100 bg-white">
                <div class="max-h-80 overflow-y-auto">
                  <table class="min-w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-blue-50 text-left text-xs uppercase tracking-wide text-blue-700">
                      <tr>
                        <th class="w-24 px-4 py-3 font-semibold">Combate</th>
                        <th class="px-4 py-3 font-semibold">Equipo A</th>
                        <th class="px-4 py-3 font-semibold">Equipo B</th>
                        <th class="w-28 px-4 py-3 font-semibold">Estado</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                      <tr v-for="group in sorteoGroups" :key="`grupo-${group.grupo}`">
                        <td class="px-4 py-3 font-semibold text-slate-900">{{ group.grupo }}</td>
                        <td class="px-4 py-3">
                          <p class="font-semibold text-slate-900">{{ group.items.find((item) => item.lado === 'A')?.equipo_nombre || group.items[0]?.equipo_nombre || "-" }}</p>
                          <p class="text-xs text-slate-500">{{ group.items.find((item) => item.lado === 'A')?.institucion || group.items[0]?.institucion || "Sin institucion" }}</p>
                        </td>
                        <td class="px-4 py-3">
                          <template v-if="group.items.some((item) => item.lado === 'BYE')">
                            <p class="font-semibold text-blue-700">Pasa directo</p>
                            <p class="text-xs text-slate-500">Bye automatico</p>
                          </template>
                          <template v-else>
                            <p class="font-semibold text-slate-900">{{ group.items.find((item) => item.lado === 'B')?.equipo_nombre || "-" }}</p>
                            <p class="text-xs text-slate-500">{{ group.items.find((item) => item.lado === 'B')?.institucion || "Sin institucion" }}</p>
                          </template>
                        </td>
                        <td class="px-4 py-3">
                          <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                            Pendiente
                          </span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div v-else-if="mockAvailableSubcats.length">
              <label class="mb-1 block text-sm font-semibold text-slate-800">
                Subcategoría <span class="text-red-500">*</span>
              </label>
              <select
                v-model="mockForm.subcategoria"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option disabled value="">Selecciona una subcategoría</option>
                <option v-for="subcat in mockAvailableSubcats" :key="subcat" :value="subcat">
                  {{ subcat }}
                </option>
              </select>
              <p v-if="getTopLevelError('subcategoria')" class="mt-1 text-xs text-red-600">
                {{ getTopLevelError("subcategoria") }}
              </p>
            </div>

            <div v-if="isRemoteMode">
              <label class="mb-1 block text-sm font-semibold text-slate-800">
                Equipo actual <span class="text-red-500">*</span>
              </label>

              <div
                v-if="currentRemoteTeam"
                class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-4"
              >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">
                      Participante {{ currentRemoteTeam.sorteo_orden ? `#${currentRemoteTeam.sorteo_orden}` : "actual" }}
                    </p>
                    <p class="mt-1 text-xl font-bold text-slate-950">{{ currentRemoteTeam.equipo_nombre }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ currentRemoteTeam.institucion || "Sin institucion" }}</p>
                  </div>
                  <span class="inline-flex rounded-full bg-white px-3 py-1.5 text-sm font-semibold text-blue-700 ring-1 ring-blue-200">
                    {{ pendingRemoteTeams.length }} pendientes
                  </span>
                </div>
              </div>

              <div
                v-else
                class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-600"
              >
                {{
                  currentSorteo
                    ? "Todos los participantes de esta ronda ya fueron calificados."
                    : "Genera el sorteo para cargar el primer participante."
                }}
              </div>

              <template v-if="false">
                <select
                  v-model="remoteSelectedTeamId"
                  @change="onRemoteTeamChange"
                  :disabled="!hasCurrentSorteo"
                  class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  <option disabled value="">Selecciona un equipo</option>
                  <option v-for="team in remoteTeams" :key="team.equipo_id" :value="String(team.equipo_id)">
                    {{ team.sorteo_orden ? `${team.sorteo_orden}. ` : '' }}{{ team.equipo_nombre }} - {{ team.institucion || 'Sin institución' }}
                  </option>
                </select>
              </template>

              <template v-if="false">
                <select
                  v-model="mockForm.equipo_id"
                  class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option disabled value="">Selecciona un equipo</option>
                  <option v-for="team in mockTeams" :key="team.id" :value="team.id">
                    {{ team.nombre }}
                  </option>
                </select>
              </template>

              <p v-if="getTopLevelError('equipo_id')" class="mt-1 text-xs text-red-600">
                {{ getTopLevelError("equipo_id") }}
              </p>
            </div>

            <div v-else>
              <label class="mb-1 block text-sm font-semibold text-slate-800">
                Equipo <span class="text-red-500">*</span>
              </label>

              <select
                v-model="mockForm.equipo_id"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option disabled value="">Selecciona un equipo</option>
                <option v-for="team in mockTeams" :key="team.id" :value="team.id">
                  {{ team.nombre }}
                </option>
              </select>

              <p v-if="getTopLevelError('equipo_id')" class="mt-1 text-xs text-red-600">
                {{ getTopLevelError("equipo_id") }}
              </p>
            </div>

            <div
              v-if="isRemoteMode && remoteFormDefinition"
              class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"
            >
              <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div>
                  <p class="font-semibold text-slate-900">Estado</p>
                  <p>{{ currentResultState || "sin registro" }}</p>
                </div>

                <div>
                  <p class="font-semibold text-slate-900">Version</p>
                  <p>{{ currentVersion }}</p>
                </div>

                <div>
                  <p class="font-semibold text-slate-900">Orden ranking</p>
                  <p>{{ remoteFormDefinition.config_calificacion.orden_ranking === "asc" ? "Menor primero" : "Mayor primero" }}</p>
                </div>
              </div>
            </div>

            <div
              v-if="isRubricFormat && rubricFields.length"
              class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
            >
              <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-sm font-semibold text-slate-900">Tabla de detalle y puntuación</p>
              </div>

              <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                  <thead>
                    <tr class="border-b border-slate-200 text-left text-slate-600">
                      <th class="px-4 py-3 font-medium">Criterio</th>
                      <th class="px-4 py-3 font-medium w-[120px]">Máximo</th>
                      <th class="px-4 py-3 font-medium w-[180px]">Puntaje</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-200">
                    <tr v-for="field in rubricFields" :key="field.key">
                      <td class="px-4 py-3">
                        <p class="font-medium text-slate-900">{{ field.label }}</p>
                        <p v-if="field.required" class="mt-1 text-xs text-red-600">Obligatorio</p>
                      </td>
                      <td class="px-4 py-3 text-slate-600">
                        {{ field.max ?? "-" }}
                      </td>
                      <td class="px-4 py-3">
                        <input
                          v-model="currentFieldValues[field.key]"
                          type="number"
                          min="0"
                          :max="field.max"
                          step="0.001"
                          class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p v-if="getFieldError(field.key)" class="mt-1 text-xs text-red-600">
                          {{ getFieldError(field.key) }}
                        </p>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div
              v-if="isTablaIndividualTemplate && individualCriterionFields.length"
              class="overflow-hidden rounded-2xl border border-slate-800 bg-[#1f1f1f] text-white shadow-sm"
            >
              <div class="grid grid-cols-[1.15fr_1fr] items-center border-b border-white/10 px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-200">
                <span>Estadisticas</span>
                <span>{{ currentRemoteTeam?.equipo_nombre || "Equipo" }}</span>
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
                      v-for="field in individualCriterionFields.filter((item) => !item.es_penalizacion)"
                      :key="`judge-individual-${field.key}`"
                      class="border-t border-white/5"
                    >
                      <td class="px-3 py-3 text-center">
                        <p class="font-semibold text-white">{{ field.label }}</p>
                        <p class="text-xs text-slate-400">Suma al subtotal</p>
                      </td>
                      <td class="px-3 py-3 text-center text-slate-200">x {{ Number(field.valor_unitario || 0) }}</td>
                      <td class="px-3 py-3 text-center">
                        <input
                          v-model="currentFieldValues[field.key]"
                          type="number"
                          min="0"
                          step="1"
                          class="w-16 rounded-lg border-2 border-emerald-500 bg-transparent px-2 py-1 text-center font-bold text-white outline-none focus:ring-2 focus:ring-emerald-400/30"
                        />
                      </td>
                      <td class="px-3 py-3 text-center">
                        <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                          {{ individualScore(field) }}
                        </span>
                      </td>
                    </tr>

                    <tr class="border-t-4 border-red-600 bg-[#242424] text-sm font-bold">
                      <td colspan="3" class="px-3 py-4 text-center text-white">Subtotal</td>
                      <td class="px-3 py-4 text-center text-yellow-100">{{ individualSubtotal() }}</td>
                    </tr>

                    <tr
                      v-for="field in individualCriterionFields.filter((item) => item.es_penalizacion)"
                      :key="`judge-individual-penalty-${field.key}`"
                      class="border-t border-white/5"
                    >
                      <td class="px-3 py-3 text-center">
                        <p class="font-semibold text-red-400">{{ field.label }}</p>
                        <p class="text-xs text-red-300">Resta al subtotal</p>
                      </td>
                      <td class="px-3 py-3 text-center text-slate-200">x {{ Number(field.valor_unitario || 0) }}</td>
                      <td class="px-3 py-3 text-center">
                        <input
                          v-model="currentFieldValues[field.key]"
                          type="number"
                          min="0"
                          step="1"
                          class="w-16 rounded-lg border-2 border-emerald-500 bg-transparent px-2 py-1 text-center font-bold text-white outline-none focus:ring-2 focus:ring-emerald-400/30"
                        />
                      </td>
                      <td class="px-3 py-3 text-center">
                        <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                          {{ individualScore(field) }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr class="border-t-4 border-red-600 bg-[#242424] text-base font-bold">
                      <td colspan="3" class="px-3 py-5 text-center uppercase tracking-wide text-white">Resultado final</td>
                      <td class="px-3 py-5 text-center text-yellow-100">{{ individualTotal() }}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>

            <div
              v-if="isTablaEnfrentamientoTemplate && fightCriterionFields.length"
              class="overflow-hidden rounded-2xl border border-slate-800 bg-[#1f1f1f] text-white shadow-sm"
            >
              <div class="grid grid-cols-[1fr_1.15fr_1fr] items-center border-b border-white/10 px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-200">
                <span>{{ scoreboardTeamA?.equipo_nombre || "Equipo A" }}</span>
                <span>Estadísticas</span>
                <span>{{ scoreboardTeamB?.equipo_nombre || "Equipo B" }}</span>
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
                      v-for="field in fightCriterionFields.filter((item) => !item.es_penalizacion)"
                      :key="`judge-fight-${field.key}`"
                      class="border-t border-white/5"
                    >
                      <td class="px-3 py-2 text-center">
                        <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                          {{ fightScore(field, 'A') }}
                        </span>
                      </td>
                      <td class="px-3 py-2 text-center">
                        <input
                          v-model="currentFieldValues[fightFieldKey(field, 'A')]"
                          type="number"
                          min="0"
                          step="1"
                          class="w-16 rounded-lg border-2 border-emerald-500 bg-transparent px-2 py-1 text-center font-bold text-white outline-none focus:ring-2 focus:ring-emerald-400/30"
                        />
                      </td>
                      <td class="px-3 py-2 text-center">
                        <p class="font-semibold text-white">{{ field.label }}</p>
                        <p class="text-xs text-slate-400">Suma al subtotal</p>
                      </td>
                      <td class="px-3 py-2 text-center text-slate-200">x {{ Number(field.valor_unitario || 0) }}</td>
                      <td class="px-3 py-2 text-center">
                        <input
                          v-model="currentFieldValues[fightFieldKey(field, 'B')]"
                          type="number"
                          min="0"
                          step="1"
                          class="w-16 rounded-lg border-2 border-emerald-500 bg-transparent px-2 py-1 text-center font-bold text-white outline-none focus:ring-2 focus:ring-emerald-400/30"
                        />
                      </td>
                      <td class="px-3 py-2 text-center">
                        <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                          {{ fightScore(field, 'B') }}
                        </span>
                      </td>
                    </tr>

                    <tr class="border-t-4 border-red-600 bg-[#242424] text-sm font-bold">
                      <td colspan="2" class="px-3 py-3 text-center text-yellow-100">{{ fightSubtotal('A') }}</td>
                      <td colspan="2" class="px-3 py-3 text-center text-white">Subtotal</td>
                      <td colspan="2" class="px-3 py-3 text-center text-yellow-100">{{ fightSubtotal('B') }}</td>
                    </tr>

                    <tr
                      v-for="field in fightCriterionFields.filter((item) => item.es_penalizacion)"
                      :key="`judge-fight-penalty-${field.key}`"
                      class="border-t border-white/5"
                    >
                      <td class="px-3 py-2 text-center">
                        <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                          {{ fightScore(field, 'A') }}
                        </span>
                      </td>
                      <td class="px-3 py-2 text-center">
                        <input
                          v-model="currentFieldValues[fightFieldKey(field, 'A')]"
                          type="number"
                          min="0"
                          step="1"
                          class="w-16 rounded-lg border-2 border-emerald-500 bg-transparent px-2 py-1 text-center font-bold text-white outline-none focus:ring-2 focus:ring-emerald-400/30"
                        />
                      </td>
                      <td class="px-3 py-2 text-center">
                        <p class="font-semibold text-red-400">{{ field.label }}</p>
                        <p class="text-xs text-red-300">Resta al subtotal</p>
                      </td>
                      <td class="px-3 py-2 text-center text-slate-200">x {{ Number(field.valor_unitario || 0) }}</td>
                      <td class="px-3 py-2 text-center">
                        <input
                          v-model="currentFieldValues[fightFieldKey(field, 'B')]"
                          type="number"
                          min="0"
                          step="1"
                          class="w-16 rounded-lg border-2 border-emerald-500 bg-transparent px-2 py-1 text-center font-bold text-white outline-none focus:ring-2 focus:ring-emerald-400/30"
                        />
                      </td>
                      <td class="px-3 py-2 text-center">
                        <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                          {{ fightScore(field, 'B') }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr class="border-t-4 border-red-600 bg-[#242424] text-base font-bold">
                      <td colspan="2" class="px-3 py-4 text-center text-yellow-100">{{ fightTotal('A') }}</td>
                      <td colspan="2" class="px-3 py-4 text-center uppercase tracking-wide text-white">Resultado final</td>
                      <td colspan="2" class="px-3 py-4 text-center text-yellow-100">{{ fightTotal('B') }}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>

            <div
              v-if="isGolesTemplate"
              class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-950 text-white shadow-sm"
            >
              <div class="h-1.5 bg-blue-600"></div>

              <div class="px-5 py-5 sm:px-6">
                <div class="mb-5 flex items-center justify-between gap-3 text-sm">
                  <span class="text-slate-300">Marcador del enfrentamiento</span>
                  <span class="rounded-full bg-slate-800 px-3 py-1 font-semibold text-slate-100">
                    En registro
                  </span>
                </div>

                <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 sm:gap-6">
                  <div class="min-w-0 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-800 text-lg font-black text-blue-200 ring-1 ring-slate-700">
                      A
                    </div>
                    <p class="mt-3 truncate text-base font-semibold text-white">
                      {{ scoreboardTeamA?.equipo_nombre || "Equipo A" }}
                    </p>
                    <p class="mt-1 truncate text-xs text-slate-400">
                      {{ scoreboardTeamA?.institucion || "Sin institucion" }}
                    </p>
                  </div>

                  <div class="flex items-center justify-center gap-2 sm:gap-4">
                    <input
                      v-model="currentFieldValues[scoreFieldForSide('A')]"
                      type="number"
                      min="0"
                      step="1"
                      class="h-16 w-20 rounded-2xl border border-slate-700 bg-slate-900 text-center text-4xl font-bold text-white outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-400/30 sm:h-20 sm:w-24 sm:text-5xl"
                    />
                    <span class="text-3xl font-bold text-slate-400 sm:text-5xl">-</span>
                    <input
                      v-model="currentFieldValues[scoreFieldForSide('B')]"
                      type="number"
                      min="0"
                      step="1"
                      :disabled="!scoreboardTeamB"
                      class="h-16 w-20 rounded-2xl border border-slate-700 bg-slate-900 text-center text-4xl font-bold text-white outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-400/30 disabled:opacity-40 sm:h-20 sm:w-24 sm:text-5xl"
                    />
                  </div>

                  <div class="min-w-0 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-800 text-lg font-black text-rose-200 ring-1 ring-slate-700">
                      B
                    </div>
                    <p class="mt-3 truncate text-base font-semibold text-white">
                      {{ scoreboardTeamB?.equipo_nombre || "Equipo B" }}
                    </p>
                    <p class="mt-1 truncate text-xs text-slate-400">
                      {{ scoreboardTeamB?.institucion || "Sin institucion" }}
                    </p>
                  </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                  <p v-if="getFieldError('goles_favor')" class="rounded-xl bg-red-500/10 px-3 py-2 text-xs text-red-200">
                    {{ getFieldError("goles_favor") }}
                  </p>
                  <p v-if="getFieldError('goles_contra')" class="rounded-xl bg-red-500/10 px-3 py-2 text-xs text-red-200">
                    {{ getFieldError("goles_contra") }}
                  </p>
                </div>
              </div>
            </div>

            <div
              v-if="isTiempoTemplate"
              class="overflow-hidden rounded-2xl border border-blue-500/30 bg-black text-green-400 shadow-sm"
            >
              <div class="h-1.5 bg-blue-600"></div>

              <div class="px-5 py-6 sm:px-6">
                <div class="mb-4 flex items-center justify-between gap-3 text-sm">
                  <span class="font-semibold text-slate-200">Cronometro digital</span>
                  <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-slate-200 ring-1 ring-white/15">
                    Tiempo final
                  </span>
                </div>

                <label class="block cursor-text">
                  <div class="chrono-display rounded-2xl border border-white/10 bg-[#101010] px-4 py-6 text-slate-100 sm:px-6">
                    <div class="flex items-end justify-center gap-4 sm:gap-8">
                      <div class="flex items-end gap-1">
                        <span class="chrono-number">{{ timeDisplayParts.hours }}</span>
                        <span class="chrono-unit">h</span>
                      </div>
                      <div class="flex items-end gap-1">
                        <span class="chrono-number">{{ timeDisplayParts.minutes }}</span>
                        <span class="chrono-unit">m</span>
                      </div>
                      <div class="flex items-end gap-1">
                        <span class="chrono-number">{{ timeDisplayParts.seconds }}</span>
                        <span class="chrono-unit">s</span>
                      </div>
                    </div>
                  </div>

                  <input
                    :value="timeInputDigits"
                    type="tel"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="6"
                    class="mt-3 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-center text-sm font-semibold text-slate-100 outline-none transition placeholder:text-slate-500 focus:border-slate-400 focus:ring-2 focus:ring-slate-400/20"
                    placeholder="Digite solo numeros. Ej: 735 = 00h 07m 35s"
                    @beforeinput="blockNonNumericInput"
                    @input="onTimeDigitsInput"
                    @paste="onTimeDigitsPaste"
                  />
                </label>

                <div class="mt-4 grid grid-cols-1 gap-3 text-xs text-slate-300 sm:grid-cols-2">
                  <div class="rounded-xl bg-white/5 px-3 py-2 ring-1 ring-white/10">
                    Se completa de derecha a izquierda: HH MM SS.
                  </div>
                  <div class="rounded-xl bg-white/5 px-3 py-2 ring-1 ring-white/10">
                    Ejemplo: 735 se guarda como 00:07:35.
                  </div>
                </div>

                <p v-if="getFieldError('tiempo')" class="mt-3 rounded-xl bg-red-500/10 px-3 py-2 text-xs text-red-200">
                  {{ getFieldError("tiempo") }}
                </p>
              </div>
            </div>

            <div
              v-if="standardFields.length"
              class="grid grid-cols-1 gap-4 sm:grid-cols-2"
            >
              <div
                v-for="field in standardFields"
                :key="field.key"
                :class="field.type === 'textarea' ? 'sm:col-span-2' : ''"
              >
                <label
                  v-if="!isBooleanField(field)"
                  class="mb-1 block text-sm font-semibold text-slate-800"
                >
                  {{ field.label }}
                  <span v-if="field.required" class="text-red-500">*</span>
                </label>

                <select
                  v-if="isSelectField(field)"
                  v-model="currentFieldValues[field.key]"
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

                <input
                  v-else-if="field.type !== 'textarea' && !isBooleanField(field)"
                  v-model="currentFieldValues[field.key]"
                  :type="field.type === 'number' ? 'number' : 'text'"
                  :step="field.type === 'number' ? '0.001' : undefined"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :placeholder="field.type === 'duration' ? 'Ej: 12.345 o 01:12.500' : `Ingresa ${field.label.toLowerCase()}`"
                />

                <label
                  v-else-if="isBooleanField(field)"
                  class="flex min-h-[44px] items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-800"
                >
                  <input
                    v-model="currentFieldValues[field.key]"
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                  />
                  <span>
                    {{ field.label }}
                    <span v-if="field.required" class="text-red-500">*</span>
                  </span>
                </label>

                <textarea
                  v-else
                  v-model="currentFieldValues[field.key]"
                  rows="3"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :placeholder="`Ingresa ${field.label.toLowerCase()}`"
                />

                <p v-if="getFieldError(field.key)" class="mt-1 text-xs text-red-600">
                  {{ getFieldError(field.key) }}
                </p>
              </div>
            </div>

            <div v-if="isRemoteMode && !remoteFormDefinition && remoteSelectedTeamId" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-500">
              Genera el sorteo de la ronda para habilitar el formulario de evaluación.
            </div>

            <div
              v-if="!isRemoteMode || !hasRemoteObservacionesField"
              class="space-y-1"
            >
              <label class="block text-sm font-semibold text-slate-800">
                Observaciones
              </label>
              <textarea
                v-if="isRemoteMode"
                v-model="remoteForm.observaciones"
                rows="3"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Comentarios adicionales sobre la evaluación"
              />
              <textarea
                v-else
                v-model="mockForm.observaciones"
                rows="3"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Comentarios adicionales sobre la evaluación"
              />
            </div>

            <div v-if="isRemoteMode && currentVersion > 0" class="space-y-1">
              <label class="block text-sm font-semibold text-slate-800">
                Motivo del cambio
              </label>
              <input
                v-model="remoteForm.motivo_cambio"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Describe brevemente por qué estás actualizando esta evaluación"
              />
              <p v-if="getTopLevelError('motivo_cambio')" class="mt-1 text-xs text-red-600">
                {{ getTopLevelError("motivo_cambio") }}
              </p>
            </div>

            <div class="flex flex-col gap-3 pt-2 sm:flex-row">
              <button
                type="button"
                @click="registrarResultado"
                :disabled="remoteSaving || (isRemoteMode && (!remoteSelectedTeamId || !hasCurrentSorteo))"
                class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <CheckCircleIcon class="h-5 w-5" />
                {{ remoteSaving ? "Guardando..." : "Guardar evaluación" }}
              </button>

              <button
                type="button"
                @click="limpiarRegistro"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 transition hover:bg-slate-50"
              >
                <XMarkIcon class="h-5 w-5 text-slate-700" />
                Limpiar
              </button>
            </div>

            <div
              v-if="isRemoteMode && !canEditRemoteResult"
              class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
            >
              Esta categoría no permite edición del juez después del primer guardado.
            </div>
          </div>
        </div>

        <div class="space-y-6">
          <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5">
            <div class="mb-4 flex items-center gap-2">
              <InformationCircleIcon class="h-5 w-5 text-slate-700" />
              <h3 class="text-lg font-semibold text-slate-900">Resumen</h3>
            </div>

            <div class="space-y-3 text-sm text-slate-700">
              <div class="flex items-center justify-between gap-4">
                <span>Equipo actual</span>
                <span class="font-semibold text-slate-900 text-right">{{ selectedTeamLabel }}</span>
              </div>

              <div class="flex items-center justify-between gap-4">
                <span>Mecanismo</span>
                <span class="font-semibold text-slate-900 text-right">{{ currentMechanismLabel }}</span>
              </div>

              <div class="flex items-center justify-between gap-4">
                <span>Unidad</span>
                <span class="font-semibold text-slate-900 text-right">{{ currentUnitLabel }}</span>
              </div>

              <div v-if="isRemoteMode" class="flex items-center justify-between gap-4">
                <span>Equipos en esta ronda</span>
                <span class="font-semibold text-slate-900">{{ remoteTeams.length }}</span>
              </div>

              <div v-if="!isRemoteMode" class="flex items-center justify-between gap-4">
                <span>Subcategorías</span>
                <span class="font-semibold text-slate-900">{{ mockAvailableSubcats.length }}</span>
              </div>
            </div>
          </div>

          <div class="rounded-2xl bg-slate-900 p-5 text-white shadow-sm">
            <div class="flex items-center justify-between gap-4">
              <div>
                <p class="text-sm font-semibold">Vista previa</p>
                <p class="text-xs text-slate-300">
                  {{ isRemoteMode ? "Basado en el mecanismo configurado" : "Basado en el formulario demo" }}
                </p>
              </div>

              <ClockIcon class="h-6 w-6 text-slate-300" />
            </div>

            <div class="mt-4">
              <p class="text-3xl font-bold leading-none">{{ scorePreview }}</p>
              <p class="mt-2 text-xs uppercase tracking-wide text-slate-300">{{ currentUnitLabel }}</p>
            </div>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5">
            <div class="mb-4 flex items-center gap-2">
              <TrophyIcon class="h-5 w-5 text-blue-600" />
              <h3 class="text-lg font-semibold text-slate-900">Guía rápida</h3>
            </div>

            <ul class="space-y-2 text-sm text-slate-700">
              <li>- Verifica categoría, ronda y equipo antes de guardar.</li>
              <li>- Respeta el mecanismo configurado por categoría.</li>
              <li v-if="isRemoteMode">- Si otra sesión modifica el registro, el sistema te pedirá recargar.</li>
              <li v-if="isRemoteMode">- Cada guardado incrementa la versión y deja historial.</li>
              <li v-else>- Este modo es solo demostrativo y no persiste datos reales.</li>
            </ul>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.chrono-display {
  box-shadow: inset 0 0 28px rgba(255, 255, 255, 0.03);
}

.chrono-number {
  font-family: Arial, Helvetica, sans-serif;
  font-size: clamp(4rem, 14vw, 8.5rem);
  font-weight: 800;
  line-height: 0.9;
  letter-spacing: 0;
  color: #f2f2f2;
}

.chrono-unit {
  margin-bottom: 0.1em;
  font-family: Arial, Helvetica, sans-serif;
  font-size: clamp(1.8rem, 5vw, 3.2rem);
  font-weight: 800;
  line-height: 1;
  color: #f2f2f2;
}
</style>
