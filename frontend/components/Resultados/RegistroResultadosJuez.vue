<script setup>
import axios from "axios";
import { usePage } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";

import {
  EyeIcon,
  CheckCircleIcon,
  ClockIcon,
  XMarkIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ClipboardDocumentListIcon,
  Squares2X2Icon,
  SparklesIcon,
  TrophyIcon,
  ExclamationTriangleIcon,
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
const page = usePage();
const catScroller = ref(null);

const mockSelectedCompetition = ref(
  props.initialCompetitionId || props.competitions?.[0]?.id || ""
);
const mockSelectedCategoryId = ref(props.categories?.[0]?.id ?? null);

const remoteLoading = ref(false);
const remoteSaving = ref(false);
const remoteEndingMatch = ref(false);
const remoteDrawGenerating = ref(false);
const remoteExcludingParticipant = ref(false);
const remoteNotice = ref({ type: "", message: "" });
const toast = ref({ show: false, type: "info", message: "" });
let toastTimer = null;
let remoteSyncTimer = null;
let remoteLockHeartbeatTimer = null;
const remoteContext = ref(null);
const remoteFormDefinition = ref(null);
const remoteErrors = ref({});
const remoteCategoryLock = ref({ activo: false, bloqueado: false });
const remoteSelectedCategoryId = ref(null);
const remoteSelectedRondaId = ref(null);
const remoteSelectedTeamId = ref("");
const remoteSelectedAttemptNumber = ref(1);
const timeInputDigits = ref("");
const invalidTimeModalOpen = ref(false);
const invalidTimeReason = ref("No completa el circuito");
const invalidTimeOtherReason = ref("");
const nextAttemptModalOpen = ref(false);
const completedAttemptNumber = ref(1);
const nextAttemptNumber = ref(2);
const regenerateDrawModalOpen = ref(false);
let regenerateDrawModalResolver = null;
const excludeModalOpen = ref(false);
const excludeModalItem = ref(null);
const excludeModalProcessing = ref(false);
const roundCompletionModal = ref({
  show: false,
  isFinal: false,
  title: "",
  message: "",
});

const invalidTimeReasons = [
  "No participa",
  "No completa el circuito",
  "Abandona",
  "No genera tiempo",
  "Intento inválido",
  "Otro",
];

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
  motivo_cambio_opcion: "",
  motivo_cambio_otro: "",
  version: 0,
  payload: {},
});

const changeReasonOptions = [
  "Error de digitación en el resultado",
  "Corrección por reclamo aprobado",
  "Otro",
];

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
const remoteParticipants = computed(() => remoteContext.value?.participantes_sorteo ?? []);
const remoteParticipantsMap = computed(() => {
  const items = remoteParticipants.value ?? [];
  return new Map(items.map((item) => [String(item.inscripcion_id), item]));
});

const currentSorteo = computed(() => remoteContext.value?.sorteo ?? null);

const currentRemoteRound = computed(() => {
  return remoteRounds.value.find(
    (round) => Number(round.id) === Number(remoteSelectedRondaId.value)
  ) ?? remoteFormDefinition.value?.ronda ?? null;
});

const hasCurrentSorteo = computed(() => !isRemoteMode.value || !!currentSorteo.value);

const pendingRemoteTeams = computed(() => {
  if (!isRemoteMode.value) return [];

  const isFightDraw = currentSorteo.value?.tipo_sorteo === "enfrentamiento";

  return remoteTeams.value.filter((team) => isRemoteTeamEvaluable(team, isFightDraw));
});

const currentPendingRemoteTeam = computed(() => pendingRemoteTeams.value[0] ?? null);

const currentRemoteTeam = computed(() => {
  if (!isRemoteMode.value) return null;

  return remoteTeams.value.find(
    (team) => String(team.equipo_id) === String(remoteSelectedTeamId.value)
      && Number(team.intento_numero ?? 1) === Number(remoteSelectedAttemptNumber.value || 1)
  ) ?? null;
});

const currentRemoteTurnLabel = computed(() => {
  if (!currentRemoteTeam.value) return "";

  if (isEnfrentamientoSorteo.value) {
    return `Combate #${currentRemoteTeam.value.sorteo_grupo || currentRemoteTeam.value.sorteo_orden || "actual"}`;
  }

  const orden = currentRemoteTeam.value.sorteo_orden ? `#${currentRemoteTeam.value.sorteo_orden}` : "actual";
  const intento = currentRemoteTeam.value.intento_label || `Intento ${currentRemoteTeam.value.intento_numero || 1}`;

  return `Participante ${orden} - ${intento}`;
});

const completedRemoteTeamsCount = computed(() => {
  if (!isRemoteMode.value) return 0;

  return remoteTeams.value.filter((team) => remoteTeamEvaluationComplete(team)).length;
});

const currentRoundHasRemoteResults = computed(() => {
  return isRemoteMode.value && remoteTeams.value.some((team) => !!team.resultado_id);
});

const isRemoteMultiJudge = computed(() => {
  return remoteFormDefinition.value?.config_calificacion?.esquema_jueces === "evaluacion_multi_juez"
    || selectedCategory.value?.config_calificacion?.esquema_jueces === "evaluacion_multi_juez";
});

const remoteCategoryLockedByOther = computed(() => {
  return isRemoteMode.value && Boolean(remoteCategoryLock.value?.bloqueado);
});

const remoteCategoryLockMessage = computed(() => {
  if (!remoteCategoryLockedByOther.value) return "";

  if (remoteCategoryLock.value?.message) {
    return remoteCategoryLock.value.message;
  }

  const judgeName = remoteCategoryLock.value?.juez_nombre || "otro juez";
  return `Esta categoría está siendo registrada por ${judgeName}.`;
});

const canUseRemoteRegistration = computed(() => {
  return !isRemoteMode.value || !remoteCategoryLockedByOther.value;
});

function remoteTeamEvaluationComplete(team) {
  if (!team) return false;
  if (team.evaluacion_completa !== undefined) return Boolean(team.evaluacion_completa);

  return !!team.resultado_id;
}

function isRemoteTeamEvaluable(team, isFightDraw = false) {
  if (!team) return false;

  if (team.sorteo_estado === "completado" || team.sorteo_estado === "directo") {
    return false;
  }

  return isFightDraw || !remoteTeamEvaluationComplete(team);
}

function currentSaveWillCompleteEvaluation() {
  const team = currentRemoteTeam.value;
  if (!team) return false;
  if (remoteTeamEvaluationComplete(team)) return true;

  const required = Math.max(1, Number(team.evaluaciones_requeridas ?? 1));
  const registered = Math.max(0, Number(team.evaluaciones_registradas ?? (team.resultado_id ? 1 : 0)));
  const currentJudgeAlreadyRegistered = Boolean(team.resultado_id);

  return registered + (currentJudgeAlreadyRegistered ? 0 : 1) >= required;
}

function remoteTeamKey(item) {
  return `${String(item?.equipo_id ?? "")}:${Number(item?.intento_numero ?? 1)}`;
}

function findRemoteTeamByKey(item) {
  if (!item?.equipo_id) return null;

  return remoteTeams.value.find(
    (team) => String(team.equipo_id) === String(item.equipo_id)
      && Number(team.intento_numero ?? 1) === Number(item.intento_numero ?? 1)
  ) ?? null;
}

function queueItemForSelection(item) {
  return findRemoteTeamByKey(item) ?? item;
}

function isCurrentQueueTurn(item) {
  const queueItem = queueItemForSelection(item);

  if (!queueItem?.equipo_id || !currentPendingRemoteTeam.value) return false;

  return remoteTeamKey(queueItem) === remoteTeamKey(currentPendingRemoteTeam.value);
}

function isSelectableQueueItem(item) {
  const queueItem = queueItemForSelection(item);

  return remoteTeamEvaluationComplete(queueItem) || isCurrentQueueTurn(queueItem);
}

function isSelectableMatchGroup(group) {
  return (group?.items ?? []).some((item) => isSelectableQueueItem(item));
}

function queueItemStatusLabel(item) {
  if (isSelectableQueueItem(item)) return "";
  return "Debes registrar el participante actual según el orden del sorteo antes de avanzar.";
}

const canGenerateRemoteDraw = computed(() => {
  return isRemoteMode.value
    && !!remoteSelectedRondaId.value
    && canUseRemoteRegistration.value
    && (!currentSorteo.value || !currentRoundHasRemoteResults.value);
});

const remoteDrawButtonLabel = computed(() => {
  if (remoteDrawGenerating.value) return "Generando...";
  if (!currentSorteo.value) return "Generar sorteo";
  if (currentRoundHasRemoteResults.value) return "Sorteo generado";
  return "Generar nuevamente";
});

const canFinishCurrentMatch = computed(() => {
  return isRemoteMode.value
    && canUseRemoteRegistration.value
    && isEnfrentamientoSorteo.value
    && !!remoteSelectedTeamId.value
    && currentVersion.value > 0
    && !currentMatchHasUnsavedChanges.value
    && currentRemoteTeam.value?.sorteo_estado !== "completado";
});

const currentMatchHasUnsavedChanges = computed(() => {
  if (!isRemoteMode.value || !isEnfrentamientoSorteo.value || currentVersion.value <= 0) {
    return false;
  }

  return comparablePayload(normalizeRemotePayload()) !== comparablePayload(remoteFormDefinition.value?.resultado_actual?.payload ?? {});
});

const currentRemoteHasUnsavedChanges = computed(() => {
  if (!isRemoteMode.value || currentVersion.value <= 0 || !remoteFormDefinition.value?.resultado_actual) {
    return false;
  }

  return comparablePayload(normalizeRemotePayload()) !== comparablePayload(remoteFormDefinition.value?.resultado_actual?.payload ?? {});
});

const shouldShowFinishCurrentMatch = computed(() => {
  return isRemoteMode.value
    && isEnfrentamientoSorteo.value
    && !!remoteSelectedTeamId.value
    && currentRemoteTeam.value?.sorteo_estado !== "completado";
});

const finishCurrentMatchHint = computed(() => {
  if (!shouldShowFinishCurrentMatch.value || canFinishCurrentMatch.value) {
    return "";
  }

  return "Guarda el marcador antes de terminar el encuentro.";
});

const currentSorteoLabel = computed(() => {
  if (!currentSorteo.value) return "Sorteo pendiente";
  return currentSorteo.value.tipo_sorteo === "enfrentamiento"
    ? "Llaves de enfrentamiento"
    : "Orden de participación";
});

function normalizeTextForCompare(value) {
  return String(value ?? "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toUpperCase()
    .replace(/\s+/g, " ")
    .trim();
}

function cleanParticipantName(value) {
  const raw = String(value ?? "").trim();
  if (!raw) return "";

  const categoryName = selectedCategory.value?.categoria?.nombre ?? selectedCategory.value?.nombre ?? "";
  const normalizedCategory = normalizeTextForCompare(categoryName);

  if (!normalizedCategory) {
    return raw;
  }

  const pieces = raw
    .split(/\s+-\s+/)
    .map((piece) => piece.trim())
    .filter(Boolean);

  const withoutCategoryPieces = pieces.filter((piece) => normalizeTextForCompare(piece) !== normalizedCategory);

  if (withoutCategoryPieces.length && withoutCategoryPieces.length !== pieces.length) {
    return withoutCategoryPieces.join(" - ");
  }

  const normalizedRaw = normalizeTextForCompare(raw);
  if (normalizedRaw.endsWith(` - ${normalizedCategory}`)) {
    return raw.slice(0, raw.length - categoryName.length).replace(/\s+-\s*$/, "").trim();
  }

  return raw;
}

function participantName(item, fallback = "Sin participante") {
  return cleanParticipantName(item?.nombre_prototipo || item?.equipo_nombre) || fallback;
}

const sorteoGroups = computed(() => {
  const detalles = currentSorteo.value?.detalles ?? [];

  if (currentSorteo.value?.tipo_sorteo !== "enfrentamiento") {
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
  const detalles = currentSorteo.value?.detalles ?? [];

  if (currentSorteo.value?.tipo_sorteo !== "enfrentamiento") {
    return [];
  }

  return detalles
    .filter((detalle) => detalle.estado === "directo")
    .sort((a, b) => Number(a.orden ?? 0) - Number(b.orden ?? 0));
});

const sorteoOrdenItems = computed(() => {
  if (!isRemoteMode.value) return [];

  const detalles = currentSorteo.value?.detalles ?? [];

  if (detalles.length && currentSorteo.value?.tipo_sorteo === "enfrentamiento") {
    return [...detalles]
      .sort((a, b) => Number(a.orden ?? 0) - Number(b.orden ?? 0))
      .map((detalle) => ({
        orden: detalle.orden,
        equipo_id: detalle.equipo_id,
        intento_numero: 1,
        intento_label: "Intento 1",
        participante: participantName(detalle),
        institucion: detalle.institucion || "Sin institución",
      }));
  }

  return [...remoteTeams.value]
    .sort((a, b) => Number(a.flujo_orden ?? a.sorteo_orden ?? a.inscripcion_id ?? 0) - Number(b.flujo_orden ?? b.sorteo_orden ?? b.inscripcion_id ?? 0))
    .map((team, index) => ({
      orden: team.sorteo_orden ?? index + 1,
      equipo_id: team.equipo_id,
      intento_numero: team.intento_numero ?? 1,
      intento_label: team.intento_label ?? `Intento ${team.intento_numero ?? 1}`,
      participante: participantName(team),
      institucion: team.institucion || "Sin institución",
      estado_participacion: remoteParticipantsMap.value.get(String(team.inscripcion_id))?.estado_participacion ?? "incluido",
      resultado_id: team.resultado_id ?? null,
      resultado_estado: team.resultado_estado ?? null,
      resultado_juez_nombre: team.resultado_juez_nombre ?? "",
      resultado_registrado_por_otro_juez: Boolean(team.resultado_registrado_por_otro_juez),
      evaluacion_completa: remoteTeamEvaluationComplete(team),
      evaluaciones_pendientes: Number(team.evaluaciones_pendientes ?? 0),
      is_current_turn: isCurrentQueueTurn(team),
      is_selectable: isSelectableQueueItem(team),
    }));
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
  const template = remoteFormDefinition.value?.config_calificacion?.plantilla_resultado;

  return isRemoteMode.value
    && (["tabla_individual_criterios", "tabla_individual_puntaje_maximo", "tabla_enfrentamiento_criterios"].includes(template)
      || ["tabla_evaluacion", "puntaje_jueces"].includes(remoteFormDefinition.value?.config_calificacion?.mecanismo_codigo));
});

const isTablaEnfrentamientoTemplate = computed(() => {
  return isRemoteMode.value
    && remoteFormDefinition.value?.config_calificacion?.plantilla_resultado === "tabla_enfrentamiento_criterios";
});

const isTablaIndividualTemplate = computed(() => {
  return isRemoteMode.value
    && remoteFormDefinition.value?.config_calificacion?.plantilla_resultado === "tabla_individual_criterios";
});

const isTablaPuntajeMaximoTemplate = computed(() => {
  return isRemoteMode.value
    && remoteFormDefinition.value?.config_calificacion?.plantilla_resultado === "tabla_individual_puntaje_maximo";
});

const rubricFields = computed(() => {
  if (!isRubricFormat.value || isTablaEnfrentamientoTemplate.value || isTablaIndividualTemplate.value || isTablaPuntajeMaximoTemplate.value) return [];

  return currentFields.value.filter((field) => field.type === "number" && field.key !== "penalizaciones");
});

const fightCriterionFields = computed(() => {
  if (!isTablaEnfrentamientoTemplate.value) return [];

  return currentFields.value.filter((field) => field.type === "number" && field.key !== "penalizaciones");
});

const individualCriterionFields = computed(() => {
  if (!isTablaIndividualTemplate.value && !isTablaPuntajeMaximoTemplate.value) return [];

  return currentFields.value.filter((field) => field.type === "number" && field.key !== "penalizaciones");
});

const standardFields = computed(() => {
  if (isTablaEnfrentamientoTemplate.value || isTablaIndividualTemplate.value || isTablaPuntajeMaximoTemplate.value) {
    return currentFields.value.filter((field) => field.key === "observaciones" || field.type !== "number");
  }

  const fields = isRubricFormat.value
    ? currentFields.value.filter((field) => !rubricFields.value.some((rubric) => rubric.key === field.key))
    : currentFields.value;

  if (isMarcadorTemplate.value) {
    return fields.filter((field) => !["marcador_equipo_a", "marcador_equipo_b"].includes(field.key));
  }

  if (isTiempoTemplate.value) {
    return fields.filter((field) => !["tiempo", "penalizaciones", "valor_principal", "resultado"].includes(field.key));
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

const isMarcadorTemplate = computed(() => {
  return isRemoteMode.value
    && remoteFormDefinition.value?.config_calificacion?.plantilla_resultado === "marcador";
});

const isTiempoTemplate = computed(() => {
  return isRemoteMode.value
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
  const normalizedNumber = raw.replace(",", ".");

  if (/^\d+(?:\.\d+)?$/.test(normalizedNumber)) {
    const seconds = Number(normalizedNumber);
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

function durationValueToSeconds(value) {
  if (value === null || value === undefined || value === "") return null;

  const raw = String(value).trim().replace(",", ".");

  if (/^\d+(?:\.\d+)?$/.test(raw)) {
    return Number(raw);
  }

  const parts = raw.split(":").map((part) => Number(part));

  if (parts.length === 3 && parts.every(Number.isFinite)) {
    return (parts[0] * 3600) + (parts[1] * 60) + parts[2];
  }

  if (parts.length === 2 && parts.every(Number.isFinite)) {
    return (parts[0] * 60) + parts[1];
  }

  return null;
}

function formatDurationValue(value) {
  const secondsValue = durationValueToSeconds(value);

  if (secondsValue === null) return "00:00:00";

  const totalSeconds = Math.max(0, Math.floor(secondsValue));
  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;

  return `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
}

function onTimeDigitsInput(event) {
  const digits = String(event.target.value || "").replace(/\D/g, "").slice(-6).replace(/^0+(?=\d)/, "");
  event.target.value = digits;
  timeInputDigits.value = digits;
  currentFieldValues.value.tiempo = digits ? digitsToDuration(digits) : "";
  currentFieldValues.value.no_participa = false;
  currentFieldValues.value.sin_tiempo_valido = false;
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
  currentFieldValues.value.no_participa = false;
  currentFieldValues.value.sin_tiempo_valido = false;
}

function durationDisplayParts(value) {
  const padded = durationDigitsFromValue(value).replace(/\D/g, "").slice(-6).padStart(6, "0");

  return {
    hours: padded.slice(0, 2),
    minutes: padded.slice(2, 4),
    seconds: padded.slice(4, 6),
  };
}

function onDurationDigitsInput(event, key) {
  const digits = String(event.target.value || "").replace(/\D/g, "").slice(-6).replace(/^0+(?=\d)/, "");
  event.target.value = digits;
  currentFieldValues.value[key] = digits ? digitsToDuration(digits) : "";
}

function onDurationDigitsPaste(event, key) {
  event.preventDefault();
  const text = event.clipboardData?.getData("text") ?? "";
  const digits = text.replace(/\D/g, "").slice(-6).replace(/^0+(?=\d)/, "");
  currentFieldValues.value[key] = digits ? digitsToDuration(digits) : "";
}

const currentMatchItems = computed(() => {
  if (!isRemoteMode.value || currentSorteo.value?.tipo_sorteo !== "enfrentamiento" || !currentRemoteTeam.value?.sorteo_grupo) return [];

  const group = sorteoGroups.value.find(
    (item) => Number(item.grupo) === Number(currentRemoteTeam.value.sorteo_grupo)
  );

  return (group?.items ?? []).slice().sort((left, right) => {
    const order = { A: 1, B: 2 };
    return (order[left.lado] ?? 9) - (order[right.lado] ?? 9);
  });
});

const isEnfrentamientoSorteo = computed(() => currentSorteo.value?.tipo_sorteo === "enfrentamiento");

function isCurrentMatchGroup(group) {
  if (!isEnfrentamientoSorteo.value || !currentRemoteTeam.value) return false;

  return Number(group?.grupo) === Number(currentRemoteTeam.value.sorteo_grupo);
}

const scoreboardTeamA = computed(() => {
  return currentMatchItems.value.find((item) => item.lado === "A") ?? currentMatchItems.value[0] ?? currentRemoteTeam.value;
});

const scoreboardTeamB = computed(() => {
  return currentMatchItems.value.find((item) => item.lado === "B") ?? null;
});

function scoreFieldForSide(side) {
  return side === "B" ? "marcador_equipo_b" : "marcador_equipo_a";
}

function onIntegerFieldInput(event, key) {
  const value = String(event.target.value || "").replace(/\D/g, "");
  event.target.value = value;
  currentFieldValues.value[key] = value;
}

function onCriterionScoreInput(event, field) {
  const integerPart = String(event.target.value || "").replace(",", ".").split(".")[0] ?? "";
  const cleaned = integerPart.replace(/\D/g, "");
  const max = Number(field?.valor_unitario ?? 0);
  const value = cleaned === "" ? "" : Math.max(0, Math.min(parseInt(cleaned, 10), max));

  event.target.value = value === "" ? "" : String(value);
  currentFieldValues.value[field.key] = value;
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
  if (isTablaPuntajeMaximoTemplate.value) {
    return Number(currentFieldValues.value[field.key] || 0);
  }

  const raw = individualQuantity(field) * Number(field?.valor_unitario || 0);
  return field?.es_penalizacion ? -raw : raw;
}

function individualSubtotal() {
  return individualCriterionFields.value
    .filter((field) => !field.es_penalizacion)
    .reduce((sum, field) => sum + individualScore(field), 0);
}

function individualPenaltyTotal() {
  if (isTablaPuntajeMaximoTemplate.value) return 0;

  return individualCriterionFields.value
    .filter((field) => field.es_penalizacion)
    .reduce((sum, field) => sum + Math.abs(individualScore(field)), 0);
}

function individualTotal() {
  if (isTablaPuntajeMaximoTemplate.value) {
    return individualSubtotal();
  }

  return individualSubtotal() - individualPenaltyTotal();
}

const currentVersion = computed(() => {
  return isRemoteMode.value ? Number(remoteForm.version || 0) : 0;
});

const currentResultState = computed(() => {
  return remoteFormDefinition.value?.resultado_actual?.estado ?? null;
});

const currentResultJudgeName = computed(() => {
  return remoteFormDefinition.value?.resultado_actual?.juez_nombre || "";
});

const authenticatedJudgeId = computed(() => {
  return page.props.juez?.id ?? page.props.auth?.user?.id ?? null;
});

const currentResultRegisteredByOtherJudge = computed(() => {
  const ownerId = remoteFormDefinition.value?.resultado_actual?.juez_user_id;
  const currentJudgeId = authenticatedJudgeId.value;

  return Boolean(ownerId && currentJudgeId && Number(ownerId) !== Number(currentJudgeId));
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

    if (template === "marcador") {
      return `${Number(values.marcador_equipo_a || 0)} - ${Number(values.marcador_equipo_b || 0)}`;
    }

    if (template === "tiempo") {
      if (values.sin_tiempo_valido || values.no_participa) {
        return "Sin tiempo válido";
      }

      const time = durationValueToSeconds(values.tiempo) ?? 0;
      const penalty = Number(values.penalizaciones || 0);
      return formatDurationValue(time + penalty);
    }

    if (template === "tabla_individual_criterios") {
      return individualTotal();
    }

    if (template === "tabla_individual_puntaje_maximo") {
      return individualTotal();
    }

    if (template === "tabla_enfrentamiento_criterios") {
      const side = currentRemoteTeam.value?.sorteo_lado === "B" ? "B" : "A";
      return fightTotal(side);
    }

    if (mechanism === "registro_resultado") {
      if (values.resultado) {
        const points = Number(values.puntos || 0);
        return points ? `${values.resultado} (${points})` : values.resultado;
      }

      const time = Number(values.tiempo || 0);
      const penalty = Number(values.penalizaciones || 0);
      return time + penalty || values.valor_principal || values.puntaje || values.tiempo || "0";
    }

    if (mechanism === "tabla_evaluacion") {
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
      return `${Number(values.marcador_equipo_a || 0)} - ${Number(values.marcador_equipo_b || 0)}`;
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

  if (message) {
    showToast(message, type || "info");
  }
}

function showToast(message, type = "info", ms = 4500) {
  toast.value = { show: true, type: type || "info", message };

  if (toastTimer) {
    clearTimeout(toastTimer);
  }

  toastTimer = setTimeout(() => {
    toast.value.show = false;
  }, ms);
}

function closeToast() {
  toast.value = { show: false, type: "info", message: "" };

  if (toastTimer) {
    clearTimeout(toastTimer);
    toastTimer = null;
  }
}

function shouldShowNextAttemptModalAfterSave() {
  if (!isRemoteMode.value || isEnfrentamientoSorteo.value) return false;

  const round = currentRemoteRound.value;
  const totalAttempts = Number(round?.cantidad_intentos ?? 1);
  const currentAttempt = Number(remoteSelectedAttemptNumber.value || 1);

  if (totalAttempts <= 1 || currentAttempt >= totalAttempts || round?.intentos_consecutivos) {
    return false;
  }

  if (!currentRemoteTeam.value || remoteTeamEvaluationComplete(currentRemoteTeam.value)) {
    return false;
  }

  if (!currentSaveWillCompleteEvaluation()) {
    return false;
  }

  const currentTeamId = String(remoteSelectedTeamId.value || "");

  return !remoteTeams.value.some((team) => {
    return Number(team.intento_numero ?? 1) === currentAttempt
      && String(team.equipo_id) !== currentTeamId
      && !remoteTeamEvaluationComplete(team)
      && team.sorteo_estado !== "completado";
  });
}

function openNextAttemptModal(attemptNumber) {
  completedAttemptNumber.value = Number(attemptNumber || 1);
  nextAttemptNumber.value = completedAttemptNumber.value + 1;
  nextAttemptModalOpen.value = true;
}

function continueNextAttempt() {
  nextAttemptModalOpen.value = false;
}

function openRoundCompletionModal(publication = {}) {
  const isFinal = Boolean(publication?.categoria_completa);

  roundCompletionModal.value = {
    show: true,
    isFinal,
    title: isFinal ? "Categoría finalizada" : "Ronda finalizada",
    message: isFinal
      ? "La ronda final terminó correctamente. El podio ya está listo: los tres primeros lugares se publicaron en la página de Resultados."
      : "Ronda finalizada, el sistema calculará automáticamente los clasificados y preparará la siguiente ronda.",
  };
}

function closeRoundCompletionModal() {
  roundCompletionModal.value = {
    show: false,
    isFinal: false,
    title: "",
    message: "",
  };
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
  remoteForm.motivo_cambio_opcion = "";
  remoteForm.motivo_cambio_otro = "";
  remoteForm.version = 0;
  remoteForm.payload = {};
  timeInputDigits.value = "";
  resetRemoteErrors();
}

function shouldDefaultCriterionToZero(field, options = {}) {
  return Boolean(options.defaultCriteriaToZero)
    && field?.type === "number"
    && field?.key !== "penalizaciones";
}

function fieldDefaultValue(field, options = {}) {
  if (["marcador_equipo_a", "marcador_equipo_b"].includes(field.key)) {
    return "0";
  }

  if (shouldDefaultCriterionToZero(field, options)) {
    return "0";
  }

  return isBooleanField(field) ? false : "";
}

function initializeFieldPayload(fields, source = {}, options = {}) {
  const payload = {};

  for (const field of fields) {
    const value = source?.[field.key];
    payload[field.key] = value ?? fieldDefaultValue(field, options);
  }

  return payload;
}

function comparablePayload(payload) {
  return JSON.stringify(
    Object.keys(payload ?? {})
      .sort()
      .reduce((acc, key) => {
        const value = payload[key];
        acc[key] = value === null || value === undefined || value === "" ? "" : String(value);
        return acc;
      }, {})
  );
}

function isBooleanField(field) {
  return ["checkbox", "boolean"].includes(field?.type);
}

function isSelectField(field) {
  return field?.type === "select";
}

function hydrateRemoteForm(definition) {
  remoteFormDefinition.value = definition;
  remoteSelectedAttemptNumber.value = Number(definition?.ronda?.intento_actual ?? remoteSelectedAttemptNumber.value ?? 1);
  remoteForm.version = definition?.resultado_actual?.version ?? 0;
  remoteForm.observaciones = definition?.resultado_actual?.observaciones ?? "";
  remoteForm.motivo_cambio = "";
  remoteForm.motivo_cambio_opcion = "";
  remoteForm.motivo_cambio_otro = "";
  remoteForm.payload = initializeFieldPayload(
    definition?.config_calificacion?.campos ?? [],
    definition?.resultado_actual?.payload ?? {},
    { defaultCriteriaToZero: isTablaEnfrentamientoTemplate.value || isTablaIndividualTemplate.value || isTablaPuntajeMaximoTemplate.value }
  );
  remoteForm.payload.sin_tiempo_valido = Boolean(
    definition?.resultado_actual?.payload?.sin_tiempo_valido
      ?? definition?.resultado_actual?.payload?.no_participa
      ?? false
  );
  remoteForm.payload.no_participa = false;

  if (isTablaEnfrentamientoTemplate.value) {
    for (const field of fightCriterionFields.value) {
      remoteForm.payload[fightFieldKey(field, "A")] = definition?.resultado_actual?.payload?.[fightFieldKey(field, "A")] ?? "0";
      remoteForm.payload[fightFieldKey(field, "B")] = definition?.resultado_actual?.payload?.[fightFieldKey(field, "B")] ?? "0";
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

function stopRemoteLockHeartbeat() {
  if (remoteLockHeartbeatTimer) {
    clearInterval(remoteLockHeartbeatTimer);
    remoteLockHeartbeatTimer = null;
  }
}

async function sendRemoteLockHeartbeat(silent = true) {
  if (!isRemoteMode.value || !remoteSelectedCategoryId.value || remoteCategoryLockedByOther.value) {
    stopRemoteLockHeartbeat();
    return;
  }

  try {
    const { data } = await axios.post("/juez/evaluaciones/bloqueo/heartbeat", {
      categoria_id: Number(remoteSelectedCategoryId.value),
    });

    remoteCategoryLock.value = data?.bloqueo_registro ?? { activo: false, bloqueado: false };

    if (remoteCategoryLockedByOther.value) {
      stopRemoteLockHeartbeat();
      clearRemoteForm();
      setNotice(remoteNotice, "warning", remoteCategoryLockMessage.value);
    }
  } catch (error) {
    if (!silent) {
      const message = error?.response?.data?.errors?.categoria_id?.[0]
        || error?.response?.data?.message
        || "No se pudo renovar el bloqueo de la categoría.";
      setNotice(remoteNotice, "warning", message);
    }

    if (error?.response?.status === 422) {
      stopRemoteLockHeartbeat();
      clearRemoteForm();
    }
  }
}

function startRemoteLockHeartbeat() {
  stopRemoteLockHeartbeat();

  if (!isRemoteMode.value || !remoteSelectedCategoryId.value || remoteCategoryLockedByOther.value) {
    return;
  }

  remoteLockHeartbeatTimer = setInterval(() => {
    sendRemoteLockHeartbeat(true);
  }, 20000);
}

async function releaseRemoteCategoryLock(categoryId = remoteSelectedCategoryId.value) {
  if (!isRemoteMode.value || !categoryId) return;

  try {
    await axios.post("/juez/evaluaciones/bloqueo/liberar", {
      categoria_id: Number(categoryId),
    });
  } catch (_) {
    // El timeout del backend libera el bloqueo si la pesta?a se cierra o la petici?n falla.
  }
}

async function loadRemoteContext(options = {}) {
  if (!options.silent) {
    remoteLoading.value = true;
    setNotice(remoteNotice, "", "");
  }

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
    const previousAttempt = options.preserveTeam ? Number(remoteSelectedAttemptNumber.value || 1) : 1;

    remoteContext.value = data;
    remoteCategoryLock.value = data?.bloqueo_registro ?? { activo: false, bloqueado: false };
    remoteSelectedCategoryId.value = data?.seleccion?.categoria_id ?? null;
    remoteSelectedRondaId.value = data?.seleccion?.ronda_id ?? null;
    startRemoteLockHeartbeat();

    if (remoteCategoryLockedByOther.value) {
      clearRemoteForm();
      setNotice(remoteNotice, "warning", remoteCategoryLockMessage.value);
      return;
    }

    const isFightDraw = data?.sorteo?.tipo_sorteo === "enfrentamiento";
    const nextPendingTeam = data?.equipos?.find((item) => isRemoteTeamEvaluable(item, isFightDraw));
    const stillPending = data?.equipos?.some(
      (item) => String(item.equipo_id) === previousTeamId
        && Number(item.intento_numero ?? 1) === previousAttempt
        && isRemoteTeamEvaluable(item, isFightDraw)
    );

    remoteSelectedTeamId.value = stillPending
      ? previousTeamId
      : String(nextPendingTeam?.equipo_id ?? "");
    remoteSelectedAttemptNumber.value = stillPending
      ? previousAttempt
      : Number(nextPendingTeam?.intento_numero ?? 1);

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
    if (options.silent) return;

    clearRemoteForm();
    remoteCategoryLock.value = { activo: false, bloqueado: false };
    remoteContext.value = null;
    setNotice(
      remoteNotice,
      "error",
      error?.response?.data?.message || "No se pudo cargar el contexto de evaluación del juez."
    );
  } finally {
    if (!options.silent) {
      remoteLoading.value = false;
    }
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
        intento_numero: Number(remoteSelectedAttemptNumber.value || 1),
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

async function selectRemoteTeamFromSummary(item) {
  if (!item?.equipo_id) return;

  const queueItem = queueItemForSelection(item);

  if (!isSelectableQueueItem(queueItem)) {
    showToast(queueItemStatusLabel(queueItem), "warning", 4500);
    return;
  }

  remoteSelectedTeamId.value = String(queueItem.equipo_id);
  remoteSelectedAttemptNumber.value = Number(queueItem.intento_numero || 1);
  await loadRemoteForm();
}

async function generarSorteoRemoto() {
  if (!canUseRemoteRegistration.value) {
    setNotice(remoteNotice, "warning", remoteCategoryLockMessage.value);
    return;
  }

  if (!remoteSelectedRondaId.value) {
    setNotice(remoteNotice, "error", "Selecciona una ronda antes de generar el sorteo.");
    return;
  }

  if (currentSorteo.value && currentRoundHasRemoteResults.value) {
    setNotice(remoteNotice, "info", "El sorteo de esta ronda ya está generado. Puedes continuar registrando los resultados.");
    return;
  }

  const regenerar = !!currentSorteo.value;

  if (regenerar && !(await confirmRegenerateDraw())) {
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

async function excluirParticipanteRemoto(item) {
  if (!isRemoteMode.value || !item?.inscripcion_id) return;

  if (!remoteSelectedRondaId.value) {
    showToast("Selecciona una ronda antes de excluir participantes.", "warning", 4500);
    return;
  }

  if (currentSorteo.value) {
    showToast("No puedes excluir participantes con un sorteo ya generado.", "warning", 4500);
    return;
  }

  excludeModalItem.value = item;
  excludeModalOpen.value = true;
}

function closeExcludeModal() {
  if (excludeModalProcessing.value) return;

  excludeModalOpen.value = false;
  excludeModalItem.value = null;
}

async function confirmExcludeParticipant() {
  const item = excludeModalItem.value;
  if (!item?.inscripcion_id || !remoteSelectedRondaId.value) return;

  excludeModalProcessing.value = true;
  setNotice(remoteNotice, "", "");

  try {
    const { data } = await axios.post("/juez/evaluaciones/sorteo/excluir", {
      ronda_id: Number(remoteSelectedRondaId.value),
      inscripcion_id: Number(item.inscripcion_id),
    });

    remoteContext.value = data?.contexto ?? remoteContext.value;
    await loadRemoteContext({
      categoriaId: remoteSelectedCategoryId.value,
      rondaId: remoteSelectedRondaId.value,
      preserveTeam: true,
      silent: true,
    });

    excludeModalOpen.value = false;
    excludeModalItem.value = null;
    setNotice(remoteNotice, "success", data?.message || "Participante excluido correctamente.");
  } catch (error) {
    setNotice(
      remoteNotice,
      "error",
      error?.response?.data?.message ||
        Object.values(error?.response?.data?.errors ?? {})?.[0]?.[0] ||
      "No se pudo excluir al participante."
    );
  } finally {
    excludeModalProcessing.value = false;
  }
}

function confirmRegenerateDraw() {
  regenerateDrawModalOpen.value = true;
  return new Promise((resolve) => {
    regenerateDrawModalResolver = resolve;
  });
}

function closeRegenerateDrawModal(confirmed) {
  regenerateDrawModalOpen.value = false;
  if (regenerateDrawModalResolver) {
    regenerateDrawModalResolver(confirmed);
    regenerateDrawModalResolver = null;
  }
}

async function pickCategory(categoryId) {
  const previousRemoteCategoryId = isRemoteMode.value ? remoteSelectedCategoryId.value : null;

  if (
    isRemoteMode.value
    && previousRemoteCategoryId
    && Number(previousRemoteCategoryId) !== Number(categoryId)
  ) {
    stopRemoteLockHeartbeat();
    await releaseRemoteCategoryLock(previousRemoteCategoryId);
    remoteCategoryLock.value = { activo: false, bloqueado: false };
  }

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
  if (isTiempoTemplate.value) {
    return {
      tiempo: remoteForm.payload.tiempo || null,
      penalizaciones: null,
      observaciones: remoteForm.payload.observaciones || null,
      no_participa: false,
      sin_tiempo_valido: false,
    };
  }

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

function motivoCambioSeleccionado() {
  if (remoteForm.motivo_cambio_opcion === "Otro") {
    return String(remoteForm.motivo_cambio_otro || "").trim();
  }

  return String(remoteForm.motivo_cambio_opcion || "").trim();
}

async function registrarResultadoRemoto() {
  if (!canUseRemoteRegistration.value) {
    setNotice(remoteNotice, "warning", remoteCategoryLockMessage.value);
    return;
  }

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
    const savedAttemptNumber = Number(remoteSelectedAttemptNumber.value || 1);
    const showNextAttemptModal = shouldShowNextAttemptModalAfterSave();

    if (isTiempoTemplate.value) {
      const seconds = durationValueToSeconds(payload.tiempo);

      if (seconds !== null && seconds <= 0) {
        remoteErrors.value = { "payload.tiempo": "El tiempo debe ser mayor a cero. Si no hay tiempo válido, usa Sin tiempo válido." };
        setNotice(remoteNotice, "warning", "No se puede guardar 00:00:00 como tiempo válido.");
        return;
      }
    }

    const observaciones =
      payload.observaciones !== undefined && payload.observaciones !== null
        ? String(payload.observaciones)
        : remoteForm.observaciones || null;

    const { data } = await axios.post("/juez/evaluaciones", {
      ronda_id: Number(remoteSelectedRondaId.value),
      equipo_id: Number(remoteSelectedTeamId.value),
      intento_numero: Number(remoteSelectedAttemptNumber.value || 1),
      expected_juez_user_id: authenticatedJudgeId.value ? Number(authenticatedJudgeId.value) : null,
      version: Number(remoteForm.version || 0),
      observaciones,
      motivo_cambio: motivoCambioSeleccionado() || null,
      payload,
    });
    const automaticPublication = data?.publicacion_automatica ?? {};
    const roundCompleted = Boolean(automaticPublication.ronda_completa);

    hydrateRemoteForm(data);
    await loadRemoteContext({ preserveTeam: isEnfrentamientoSorteo.value });

    if (showNextAttemptModal && !roundCompleted) {
      openNextAttemptModal(savedAttemptNumber);
    }

    if (roundCompleted) {
      openRoundCompletionModal(automaticPublication);
    }

    if (isEnfrentamientoSorteo.value) {
      setNotice(
        remoteNotice,
        "success",
        "Resultado actualizado y publicado en vivo. Cuando termine el combate, pulsa Terminar encuentro."
      );
      return;
    }

    if (currentRemoteTeam.value?.resultado_id && !remoteTeamEvaluationComplete(currentRemoteTeam.value)) {
      setNotice(
        remoteNotice,
        "success",
        `Evaluación guardada correctamente. Faltan ${Number(currentRemoteTeam.value.evaluaciones_pendientes ?? 0)} calificaciones de otros jueces para avanzar.`
      );
      return;
    }

    setNotice(
      remoteNotice,
      "success",
      remoteSelectedTeamId.value
        ? "Evaluación guardada correctamente. Se cargó el siguiente participante."
        : "Evaluación guardada correctamente. Ya no hay participantes pendientes."
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

function abrirModalSinTiempoValido() {
  if (!currentSorteo.value) {
    setNotice(remoteNotice, "error", "Genera el sorteo antes de registrar resultados.");
    return;
  }

  if (!remoteSelectedRondaId.value || !remoteSelectedTeamId.value) {
    setNotice(remoteNotice, "error", "Selecciona una ronda y un equipo antes de marcar sin tiempo válido.");
    return;
  }

  if (!isTiempoTemplate.value) {
    setNotice(remoteNotice, "warning", "La opción Sin tiempo válido solo aplica para la plantilla de tiempo.");
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

  invalidTimeReason.value = "No completa el circuito";
  invalidTimeOtherReason.value = "";
  invalidTimeModalOpen.value = true;
}

function cerrarModalSinTiempoValido() {
  invalidTimeModalOpen.value = false;
}

function motivoSinTiempoValido() {
  const motivo = invalidTimeReason.value === "Otro"
    ? invalidTimeOtherReason.value
    : invalidTimeReason.value;

  return String(motivo || "Sin tiempo válido").trim();
}

async function confirmarSinTiempoValidoRemoto() {
  if (!canUseRemoteRegistration.value) {
    setNotice(remoteNotice, "warning", remoteCategoryLockMessage.value);
    return;
  }

  const motivo = motivoSinTiempoValido();

  if (!motivo) {
    setNotice(remoteNotice, "warning", "Selecciona o escribe un motivo para continuar.");
    return;
  }

  remoteSaving.value = true;
  resetRemoteErrors();
  setNotice(remoteNotice, "", "");

  try {
    const observaciones = motivo;
    const savedAttemptNumber = Number(remoteSelectedAttemptNumber.value || 1);
    const showNextAttemptModal = shouldShowNextAttemptModalAfterSave();

    const { data } = await axios.post("/juez/evaluaciones", {
      ronda_id: Number(remoteSelectedRondaId.value),
      equipo_id: Number(remoteSelectedTeamId.value),
      intento_numero: Number(remoteSelectedAttemptNumber.value || 1),
      expected_juez_user_id: authenticatedJudgeId.value ? Number(authenticatedJudgeId.value) : null,
      version: Number(remoteForm.version || 0),
      observaciones,
      motivo_cambio: motivoCambioSeleccionado() || null,
      payload: {
        tiempo: null,
        penalizaciones: null,
        observaciones,
        sin_tiempo_valido: true,
        motivo_sin_tiempo_valido: motivo,
      },
    });
    const automaticPublication = data?.publicacion_automatica ?? {};
    const roundCompleted = Boolean(automaticPublication.ronda_completa);

    invalidTimeModalOpen.value = false;
    hydrateRemoteForm(data);
    await loadRemoteContext({ preserveTeam: false });

    if (showNextAttemptModal && !roundCompleted) {
      openNextAttemptModal(savedAttemptNumber);
    }

    if (roundCompleted) {
      openRoundCompletionModal(automaticPublication);
    }

    if (currentRemoteTeam.value?.resultado_id && !remoteTeamEvaluationComplete(currentRemoteTeam.value)) {
      setNotice(
        remoteNotice,
        "success",
        `Intento marcado como Sin tiempo válido. Faltan ${Number(currentRemoteTeam.value.evaluaciones_pendientes ?? 0)} calificaciones de otros jueces para avanzar.`
      );
      return;
    }

    setNotice(
      remoteNotice,
      "success",
      remoteSelectedTeamId.value
        ? "Intento marcado como Sin tiempo válido. Se cargó el siguiente participante."
        : "Intento marcado como Sin tiempo válido. Ya no hay participantes pendientes."
    );
  } catch (error) {
    if (error?.response?.status === 409) {
      setNotice(
        remoteNotice,
        "warning",
        error?.response?.data?.message ||
          "La evaluación fue actualizada por otra sesión. Se recargó el formulario."
      );
      await loadRemoteForm();
      return;
    }

    if (error?.response?.status === 422) {
      remoteErrors.value = error.response.data?.errors ?? {};
      setNotice(remoteNotice, "error", error?.response?.data?.message || "Revisa la información antes de guardar.");
      return;
    }

    setNotice(
      remoteNotice,
      "error",
      error?.response?.data?.message || "No se pudo marcar el intento como Sin tiempo válido."
    );
  } finally {
    remoteSaving.value = false;
  }
}

async function terminarEncuentroRemoto() {
  if (!canUseRemoteRegistration.value) {
    setNotice(remoteNotice, "warning", remoteCategoryLockMessage.value);
    return;
  }

  if (!canFinishCurrentMatch.value) {
    setNotice(remoteNotice, "error", "Selecciona un encuentro activo antes de finalizar.");
    return;
  }

  remoteEndingMatch.value = true;
  resetRemoteErrors();
  setNotice(remoteNotice, "", "");

  try {
    const { data } = await axios.post("/juez/evaluaciones/terminar-encuentro", {
      ronda_id: Number(remoteSelectedRondaId.value),
      equipo_id: Number(remoteSelectedTeamId.value),
      payload: normalizeRemotePayload(),
    });

    await loadRemoteContext();

    if (data?.ronda_completa) {
      openRoundCompletionModal(data);
    }

    setNotice(
      remoteNotice,
      "success",
      remoteSelectedTeamId.value
        ? "Encuentro finalizado. Se cargó el siguiente combate pendiente."
        : "Encuentro finalizado. Ya no hay combates pendientes en esta ronda."
    );
  } catch (error) {
    if (error?.response?.status === 422) {
      remoteErrors.value = error.response.data?.errors ?? {};
      setNotice(remoteNotice, "error", error?.response?.data?.message || "Revisa el encuentro antes de finalizar.");
      return;
    }

    setNotice(
      remoteNotice,
      "error",
      error?.response?.data?.message || "No se pudo finalizar el encuentro."
    );
  } finally {
    remoteEndingMatch.value = false;
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

watch(finishCurrentMatchHint, (message, previous) => {
  if (message && message !== previous) {
    showToast(message, "warning", 4500);
  }
});

async function syncRemoteQueueIfWaiting() {
  if (!isRemoteMode.value || !isRemoteMultiJudge.value || remoteLoading.value || remoteSaving.value || remoteEndingMatch.value) {
    return;
  }

  if (!currentRemoteTeam.value?.resultado_id || currentRemoteHasUnsavedChanges.value) {
    return;
  }

  await loadRemoteContext({ preserveTeam: true, silent: true });
}

onMounted(async () => {
  if (isRemoteMode.value) {
    await loadRemoteContext();
    remoteSyncTimer = setInterval(syncRemoteQueueIfWaiting, 4000);
    return;
  }

  resetMockForm();
});

onBeforeUnmount(() => {
  if (regenerateDrawModalResolver) {
    regenerateDrawModalResolver(false);
    regenerateDrawModalResolver = null;
  }

  if (toastTimer) {
    clearTimeout(toastTimer);
  }

  if (remoteSyncTimer) {
    clearInterval(remoteSyncTimer);
    remoteSyncTimer = null;
  }

  stopRemoteLockHeartbeat();
  releaseRemoteCategoryLock();
});
</script>

<template>
  <div class="space-y-5 sm:space-y-6">
    <div
      v-if="toast.show"
      class="fixed right-4 top-4 z-[10050] w-[min(92vw,420px)] rounded-2xl border bg-white p-4 shadow-2xl"
      :class="{
        'border-emerald-200': toast.type === 'success',
        'border-amber-200': toast.type === 'warning',
        'border-red-200': toast.type === 'error',
        'border-slate-200': !['success', 'warning', 'error'].includes(toast.type),
      }"
      role="status"
      aria-live="polite"
    >
      <div class="flex items-start gap-3">
        <div
          class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
          :class="{
            'bg-emerald-50 text-emerald-700': toast.type === 'success',
            'bg-amber-50 text-amber-700': toast.type === 'warning',
            'bg-red-50 text-red-700': toast.type === 'error',
            'bg-slate-100 text-slate-700': !['success', 'warning', 'error'].includes(toast.type),
          }"
        >
          <CheckCircleIcon v-if="toast.type === 'success'" class="h-5 w-5" />
          <ClockIcon v-else-if="toast.type === 'warning'" class="h-5 w-5" />
          <XMarkIcon v-else-if="toast.type === 'error'" class="h-5 w-5" />
          <ClipboardDocumentListIcon v-else class="h-5 w-5" />
        </div>

        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold text-slate-900">
            {{ toast.type === "success" ? "Correcto" : toast.type === "error" ? "Revisa la información" : toast.type === "warning" ? "Atención" : "Mensaje" }}
          </p>
          <p class="mt-1 text-sm leading-5 text-slate-600">{{ toast.message }}</p>
        </div>

        <button
          type="button"
          class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
          @click="closeToast"
          aria-label="Cerrar mensaje"
        >
          <XMarkIcon class="h-5 w-5" />
        </button>
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="invalidTimeModalOpen"
        class="fixed inset-0 z-[10070] flex items-center justify-center bg-slate-950/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="invalid-time-title"
        @click.self="cerrarModalSinTiempoValido"
      >
        <div class="relative w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p id="invalid-time-title" class="text-lg font-bold text-slate-900">Motivo</p>
              <p class="mt-1 text-sm text-slate-500">
                Selecciona por qué este intento no tiene un tiempo válido.
              </p>
            </div>

            <button
              type="button"
              class="rounded-xl p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
              @click="cerrarModalSinTiempoValido"
              aria-label="Cerrar"
            >
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>

          <div class="mt-5 grid gap-2">
            <label
              v-for="reason in invalidTimeReasons"
              :key="reason"
              class="flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 text-sm font-semibold transition"
              :class="invalidTimeReason === reason ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
            >
              <input
                v-model="invalidTimeReason"
                type="radio"
                :value="reason"
                class="h-4 w-4 border-slate-300 text-blue-600 focus:ring-blue-500"
              />
              <span>{{ reason }}</span>
            </label>
          </div>

          <textarea
            v-if="invalidTimeReason === 'Otro'"
            v-model="invalidTimeOtherReason"
            rows="3"
            class="mt-4 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Escribe el motivo"
          />

          <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button
              type="button"
              class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 font-semibold text-slate-700 transition hover:bg-slate-50"
              @click="cerrarModalSinTiempoValido"
            >
              Cancelar
            </button>

            <button
              type="button"
              class="rounded-xl bg-blue-600 px-4 py-2.5 font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="remoteSaving || (invalidTimeReason === 'Otro' && !invalidTimeOtherReason.trim())"
              @click="confirmarSinTiempoValidoRemoto"
            >
              {{ remoteSaving ? "Guardando..." : "Aceptar" }}
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="nextAttemptModalOpen"
        class="fixed inset-0 z-[10080] flex items-center justify-center bg-slate-950/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="next-attempt-title"
      >
        <div class="relative w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-2xl">
          <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-700">
            <CheckCircleIcon class="h-8 w-8" />
          </div>

          <h2 id="next-attempt-title" class="mt-4 text-xl font-bold text-slate-900">
            Intento {{ completedAttemptNumber }} finalizado
          </h2>

          <p class="mt-2 text-sm leading-6 text-slate-600">
            Desea continuar con el intento {{ nextAttemptNumber }}?
          </p>

          <button
            type="button"
            class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-5 py-3 font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            @click="continueNextAttempt"
          >
            Continuar
          </button>
        </div>
      </div>

      <div
        v-if="regenerateDrawModalOpen"
        class="fixed inset-0 z-[10095] flex items-center justify-center bg-slate-950/55 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="regenerate-draw-title"
        @click.self="closeRegenerateDrawModal(false)"
      >
        <div class="relative w-full max-w-md rounded-2xl bg-slate-900 p-5 text-slate-100 shadow-2xl ring-1 ring-white/10 sm:p-6">
          <h2 id="regenerate-draw-title" class="text-xl font-bold sm:text-2xl">
            Confirmar nuevo sorteo
          </h2>

          <p class="mt-3 text-sm leading-6 text-slate-200 sm:mt-4 sm:text-base sm:leading-7">
            Se generará un nuevo sorteo para esta ronda. El sorteo anterior quedará anulado.
            ¿Deseas continuar?
          </p>

          <div class="mt-6 flex flex-col-reverse gap-2.5 sm:mt-7 sm:flex-row sm:justify-end sm:gap-3">
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-xl border border-indigo-300/70 bg-transparent px-4 py-2.5 text-sm font-semibold text-indigo-100 transition hover:bg-indigo-500/10 sm:px-5"
              @click="closeRegenerateDrawModal(false)"
            >
              Cancelar
            </button>

            <button
              type="button"
              class="inline-flex items-center justify-center rounded-xl border border-indigo-300 bg-indigo-200 px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-indigo-100 sm:px-5"
              @click="closeRegenerateDrawModal(true)"
            >
              Aceptar
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="excludeModalOpen"
        class="fixed inset-0 z-[10085] flex items-center justify-center bg-slate-950/55 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="exclude-participant-title"
        @click.self="closeExcludeModal"
      >
        <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-slate-200">
          <div class="flex items-start gap-4 border-b border-slate-100 bg-gradient-to-br from-rose-50 via-white to-white px-5 py-5">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-100 text-rose-700">
              <ExclamationTriangleIcon class="h-7 w-7" />
            </div>

            <div class="flex-1">
              <p id="exclude-participant-title" class="text-lg font-bold text-slate-900">
                Excluir participante
              </p>
              <p class="mt-1 text-sm leading-6 text-slate-600">
                Esta acción lo retirará del sorteo de la ronda antes de generar el orden final.
              </p>
            </div>

            <button
              type="button"
              class="rounded-xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
              :disabled="excludeModalProcessing"
              @click="closeExcludeModal"
              aria-label="Cerrar"
            >
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>

          <div class="px-5 py-5">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Participante seleccionado
              </p>
              <p class="mt-2 text-base font-bold text-slate-900">
                {{ participantName(excludeModalItem, "Sin nombre") }}
              </p>
              <p class="mt-1 text-sm text-slate-600">
                {{ excludeModalItem?.institucion || "Sin institución" }}
              </p>
            </div>

            <p class="mt-4 text-sm leading-6 text-slate-600">
              ¿Quieres continuar? Si confirmas, este participante quedará excluido y no aparecerá en el sorteo de la ronda.
            </p>
          </div>

          <div class="flex flex-col-reverse gap-3 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end">
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="excludeModalProcessing"
              @click="closeExcludeModal"
            >
              Cancelar
            </button>

            <button
              type="button"
              class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-70"
              :disabled="excludeModalProcessing"
              @click="confirmExcludeParticipant"
            >
              {{ excludeModalProcessing ? "Excluyendo..." : "Sí, excluir" }}
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="roundCompletionModal.show"
        class="fixed inset-0 z-[10090] flex items-center justify-center bg-slate-950/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="round-completion-title"
        @click.self="closeRoundCompletionModal"
      >
        <div class="relative w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-2xl">
          <div
            class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl"
            :class="roundCompletionModal.isFinal ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700'"
          >
            <TrophyIcon v-if="roundCompletionModal.isFinal" class="h-8 w-8" />
            <CheckCircleIcon v-else class="h-8 w-8" />
          </div>

          <h2 id="round-completion-title" class="mt-4 text-xl font-bold text-slate-900">
            {{ roundCompletionModal.title }}
          </h2>

          <p class="mt-2 text-sm leading-6 text-slate-600">
            {{ roundCompletionModal.message }}
          </p>

          <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-center">
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 font-semibold text-slate-700 transition hover:bg-slate-50"
              @click="closeRoundCompletionModal"
            >
              Entendido
            </button>

            <a
              v-if="roundCompletionModal.isFinal"
              href="/resultados"
              class="inline-flex items-center justify-center rounded-xl bg-amber-500 px-5 py-3 font-semibold text-white shadow-sm transition hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2"
            >
              Ver resultados
            </a>
          </div>
        </div>
      </div>
    </Teleport>

    <div
      v-if="!isRemoteMode"
      class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4"
      :class="isRemoteMode ? 'border-blue-200 bg-blue-50/40' : ''"
    >
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
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
          class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 transition hover:bg-slate-50 sm:w-auto"
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

    <div
      v-if="isRemoteMode && remoteLoading && !remoteContext"
      class="rounded-2xl border border-slate-200 bg-white px-4 py-7 text-center text-slate-500 shadow-sm sm:px-6 sm:py-8"
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

      <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
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
          class="flex gap-3 overflow-x-auto pb-2 scroll-smooth snap-x snap-mandatory sm:gap-4"
        >
          <button
            v-for="category in currentCategories"
            :key="category.id"
            type="button"
            @click="pickCategory(category.id)"
            class="w-[200px] shrink-0 snap-start overflow-hidden rounded-2xl border text-left transition hover:shadow-md sm:w-[220px]"
            :class="Number(selectedCategoryId) === Number(category.id) ? 'border-blue-400 ring-2 ring-blue-100' : 'border-slate-200'"
          >
            <div class="h-[98px] w-full bg-cover bg-center sm:h-[110px]" :style="{ backgroundImage: `url(${categoryThumb(category)})` }" />

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

      <div class="grid grid-cols-1 items-stretch gap-4 sm:gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(360px,1fr)]">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 sm:p-6">
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

            <div v-if="!isRemoteMode && mockAvailableSubcats.length">
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
                {{ isEnfrentamientoSorteo ? "Combate actual" : "Equipo actual" }} <span class="text-red-500">*</span>
              </label>

              <div
                v-if="currentRemoteTeam"
                class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-4"
              >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div :class="isEnfrentamientoSorteo ? '' : 'flex-1 text-center'">
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">
                      {{
                        isEnfrentamientoSorteo
                          ? `Combate #${currentRemoteTeam.sorteo_grupo || currentRemoteTeam.sorteo_orden || "actual"}`
                          : `Participante ${currentRemoteTeam.sorteo_orden ? `#${currentRemoteTeam.sorteo_orden}` : "actual"} - ${currentRemoteTeam.intento_label || `Intento ${currentRemoteTeam.intento_numero || 1}`}`
                      }}
                    </p>
                    <template v-if="isEnfrentamientoSorteo">
                      <p class="mt-1 text-xl font-bold text-slate-950">
                        {{ participantName(scoreboardTeamA, "Participante A") }}
                        <span class="mx-2 text-sm font-black text-blue-700">VS</span>
                        {{ participantName(scoreboardTeamB, "Pasa directo") }}
                      </p>
                      <p class="mt-1 text-sm text-slate-600">
                        {{ scoreboardTeamA?.institucion || "Sin institución" }}
                        <span v-if="scoreboardTeamB"> / {{ scoreboardTeamB?.institucion || "Sin institución" }}</span>
                      </p>
                    </template>
                    <template v-else>
                      <p class="mt-1 text-xl font-bold text-slate-950">{{ participantName(currentRemoteTeam) }}</p>
                      <p class="mt-1 text-sm text-slate-600">{{ currentRemoteTeam.institucion || "Sin institución" }}</p>
                    </template>
                  </div>
                  <span class="inline-flex rounded-full bg-white px-3 py-1.5 text-sm font-semibold text-blue-700 ring-1 ring-blue-200">
                    {{ pendingRemoteTeams.length }} registros pendientes
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
                    {{ team.sorteo_orden ? `${team.sorteo_orden}. ` : '' }}{{ participantName(team) }} - {{ team.institucion || 'Sin institución' }}
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
              <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                  <p class="font-semibold text-slate-900">Estado</p>
                  <p>{{ currentResultState || "sin registro" }}</p>
                </div>

                <div>
                  <p class="font-semibold text-slate-900">Version</p>
                  <p>{{ currentVersion }}</p>
                </div>

                <div v-if="currentResultJudgeName">
                  <p class="font-semibold text-slate-900">Registrado por</p>
                  <p>
                    {{ currentResultJudgeName }}
                    <span v-if="currentResultRegisteredByOtherJudge" class="text-amber-700">(otro juez)</span>
                  </p>
                </div>
              </div>
            </div>

            <div
              v-if="remoteCategoryLockedByOther"
              class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800"
            >
              {{ remoteCategoryLockMessage }}
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
              v-if="(isTablaIndividualTemplate || isTablaPuntajeMaximoTemplate) && individualCriterionFields.length"
              class="overflow-hidden rounded-2xl border border-slate-800 bg-[#1f1f1f] text-white shadow-sm"
            >
              <div class="grid grid-cols-[1.15fr_1fr] items-center border-b border-white/10 px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-200">
                <span>Estadísticas</span>
                <span>{{ participantName(currentRemoteTeam, "Equipo") }}</span>
              </div>

              <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                  <thead>
                    <tr class="text-xs uppercase tracking-wide text-slate-300">
                      <th class="px-3 py-3 text-center">Criterio</th>
                      <th class="px-3 py-3 text-center">
                        {{ isTablaPuntajeMaximoTemplate ? "Puntaje máximo" : "Valor" }}
                      </th>
                      <th class="px-3 py-3 text-center text-emerald-300">
                        {{ isTablaPuntajeMaximoTemplate ? "Puntaje" : "Cantidad" }}
                      </th>
                      <th v-if="!isTablaPuntajeMaximoTemplate" class="px-3 py-3 text-center text-yellow-300">Puntaje</th>
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
                          <p class="text-xs text-slate-400">
                            {{ isTablaPuntajeMaximoTemplate ? "Máximo permitido" : "Suma al subtotal" }}
                          </p>
                        </td>
                      <td class="px-3 py-3 text-center text-slate-200">
                        {{ isTablaPuntajeMaximoTemplate ? Number(field.valor_unitario || 0) : `x ${Number(field.valor_unitario || 0)}` }}
                      </td>
                      <td class="px-3 py-3 text-center">
                        <input
                          :value="currentFieldValues[field.key]"
                          type="tel"
                          inputmode="numeric"
                          pattern="[0-9]*"
                          min="0"
                          :max="isTablaPuntajeMaximoTemplate ? Number(field.valor_unitario || 0) : undefined"
                          class="w-16 rounded-lg border-2 border-emerald-500 bg-transparent px-2 py-1 text-center font-bold text-white outline-none focus:ring-2 focus:ring-emerald-400/30"
                          @beforeinput="blockNonNumericInput"
                          @input="isTablaPuntajeMaximoTemplate ? onCriterionScoreInput($event, field) : onIntegerFieldInput($event, field.key)"
                        />
                      </td>
                      <td v-if="!isTablaPuntajeMaximoTemplate" class="px-3 py-3 text-center">
                        <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                          {{ individualScore(field) }}
                        </span>
                      </td>
                    </tr>

                    <tr class="border-t-4 border-red-600 bg-[#242424] text-sm font-bold">
                      <td class="px-3 py-4 text-center text-white">Subtotal</td>
                      <td class="px-3 py-4"></td>
                      <td v-if="!isTablaPuntajeMaximoTemplate" class="px-3 py-4"></td>
                      <td class="px-3 py-4 text-center text-yellow-100">{{ individualSubtotal() }}</td>
                    </tr>

                    <tr
                      v-for="field in individualCriterionFields.filter((item) => !isTablaPuntajeMaximoTemplate && item.es_penalizacion)"
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
                          :value="currentFieldValues[field.key]"
                          type="tel"
                          inputmode="numeric"
                          pattern="[0-9]*"
                          class="w-16 rounded-lg border-2 border-emerald-500 bg-transparent px-2 py-1 text-center font-bold text-white outline-none focus:ring-2 focus:ring-emerald-400/30"
                          @beforeinput="blockNonNumericInput"
                          @input="onIntegerFieldInput($event, field.key)"
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
                      <td class="px-3 py-5 text-center uppercase tracking-wide text-white">Resultado final</td>
                      <td class="px-3 py-5"></td>
                      <td v-if="!isTablaPuntajeMaximoTemplate" class="px-3 py-5"></td>
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
                <span>{{ participantName(scoreboardTeamA, "Equipo A") }}</span>
                <span>Estadísticas</span>
                <span>{{ participantName(scoreboardTeamB, "Equipo B") }}</span>
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
                          :value="currentFieldValues[fightFieldKey(field, 'A')]"
                          type="tel"
                          inputmode="numeric"
                          pattern="[0-9]*"
                          class="w-16 rounded-lg border-2 border-emerald-500 bg-transparent px-2 py-1 text-center font-bold text-white outline-none focus:ring-2 focus:ring-emerald-400/30"
                          @beforeinput="blockNonNumericInput"
                          @input="onIntegerFieldInput($event, fightFieldKey(field, 'A'))"
                        />
                      </td>
                      <td class="px-3 py-2 text-center">
                        <p class="font-semibold text-white">{{ field.label }}</p>
                        <p class="text-xs text-slate-400">Suma al subtotal</p>
                      </td>
                      <td class="px-3 py-2 text-center text-slate-200">x {{ Number(field.valor_unitario || 0) }}</td>
                      <td class="px-3 py-2 text-center">
                        <input
                          :value="currentFieldValues[fightFieldKey(field, 'B')]"
                          type="tel"
                          inputmode="numeric"
                          pattern="[0-9]*"
                          class="w-16 rounded-lg border-2 border-emerald-500 bg-transparent px-2 py-1 text-center font-bold text-white outline-none focus:ring-2 focus:ring-emerald-400/30"
                          @beforeinput="blockNonNumericInput"
                          @input="onIntegerFieldInput($event, fightFieldKey(field, 'B'))"
                        />
                      </td>
                      <td class="px-3 py-2 text-center">
                        <span class="inline-flex min-w-12 justify-center rounded-lg border-2 border-yellow-300 px-2 py-1 font-bold text-yellow-100">
                          {{ fightScore(field, 'B') }}
                        </span>
                      </td>
                    </tr>

                    <tr class="border-t-4 border-red-600 bg-[#242424] text-sm font-bold">
                      <td class="px-3 py-3 text-center text-yellow-100">{{ fightSubtotal('A') }}</td>
                      <td class="px-3 py-3"></td>
                      <td class="px-3 py-3 text-center text-white">Subtotal</td>
                      <td class="px-3 py-3"></td>
                      <td class="px-3 py-3"></td>
                      <td class="px-3 py-3 text-center text-yellow-100">{{ fightSubtotal('B') }}</td>
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
                          :value="currentFieldValues[fightFieldKey(field, 'A')]"
                          type="tel"
                          inputmode="numeric"
                          pattern="[0-9]*"
                          class="w-16 rounded-lg border-2 border-emerald-500 bg-transparent px-2 py-1 text-center font-bold text-white outline-none focus:ring-2 focus:ring-emerald-400/30"
                          @beforeinput="blockNonNumericInput"
                          @input="onIntegerFieldInput($event, fightFieldKey(field, 'A'))"
                        />
                      </td>
                      <td class="px-3 py-2 text-center">
                        <p class="font-semibold text-red-400">{{ field.label }}</p>
                        <p class="text-xs text-red-300">Resta al subtotal</p>
                      </td>
                      <td class="px-3 py-2 text-center text-slate-200">x {{ Number(field.valor_unitario || 0) }}</td>
                      <td class="px-3 py-2 text-center">
                        <input
                          :value="currentFieldValues[fightFieldKey(field, 'B')]"
                          type="tel"
                          inputmode="numeric"
                          pattern="[0-9]*"
                          class="w-16 rounded-lg border-2 border-emerald-500 bg-transparent px-2 py-1 text-center font-bold text-white outline-none focus:ring-2 focus:ring-emerald-400/30"
                          @beforeinput="blockNonNumericInput"
                          @input="onIntegerFieldInput($event, fightFieldKey(field, 'B'))"
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
                      <td class="px-3 py-4 text-center text-yellow-100">{{ fightTotal('A') }}</td>
                      <td class="px-3 py-4"></td>
                      <td class="px-3 py-4 text-center uppercase tracking-wide text-white">Resultado final</td>
                      <td class="px-3 py-4"></td>
                      <td class="px-3 py-4"></td>
                      <td class="px-3 py-4 text-center text-yellow-100">{{ fightTotal('B') }}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>

            <div
              v-if="isMarcadorTemplate"
              class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-950 text-white shadow-sm"
            >
              <div class="h-1.5 bg-blue-600"></div>

              <div class="px-4 py-4 sm:px-6 sm:py-5">
                <div class="mb-4 flex flex-col gap-2 text-sm sm:mb-5 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                  <span class="text-slate-300">Marcador del enfrentamiento</span>
                  <span class="rounded-full bg-slate-800 px-3 py-1 font-semibold text-slate-100">
                    En registro
                  </span>
                </div>

                <div class="grid grid-cols-1 items-center gap-4 sm:grid-cols-[1fr_auto_1fr] sm:gap-6">
                  <div class="min-w-0 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-800 text-lg font-black text-blue-200 ring-1 ring-slate-700">
                      A
                    </div>
                    <p class="mt-3 truncate text-base font-semibold text-white">
                      {{ participantName(scoreboardTeamA, "Equipo A") }}
                    </p>
                    <p class="mt-1 truncate text-xs text-slate-400">
                      {{ scoreboardTeamA?.institucion || "Sin institución" }}
                    </p>
                  </div>

                  <div class="order-first flex items-center justify-center gap-2 sm:order-none sm:gap-4">
                    <input
                      :value="currentFieldValues[scoreFieldForSide('A')]"
                      type="tel"
                      inputmode="numeric"
                      pattern="[0-9]*"
                      class="h-16 w-20 rounded-2xl border border-slate-700 bg-slate-900 text-center text-4xl font-bold text-white outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-400/30 sm:h-20 sm:w-24 sm:text-5xl"
                      @beforeinput="blockNonNumericInput"
                      @input="onIntegerFieldInput($event, scoreFieldForSide('A'))"
                    />
                    <span class="text-3xl font-bold text-slate-400 sm:text-5xl">-</span>
                    <input
                      :value="currentFieldValues[scoreFieldForSide('B')]"
                      type="tel"
                      inputmode="numeric"
                      pattern="[0-9]*"
                      :disabled="!scoreboardTeamB"
                      class="h-16 w-20 rounded-2xl border border-slate-700 bg-slate-900 text-center text-4xl font-bold text-white outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-400/30 disabled:opacity-40 sm:h-20 sm:w-24 sm:text-5xl"
                      @beforeinput="blockNonNumericInput"
                      @input="onIntegerFieldInput($event, scoreFieldForSide('B'))"
                    />
                  </div>

                  <div class="min-w-0 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-800 text-lg font-black text-rose-200 ring-1 ring-slate-700">
                      B
                    </div>
                    <p class="mt-3 truncate text-base font-semibold text-white">
                      {{ participantName(scoreboardTeamB, "Equipo B") }}
                    </p>
                    <p class="mt-1 truncate text-xs text-slate-400">
                      {{ scoreboardTeamB?.institucion || "Sin institución" }}
                    </p>
                  </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                  <p v-if="getFieldError('marcador_equipo_a')" class="rounded-xl bg-red-500/10 px-3 py-2 text-xs text-red-200">
                    {{ getFieldError("marcador_equipo_a") }}
                  </p>
                  <p v-if="getFieldError('marcador_equipo_b')" class="rounded-xl bg-red-500/10 px-3 py-2 text-xs text-red-200">
                    {{ getFieldError("marcador_equipo_b") }}
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
                    placeholder="Digite solo números. Ej: 735 = 00h 07m 35s"
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

                <p v-if="currentFieldValues.sin_tiempo_valido || currentFieldValues.no_participa" class="mt-3 rounded-xl bg-amber-500/10 px-3 py-2 text-xs font-semibold text-amber-100 ring-1 ring-amber-400/20">
                  Este intento está marcado como Sin tiempo válido.
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

                <label
                  v-else-if="field.type === 'duration'"
                  class="block cursor-text"
                >
                  <div class="rounded-xl border border-slate-900 bg-[#080808] px-3 py-2 text-white shadow-sm">
                    <div class="flex items-end justify-center gap-2">
                      <div class="flex items-end gap-1">
                        <span class="text-4xl font-black leading-none tracking-normal">{{ durationDisplayParts(currentFieldValues[field.key]).hours }}</span>
                        <span class="-mb-0.5 text-sm font-black leading-none">h</span>
                      </div>
                      <div class="flex items-end gap-1">
                        <span class="text-4xl font-black leading-none tracking-normal">{{ durationDisplayParts(currentFieldValues[field.key]).minutes }}</span>
                        <span class="-mb-0.5 text-sm font-black leading-none">m</span>
                      </div>
                      <div class="flex items-end gap-1">
                        <span class="text-4xl font-black leading-none tracking-normal">{{ durationDisplayParts(currentFieldValues[field.key]).seconds }}</span>
                        <span class="-mb-0.5 text-sm font-black leading-none">s</span>
                      </div>
                    </div>
                  </div>

                  <input
                    :value="durationDigitsFromValue(currentFieldValues[field.key])"
                    type="tel"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="6"
                    class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-center text-xs font-semibold text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                    placeholder="Ej: 145 = 00h 01m 45s"
                    @beforeinput="blockNonNumericInput"
                    @input="onDurationDigitsInput($event, field.key)"
                    @paste="onDurationDigitsPaste($event, field.key)"
                  />
                </label>

                <input
                  v-else-if="field.type !== 'textarea' && !isBooleanField(field)"
                  v-model="currentFieldValues[field.key]"
                  :type="field.type === 'number' ? 'number' : 'text'"
                  :step="field.type === 'number' ? '0.001' : undefined"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :placeholder="`Ingresa ${field.label.toLowerCase()}`"
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

            <div v-if="isRemoteMode && currentVersion > 0" class="space-y-2">
              <label class="block text-sm font-semibold text-slate-800">
                Motivo del cambio
              </label>
              <select
                v-model="remoteForm.motivo_cambio_opcion"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Selecciona un motivo</option>
                <option
                  v-for="option in changeReasonOptions"
                  :key="option"
                  :value="option"
                >
                  {{ option }}
                </option>
              </select>
              <input
                v-if="remoteForm.motivo_cambio_opcion === 'Otro'"
                v-model="remoteForm.motivo_cambio_otro"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Escribe el motivo de corrección"
              />
              <p v-if="getTopLevelError('motivo_cambio')" class="mt-1 text-xs text-red-600">
                {{ getTopLevelError("motivo_cambio") }}
              </p>
            </div>

            <div class="flex flex-col gap-3 pt-2 sm:flex-row">
              <button
                type="button"
                @click="registrarResultado"
                :disabled="remoteSaving || remoteEndingMatch || (isRemoteMode && (!remoteSelectedTeamId || !hasCurrentSorteo || !canEditRemoteResult || !canUseRemoteRegistration))"
                class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <CheckCircleIcon class="h-5 w-5" />
                {{ remoteSaving ? "Guardando..." : "Guardar evaluación" }}
              </button>

              <button
                v-if="isRemoteMode && isTiempoTemplate"
                type="button"
                @click="abrirModalSinTiempoValido"
                :disabled="remoteSaving || remoteEndingMatch || !remoteSelectedTeamId || !hasCurrentSorteo || !canEditRemoteResult || !canUseRemoteRegistration"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 font-semibold text-amber-800 transition hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50"
              >
                Sin tiempo válido
              </button>

              <button
                type="button"
                @click="limpiarRegistro"
                :disabled="remoteSaving || remoteEndingMatch"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 transition hover:bg-slate-50"
              >
                <XMarkIcon class="h-5 w-5 text-slate-700" />
                Limpiar
              </button>

              <button
                v-if="shouldShowFinishCurrentMatch"
                type="button"
                @click="terminarEncuentroRemoto"
                :disabled="remoteSaving || remoteEndingMatch || !canFinishCurrentMatch || !canUseRemoteRegistration"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <CheckCircleIcon class="h-5 w-5" />
                {{ remoteEndingMatch ? "Finalizando..." : "Terminar encuentro" }}
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

        <div class="h-full">
          <div class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="mb-3 flex flex-col gap-3">
              <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                  <ClipboardDocumentListIcon class="h-5 w-5 text-blue-600" />
                  <h3 class="text-lg font-semibold text-slate-900">Sorteo de la ronda</h3>
                </div>

                <span class="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600 ring-1 ring-slate-200">
                  {{ currentSorteo ? currentSorteoLabel : "Sorteo pendiente" }}
                </span>
              </div>

              <div v-if="isRemoteMode && !currentSorteo && remoteParticipants.length" class="overflow-hidden rounded-xl border border-slate-200">
                <div class="border-b border-slate-100 bg-slate-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                  Participantes de la ronda
                </div>
                <div class="max-h-[22rem] overflow-y-auto">
                  <table class="min-w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-white text-left text-[11px] uppercase tracking-wide text-slate-500">
                      <tr>
                        <th class="px-3 py-2.5 font-semibold">Participante</th>
                        <th class="px-3 py-2.5 font-semibold">Institución</th>
                        <th class="px-3 py-2.5 font-semibold">Estado</th>
                        <th class="w-28 px-3 py-2.5 font-semibold text-right">Acción</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                      <tr
                        v-for="item in remoteParticipants"
                        :key="`participante-${item.inscripcion_id}`"
                        :class="item.estado_participacion === 'excluido' ? 'bg-slate-50 opacity-70' : ''"
                      >
                        <td class="px-3 py-3">
                          <p class="font-semibold text-slate-900">{{ participantName(item, "-") }}</p>
                          <p class="mt-0.5 text-xs text-slate-500">Orden base {{ item.sorteo_orden || "-" }}</p>
                        </td>
                        <td class="px-3 py-3 text-slate-600">{{ item.institucion || "Sin institución" }}</td>
                        <td class="px-3 py-3 text-xs">
                          <span
                            class="inline-flex rounded-full px-2.5 py-1 font-semibold ring-1"
                            :class="item.estado_participacion === 'excluido'
                              ? 'bg-slate-100 text-slate-600 ring-slate-200'
                              : 'bg-emerald-50 text-emerald-700 ring-emerald-200'"
                          >
                            {{ item.estado_participacion === 'excluido' ? 'Excluido' : 'Incluido' }}
                          </span>
                        </td>
                        <td class="px-3 py-3 text-right">
                          <button
                            v-if="item.estado_participacion !== 'excluido'"
                            type="button"
                            @click.stop="excluirParticipanteRemoto(item)"
                            :disabled="remoteExcludingParticipant"
                            class="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                          >
                            {{ remoteExcludingParticipant ? 'Excluyendo...' : 'Excluir' }}
                          </button>
                          <span v-else class="text-xs font-semibold text-slate-400">Excluido</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <button
                v-if="isRemoteMode"
                type="button"
                @click="generarSorteoRemoto"
                :disabled="remoteDrawGenerating || !canGenerateRemoteDraw"
                class="mx-auto inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50"
                :class="currentSorteo ? 'border border-blue-200 bg-white text-blue-700 hover:bg-blue-50' : 'bg-blue-600 text-white hover:bg-blue-700'"
              >
                <SparklesIcon class="h-5 w-5" />
                {{ remoteDrawButtonLabel }}
              </button>
            </div>


            <div
              v-if="currentSorteo?.tipo_sorteo === 'enfrentamiento' && (sorteoGroups.length || sorteoDirectItems.length)"
              class="space-y-4"
            >
              <div v-if="sorteoGroups.length" class="overflow-hidden rounded-xl border border-slate-200">
                <div class="border-b border-slate-100 bg-slate-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                  {{ sorteoDirectItems.length ? "Ronda previa" : "Llaves de enfrentamiento" }}
                </div>
                <div class="max-h-[36rem] overflow-y-auto">
                  <table class="min-w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
                      <tr>
                        <th class="w-20 px-3 py-2.5 font-semibold">Combate</th>
                        <th class="px-3 py-2.5 font-semibold">Participante A</th>
                        <th class="w-12 px-2 py-2.5 text-center font-semibold"></th>
                        <th class="px-3 py-2.5 font-semibold">Participante B</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                      <tr
                        v-for="group in sorteoGroups"
                        :key="`resumen-grupo-${group.grupo}`"
                        class="transition"
                        :class="[
                          isCurrentMatchGroup(group) ? 'bg-blue-50 ring-1 ring-inset ring-blue-200' : '',
                          isSelectableMatchGroup(group) ? 'cursor-pointer hover:bg-slate-50' : 'cursor-not-allowed opacity-60'
                        ]"
                        :title="isSelectableMatchGroup(group) ? '' : 'Debes registrar el participante actual según el orden del sorteo antes de avanzar.'"
                        @click="selectRemoteTeamFromSummary(group.items.find((item) => item.lado === 'A') ?? group.items[0])"
                      >
                        <td class="px-3 py-3">
                          <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">
                            {{ group.grupo }}
                          </span>
                        </td>
                        <td class="px-3 py-3">
                          <p class="font-semibold text-slate-900">
                            {{ participantName((group.items.find((item) => item.lado === 'A') ?? group.items[0]), "-") }}
                          </p>
                          <p class="mt-0.5 text-xs text-slate-500">
                            {{ (group.items.find((item) => item.lado === 'A') ?? group.items[0])?.institucion || "Sin institución" }}
                          </p>
                        </td>
                        <td class="px-2 py-3 text-center">
                          <span class="inline-flex h-7 min-w-8 items-center justify-center rounded-lg bg-slate-800 px-2 text-[11px] font-bold uppercase tracking-wide text-white shadow-sm">
                            VS
                          </span>
                        </td>
                        <td class="px-3 py-3">
                          <p
                            class="font-semibold text-slate-900"
                            :class="String(group.items.find((item) => item.lado === 'B')?.equipo_id) === String(remoteSelectedTeamId) ? 'text-blue-700' : ''"
                          >
                            {{ participantName(group.items.find((item) => item.lado === 'B'), "-") }}
                          </p>
                          <p class="mt-0.5 text-xs text-slate-500">
                            {{ group.items.find((item) => item.lado === 'B')?.institucion || "Sin institución" }}
                          </p>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <div v-if="sorteoDirectItems.length" class="overflow-hidden rounded-xl border border-emerald-200">
                <div class="border-b border-emerald-100 bg-emerald-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                  Pasan directo
                </div>
                <div class="max-h-[26rem] overflow-y-auto">
                  <table class="min-w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-white text-left text-[11px] uppercase tracking-wide text-slate-500">
                      <tr>
                        <th class="w-16 px-3 py-2.5 font-semibold">Orden</th>
                        <th class="px-3 py-2.5 font-semibold">Participante</th>
                        <th class="px-3 py-2.5 font-semibold">Institución</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-emerald-50 bg-white">
                      <tr v-for="item in sorteoDirectItems" :key="`directo-${item.inscripcion_id}`">
                        <td class="px-3 py-3 font-semibold text-emerald-700">{{ item.orden }}</td>
                        <td class="px-3 py-3 font-semibold text-slate-900">{{ participantName(item, "-") }}</td>
                        <td class="px-3 py-3 text-slate-600">{{ item.institucion || "Sin institución" }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div v-else-if="currentSorteo && sorteoOrdenItems.length" class="overflow-hidden rounded-xl border border-slate-200">
              <div class="max-h-[42rem] overflow-y-auto">
                <table class="min-w-full text-sm">
                  <thead class="sticky top-0 z-10 bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
                    <tr>
                      <th class="w-16 px-3 py-2.5 font-semibold">Orden</th>
                      <th class="w-24 px-3 py-2.5 font-semibold">Intento</th>
                      <th class="px-3 py-2.5 font-semibold">Participante</th>
                      <th class="px-3 py-2.5 font-semibold">Institución</th>
                      <th class="px-3 py-2.5 font-semibold">Estado</th>

                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 bg-white">
                    <tr
                      v-for="item in sorteoOrdenItems"
                      :key="`resumen-sorteo-${item.orden}-${item.equipo_id}-${item.intento_numero || 1}`"
                      class="transition"
                      :class="[
                        String(item.equipo_id) === String(remoteSelectedTeamId) && Number(item.intento_numero || 1) === Number(remoteSelectedAttemptNumber || 1) ? 'bg-blue-50' : '',
                        item.is_selectable ? 'cursor-pointer hover:bg-slate-50' : 'cursor-not-allowed opacity-60'
                      ]"
                      :title="item.is_selectable ? '' : 'Debes registrar el participante actual según el orden del sorteo antes de avanzar.'"
                      @click="selectRemoteTeamFromSummary(item)"
                    >
                      <td class="px-3 py-3">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">
                          {{ item.orden }}
                        </span>
                      </td>
                      <td class="px-3 py-3 font-semibold text-blue-700">
                        {{ item.intento_label || `Intento ${item.intento_numero || 1}` }}
                      </td>
                      <td class="px-3 py-3 font-semibold text-slate-900">{{ item.participante }}</td>
                      <td class="px-3 py-3 text-slate-600">{{ item.institucion }}</td>
                      <td class="px-3 py-3 text-xs">
                        <span
                          v-if="item.evaluacion_completa"
                          class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-700 ring-1 ring-emerald-200"
                        >
                          Completo
                        </span>
                        <span
                          v-else-if="item.resultado_id"
                          class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 font-semibold text-amber-700 ring-1 ring-amber-200"
                        >
                          Faltan {{ item.evaluaciones_pendientes }}
                        </span>
                        <span v-else class="inline-flex rounded-full bg-slate-50 px-2.5 py-1 font-semibold text-slate-500 ring-1 ring-slate-200">
                          Pendiente
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div v-else class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-500">
              Genera el sorteo para ver el orden de participación.
            </div>

            <div v-if="false" class="space-y-3 text-sm text-slate-700">
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

