<script setup>
import AdminLayout from "@/layouts/AdminLayout.vue";
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import {
  ArrowDownTrayIcon,
  CheckCircleIcon,
  ChevronDownIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ChevronUpIcon,
  DocumentArrowUpIcon,
  EyeIcon,
  MinusIcon,
  PencilSquareIcon,
  PhotoIcon,
  PlusIcon,
  TrashIcon,
  XMarkIcon,
} from "@heroicons/vue/24/outline";

defineOptions({ layout: AdminLayout });

const props = defineProps({
  competenciaId: {
    type: Number,
    default: null,
  },
  activeTab: {
    type: String,
    default: "plantillas",
  },
  competencias: {
    type: Array,
    default: () => [],
  },
  tiposCertificados: {
    type: Object,
    default: () => ({}),
  },
  configuracionDefault: {
    type: Object,
    default: () => ({}),
  },
  plantillas: {
    type: Array,
    default: () => [],
  },
  emergenciaCategorias: {
    type: Array,
    default: () => [],
  },
  emergenciaParticipantes: {
    type: Array,
    default: () => [],
  },
});

const page = usePage();
const tabs = [
  { id: "plantillas", label: "Configurar plantillas" },
  { id: "manual", label: "Generación manual" },
];
const activeTab = ref(
  tabs.some((tab) => tab.id === props.activeTab) ? props.activeTab : "plantillas"
);
const selectedCompetition = ref(props.competenciaId || "");
const selectedField = ref("participante");
const fieldsDropdownOpen = ref(false);
const imagePreviewUrl = ref("");
const editingTemplate = ref(null);
const selectedManualCategoryId = ref(props.emergenciaCategorias[0]?.id || "");
const selectedManualParticipantId = ref("");
const selectedManualCertificateType = ref("participacion");
const manualSearch = ref("");
const deleteModal = ref({
  open: false,
  plantilla: null,
});

const fieldLabels = {
  participante: "Nombre del participante",
  competencia: "Competencia o evento",
  categoria: "Categoría",
  equipo: "Equipo",
  prototipo: "Prototipo",
  institucion: "Institución o club",
  fecha: "Fecha",
};

const sampleTexts = {
  participante: "NOMBRE DEL PARTICIPANTE",
  competencia: "COMPETENCIA O EVENTO",
  categoria: "CATEGORÍA",
  equipo: "EQUIPO",
  prototipo: "PROTOTIPO",
  institucion: "INSTITUCIÓN/CLUB",
  fecha: "23/05/2026",
};

function cloneConfig(config) {
  return JSON.parse(JSON.stringify(config || {}));
}

function normalizeFieldConfig(key, config = {}) {
  const defaultConfig = props.configuracionDefault[key] || {};

  return {
    x: config.x ?? defaultConfig.x ?? 50,
    y: config.y ?? defaultConfig.y ?? 50,
    size: config.size ?? defaultConfig.size ?? 12,
    align: config.align ?? defaultConfig.align ?? "center",
    bold: config.bold ?? defaultConfig.bold ?? true,
    visible: config.visible ?? defaultConfig.visible ?? true,
  };
}

function normalizeConfig(config = {}) {
  return Object.keys(fieldLabels).reduce((normalized, key) => {
    normalized[key] = normalizeFieldConfig(key, config[key] || {});
    return normalized;
  }, {});
}

const camposConfig = reactive(normalizeConfig(props.configuracionDefault));

const selectedCompetitionData = computed(() =>
  props.competencias.find((item) => Number(item.id) === Number(selectedCompetition.value))
);
const tiposConPlantillaActiva = computed(
  () => new Set(props.plantillas.filter((item) => item.activo).map((item) => item.tipo_certificado))
);
const manualCategories = computed(() => props.emergenciaCategorias || []);
const manualParticipants = computed(() => props.emergenciaParticipantes || []);
const filteredManualParticipants = computed(() => {
  const categoryId = Number(selectedManualCategoryId.value || 0);
  const term = manualSearch.value.trim().toLowerCase();

  return manualParticipants.value.filter((item) => {
    if (categoryId > 0 && Number(item.categoria_id) !== categoryId) {
      return false;
    }

    if (!term) {
      return true;
    }

    return [
      item.participante,
      item.equipo,
      item.prototipo,
      item.institucion,
      item.categoria,
    ]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(term));
  });
});
const selectedManualParticipant = computed(() =>
  filteredManualParticipants.value.find(
    (item) => Number(item.integrante_id) === Number(selectedManualParticipantId.value)
  ) || null
);
const manualTypeOptions = computed(() =>
  Object.entries(props.tiposCertificados || {}).map(([value, label]) => ({
    value,
    label,
    available: tiposConPlantillaActiva.value.has(value),
  }))
);
const manualDownloadUrl = computed(() => {
  if (!selectedManualParticipant.value || !tiposConPlantillaActiva.value.has(selectedManualCertificateType.value)) {
    return "";
  }

  const params = new URLSearchParams({
    competencia_id: String(selectedCompetition.value || ""),
    integrante_id: String(selectedManualParticipant.value.integrante_id),
    tipo_certificado: String(selectedManualCertificateType.value),
  });

  return `/admin/certificados/emergencia/descargar?${params.toString()}`;
});

const selectedConfig = computed(() => camposConfig[selectedField.value] ?? {});
const campos = computed(() =>
  Object.keys(fieldLabels).map((key) => ({
    key,
    label: fieldLabels[key],
    config: camposConfig[key],
  }))
);
const visibleCampos = computed(() => campos.value.filter((field) => field.config?.visible !== false));
const selectedFieldIsVisible = computed(() => selectedConfig.value?.visible !== false);

const form = useForm({
  competencia_id: selectedCompetition.value,
  anio: selectedCompetitionData.value?.anio || new Date().getFullYear(),
  tipo_certificado: "participacion",
  archivo_plantilla: null,
  configuracion_textos: JSON.stringify(camposConfig),
  activo: true,
});

watch(selectedCompetition, (value) => {
  form.competencia_id = value;
  form.anio = selectedCompetitionData.value?.anio || form.anio;

  router.get(
    "/admin/certificados",
    { competencia_id: value, tab: activeTab.value },
    { preserveScroll: true, preserveState: true, replace: true }
  );
});

watch(
  () => props.emergenciaCategorias,
  (categories) => {
    const current = Number(selectedManualCategoryId.value || 0);
    const exists = (categories || []).some((item) => Number(item.id) === current);
    selectedManualCategoryId.value = exists ? current : categories?.[0]?.id || "";
  },
  { immediate: true }
);

watch(selectedManualCategoryId, () => {
  const current = Number(selectedManualParticipantId.value || 0);
  const exists = filteredManualParticipants.value.some((item) => Number(item.integrante_id) === current);

  if (!exists) {
    selectedManualParticipantId.value = filteredManualParticipants.value[0]?.integrante_id || "";
  }
});

watch(
  filteredManualParticipants,
  (items) => {
    const current = Number(selectedManualParticipantId.value || 0);
    const exists = items.some((item) => Number(item.integrante_id) === current);

    if (!exists) {
      selectedManualParticipantId.value = items[0]?.integrante_id || "";
    }
  },
  { immediate: true }
);

watch(
  manualTypeOptions,
  (options) => {
    const current = selectedManualCertificateType.value;
    const exists = options.some((item) => item.value === current && item.available);

    if (!exists) {
      selectedManualCertificateType.value = options.find((item) => item.available)?.value || "participacion";
    }
  },
  { immediate: true }
);

watch(
  camposConfig,
  () => {
    form.configuracion_textos = JSON.stringify(camposConfig);
  },
  { deep: true }
);

function clamp(value, min, max) {
  return Math.min(max, Math.max(min, Number(value)));
}

function ensureSelectedConfig() {
  if (!camposConfig[selectedField.value]) {
    camposConfig[selectedField.value] = normalizeFieldConfig(selectedField.value);
  }

  return camposConfig[selectedField.value];
}

function moveSelected(deltaX, deltaY) {
  if (!selectedFieldIsVisible.value) return;
  const config = ensureSelectedConfig();
  config.x = clamp((Number(config.x) || 0) + deltaX, 0, 100);
  config.y = clamp((Number(config.y) || 0) + deltaY, 0, 100);
}

function resizeSelected(delta) {
  if (!selectedFieldIsVisible.value) return;
  const config = ensureSelectedConfig();
  config.size = clamp((Number(config.size) || 12) + delta, 6, 48);
}

function setAlign(value) {
  if (!selectedFieldIsVisible.value) return;
  ensureSelectedConfig().align = value;
}

function setBold(value) {
  if (!selectedFieldIsVisible.value) return;
  ensureSelectedConfig().bold = value;
}

function resetPositions() {
  const defaults = normalizeConfig(props.configuracionDefault);
  applyConfig(defaults);
}

function applyConfig(config) {
  for (const key of Object.keys(fieldLabels)) {
    camposConfig[key] = normalizeFieldConfig(key, config[key] || {});
  }

  if (camposConfig[selectedField.value]?.visible === false) {
    selectedField.value = visibleCampos.value[0]?.key || selectedField.value;
  }
}

function toggleFieldVisibility(key) {
  const config = camposConfig[key] || normalizeFieldConfig(key);
  config.visible = !config.visible;
  camposConfig[key] = config;

  if (config.visible) {
    selectedField.value = key;
    return;
  }

  if (selectedField.value === key) {
    selectedField.value = visibleCampos.value.find((field) => field.key !== key)?.key || key;
  }
}

function selectField(key) {
  if (camposConfig[key]?.visible === false) return;
  selectedField.value = key;
  fieldsDropdownOpen.value = false;
}

function placeSelected(event) {
  if (!selectedFieldIsVisible.value) return;
  const bounds = event.currentTarget.getBoundingClientRect();
  const x = ((event.clientX - bounds.left) / bounds.width) * 100;
  const y = ((event.clientY - bounds.top) / bounds.height) * 100;
  const config = ensureSelectedConfig();
  config.x = clamp(x, 0, 100);
  config.y = clamp(y, 0, 100);
}

function submit() {
  form.configuracion_textos = JSON.stringify(camposConfig);

  if (editingTemplate.value?.id) {
    form
      .transform((data) => ({ ...data, _method: "patch" }))
      .post(`/admin/certificados/${editingTemplate.value.id}`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
          cancelEdit();
        },
      });

    return;
  }

  form.transform((data) => data).post("/admin/certificados", {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      form.reset("archivo_plantilla");
      clearImagePreview();
    },
  });
}

function switchTab(tabId) {
  activeTab.value = tabId;

  if (typeof window !== "undefined") {
    const url = new URL(window.location.href);
    url.searchParams.set("competencia_id", String(selectedCompetition.value || ""));
    url.searchParams.set("tab", tabId);
    window.history.replaceState({}, "", url.toString());
  }
}

function handleManualDownloadClick(event) {
  if (!manualDownloadUrl.value) {
    event.preventDefault();
  }
}

function editPlantilla(plantilla) {
  if (!plantilla?.id) return;

  editingTemplate.value = plantilla;
  selectedField.value = "participante";
  form.competencia_id = plantilla.competencia_id;
  form.anio = plantilla.anio || selectedCompetitionData.value?.anio || new Date().getFullYear();
  form.tipo_certificado = plantilla.tipo_certificado;
  form.archivo_plantilla = null;
  form.activo = Boolean(plantilla.activo);
  applyConfig(normalizeConfig(plantilla.configuracion_textos || props.configuracionDefault));
  clearImagePreview();
  imagePreviewUrl.value = plantilla.archivo_url || "";
}

function cancelEdit() {
  editingTemplate.value = null;
  form.reset("archivo_plantilla");
  form.tipo_certificado = "participacion";
  form.activo = true;
  form.anio = selectedCompetitionData.value?.anio || new Date().getFullYear();
  applyConfig(normalizeConfig(props.configuracionDefault));
  clearImagePreview();
}

function openDeleteModal(plantilla) {
  deleteModal.value = {
    open: true,
    plantilla,
  };
}

function closeDeleteModal() {
  deleteModal.value = {
    open: false,
    plantilla: null,
  };
}

function deletePlantilla() {
  const plantilla = deleteModal.value.plantilla;
  if (!plantilla?.id) return;

  router.post(plantilla.delete_url || `/admin/certificados/${plantilla.id}`, {
    _method: "delete",
  }, {
    preserveScroll: true,
    onSuccess: () => {
      if (editingTemplate.value?.id === plantilla.id) {
        cancelEdit();
      }

      closeDeleteModal();
    },
  });
}

function clearImagePreview() {
  if (imagePreviewUrl.value?.startsWith("blob:")) {
    URL.revokeObjectURL(imagePreviewUrl.value);
  }

  imagePreviewUrl.value = "";
}

function fileChanged(event) {
  form.archivo_plantilla = event.target.files?.[0] || null;
  clearImagePreview();

  if (form.archivo_plantilla) {
    imagePreviewUrl.value = URL.createObjectURL(form.archivo_plantilla);
  }
}

function overlayStyle(field) {
  const config = field.config || {};
  const align = config.align || "left";

  return {
    left: `${config.x ?? 50}%`,
    top: `${config.y ?? 50}%`,
    transform: "translateY(-50%)",
    width: `${field.key === "participante" || field.key === "competencia" ? 44 : 24}%`,
    marginLeft: align === "center" ? `${field.key === "participante" || field.key === "competencia" ? -22 : -12}%` : align === "right" ? `${field.key === "participante" || field.key === "competencia" ? -44 : -24}%` : "0",
    fontSize: `${Math.max(8, Number(config.size || 12) * 0.62)}px`,
    fontWeight: config.bold ? "800" : "500",
    textAlign: align,
  };
}

onBeforeUnmount(() => {
  clearImagePreview();
});
</script>

<template>
  <div class="mx-auto w-full max-w-[1180px] space-y-5 px-3 py-5 sm:space-y-6 sm:px-6 sm:py-6 lg:px-4">
    <section>
      <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Certificados</h1>
      <p class="mt-1 text-sm text-slate-500">
        Gestiona las plantillas activas para participación y podio por competencia.
      </p>
    </section>

    <div
      v-if="page.props.flash?.success"
      class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700"
    >
      {{ page.props.flash.success }}
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div class="grid gap-4 md:grid-cols-[1fr_240px] md:items-end">
        <div>
          <label class="mb-1 block text-sm font-semibold text-slate-800">Competencia</label>
          <select
            v-model="selectedCompetition"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option v-for="competencia in competencias" :key="competencia.id" :value="competencia.id">
              {{ competencia.nombre }}
            </option>
          </select>
        </div>

        <div>
          <label class="mb-1 block text-sm font-semibold text-slate-800">Año / edición</label>
          <input
            v-model="form.anio"
            type="number"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
      <div class="grid gap-2 sm:grid-cols-2">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          class="rounded-xl px-4 py-3 text-sm font-bold transition"
          :class="activeTab === tab.id ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
          @click="switchTab(tab.id)"
        >
          {{ tab.label }}
        </button>
      </div>
    </section>

    <section v-if="activeTab === 'plantillas'" class="grid gap-6 xl:grid-cols-[390px_1fr]">
      <form class="space-y-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" @submit.prevent="submit">
        <div class="flex items-center gap-3">
          <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50">
            <DocumentArrowUpIcon class="h-6 w-6 text-blue-600" />
          </div>
          <div>
            <h2 class="text-lg font-bold text-slate-900">
              {{ editingTemplate ? "Editar plantilla" : "Nueva plantilla" }}
            </h2>
            <p class="text-sm text-slate-500">
              {{ editingTemplate ? "Ajusta posiciones o reemplaza la imagen." : "Sube JPG o PNG horizontal." }}
            </p>
          </div>
        </div>

        <div
          v-if="editingTemplate"
          class="flex items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700"
        >
          <span>Editando: {{ editingTemplate.tipo_label }}</span>
          <button type="button" class="rounded-lg p-1 hover:bg-white" @click="cancelEdit">
            <XMarkIcon class="h-5 w-5" />
          </button>
        </div>

        <div>
          <label class="mb-1 block text-sm font-semibold text-slate-800">Tipo de certificado</label>
          <select
            v-model="form.tipo_certificado"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option v-for="(label, value) in tiposCertificados" :key="value" :value="value">
              {{ label }}
            </option>
          </select>
          <p v-if="form.errors.tipo_certificado" class="mt-1 text-xs text-red-600">{{ form.errors.tipo_certificado }}</p>
        </div>

        <div>
          <label class="mb-1 block text-sm font-semibold text-slate-800">Archivo de plantilla</label>
          <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-600 hover:border-blue-300 hover:bg-blue-50">
            <PhotoIcon class="h-5 w-5 text-slate-500" />
            <span class="truncate">{{ form.archivo_plantilla?.name || (editingTemplate ? "Mantener imagen actual" : "Seleccionar imagen") }}</span>
            <input type="file" accept="image/png,image/jpeg" class="hidden" @change="fileChanged" />
          </label>
          <p v-if="form.errors.archivo_plantilla" class="mt-1 text-xs text-red-600">{{ form.errors.archivo_plantilla }}</p>
        </div>

        <div>
          <label class="mb-1 block text-sm font-semibold text-slate-800">Texto a configurar</label>
          <div class="relative">
            <button
              type="button"
              class="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
              @click="fieldsDropdownOpen = !fieldsDropdownOpen"
            >
              <span class="truncate">{{ selectedFieldIsVisible ? fieldLabels[selectedField] : "Selecciona un campo activo" }}</span>
              <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-bold text-blue-700">
                {{ visibleCampos.length }}/{{ campos.length }}
              </span>
            </button>

            <div
              v-if="fieldsDropdownOpen"
              class="absolute z-30 mt-2 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
            >
              <div
                v-for="field in campos"
                :key="field.key"
                class="flex items-center gap-3 border-b border-slate-100 px-3 py-2.5 last:border-b-0"
                :class="selectedField === field.key ? 'bg-blue-50' : 'hover:bg-slate-50'"
              >
                <input
                  :id="`campo-certificado-${field.key}`"
                  :checked="field.config?.visible !== false"
                  type="checkbox"
                  class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                  @change="toggleFieldVisibility(field.key)"
                />
                <button
                  type="button"
                  class="min-w-0 flex-1 truncate text-left text-sm font-semibold"
                  :class="field.config?.visible === false ? 'text-slate-400' : 'text-slate-800'"
                  @click="selectField(field.key)"
                >
                  {{ field.label }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4" :class="{ 'opacity-60': !selectedFieldIsVisible }">
          <p class="text-sm font-bold text-slate-900">
            {{ selectedFieldIsVisible ? fieldLabels[selectedField] : "No hay campos activos para configurar" }}
          </p>
          <div class="mt-4 grid grid-cols-[44px_44px_44px] justify-center gap-2">
            <span></span>
            <button type="button" class="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-slate-700 shadow-sm hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!selectedFieldIsVisible" @click="moveSelected(0, -1)">
              <ChevronUpIcon class="h-6 w-6" />
            </button>
            <span></span>
            <button type="button" class="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-slate-700 shadow-sm hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!selectedFieldIsVisible" @click="moveSelected(-1, 0)">
              <ChevronLeftIcon class="h-6 w-6" />
            </button>
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-900 text-xs font-bold text-white">
              Mover
            </div>
            <button type="button" class="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-slate-700 shadow-sm hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!selectedFieldIsVisible" @click="moveSelected(1, 0)">
              <ChevronRightIcon class="h-6 w-6" />
            </button>
            <span></span>
            <button type="button" class="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-slate-700 shadow-sm hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!selectedFieldIsVisible" @click="moveSelected(0, 1)">
              <ChevronDownIcon class="h-6 w-6" />
            </button>
            <span></span>
          </div>

          <div class="mt-4 grid grid-cols-2 gap-3">
            <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!selectedFieldIsVisible" @click="resizeSelected(-1)">
              <MinusIcon class="h-4 w-4" />
              Tamaño
            </button>
            <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!selectedFieldIsVisible" @click="resizeSelected(1)">
              <PlusIcon class="h-4 w-4" />
              Tamaño
            </button>
          </div>

          <div class="mt-4 grid gap-3">
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Alineación</label>
              <select
                :value="selectedConfig.align || 'left'"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!selectedFieldIsVisible"
                @change="setAlign($event.target.value)"
              >
                <option value="left">Izquierda</option>
                <option value="center">Centro</option>
                <option value="right">Derecha</option>
              </select>
            </div>

            <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700">
              <input
                :checked="Boolean(selectedConfig.bold)"
                type="checkbox"
                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!selectedFieldIsVisible"
                @change="setBold($event.target.checked)"
              />
              Texto en negrita
            </label>
          </div>
        </div>

        <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700">
          <input v-model="form.activo" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
          Activar como plantilla vigente
        </label>

        <div class="grid gap-3 sm:grid-cols-2">
          <button
            type="button"
            class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            @click="resetPositions"
          >
            Restablecer posiciones
          </button>
          <button
            type="submit"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="form.processing"
          >
            <ArrowDownTrayIcon class="h-5 w-5" />
            {{ form.processing ? "Guardando..." : editingTemplate ? "Actualizar plantilla" : "Guardar plantilla" }}
          </button>
        </div>
      </form>

      <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 class="text-lg font-bold text-slate-900">Vista previa de posiciones</h2>
              <p class="text-sm text-slate-500">Selecciona un campo y haz clic en la plantilla para ubicarlo.</p>
            </div>
            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
              {{ selectedFieldIsVisible ? fieldLabels[selectedField] : "Sin campo activo" }}
            </span>
          </div>

          <div
            class="relative aspect-[842/595] w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-100"
            @click="placeSelected"
          >
            <img
              v-if="imagePreviewUrl"
              :src="imagePreviewUrl"
              alt="Vista previa de la plantilla"
              class="h-full w-full object-cover"
            />
            <div v-else class="flex h-full items-center justify-center px-6 text-center text-sm font-semibold text-slate-500">
              Sube una plantilla para ver las posiciones sobre el diseño.
            </div>

            <button
              v-for="field in visibleCampos"
              :key="`preview-${field.key}`"
              type="button"
              class="absolute max-w-[45%] rounded border px-1.5 py-0.5 leading-tight shadow-sm"
              :class="selectedField === field.key ? 'border-blue-500 bg-blue-600/90 text-white' : 'border-blue-200 bg-white/85 text-blue-900'"
              :style="overlayStyle(field)"
              @click.stop="selectedField = field.key"
            >
              {{ sampleTexts[field.key] }}
            </button>
          </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-bold text-slate-900">Plantillas registradas</h2>
          </div>

          <div class="divide-y divide-slate-200">
            <article v-for="plantilla in plantillas" :key="plantilla.id" class="grid gap-4 px-4 py-4 sm:px-5 md:grid-cols-[120px_1fr_auto] md:items-center">
              <img :src="plantilla.archivo_url" alt="" class="h-20 w-28 rounded-lg border border-slate-200 object-cover" />

              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="font-bold text-slate-900">{{ plantilla.tipo_label }}</h3>
                  <span
                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold"
                    :class="plantilla.activo ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                  >
                    <CheckCircleIcon v-if="plantilla.activo" class="h-4 w-4" />
                    {{ plantilla.activo ? "Activa" : "Inactiva" }}
                  </span>
                </div>
                <p class="mt-1 text-sm text-slate-500">Año {{ plantilla.anio || "general" }} - {{ plantilla.created_at }}</p>
              </div>

              <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap md:justify-end">
                <button
                  type="button"
                  class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                  @click="editPlantilla(plantilla)"
                >
                  <PencilSquareIcon class="h-5 w-5" />
                  Editar
                </button>
                <a
                  :href="`/admin/certificados/${plantilla.id}/preview`"
                  target="_blank"
                  class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                  <EyeIcon class="h-5 w-5" />
                  Previsualizar
                </a>
                <button
                  type="button"
                  class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-red-50 text-red-600 transition hover:bg-red-100"
                  aria-label="Eliminar plantilla"
                  title="Eliminar plantilla"
                  @click="openDeleteModal(plantilla)"
                >
                  <TrashIcon class="h-5 w-5" />
                </button>
              </div>
            </article>

            <div v-if="!plantillas.length" class="px-5 py-12 text-center text-slate-500">
              No hay plantillas registradas para la competencia seleccionada.
            </div>
          </div>
        </div>
      </div>
    </section>

    <section v-else class="grid gap-6 xl:grid-cols-[360px_1fr]">
      <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50">
              <ArrowDownTrayIcon class="h-6 w-6 text-amber-600" />
            </div>
            <div>
              <h2 class="text-lg font-bold text-slate-900">Generación manual</h2>
              <p class="mt-1 text-sm text-slate-500">
                Selecciona la categoría, el integrante y el tipo de certificado para descargarlo al instante.
              </p>
            </div>
          </div>

          <div
            v-if="page.props.errors?.integrante_id || page.props.errors?.tipo_certificado || page.props.errors?.inscripcion_integrante_id || page.props.errors?.plantilla"
            class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
          >
            {{ page.props.errors?.integrante_id || page.props.errors?.tipo_certificado || page.props.errors?.inscripcion_integrante_id || page.props.errors?.plantilla }}
          </div>

          <div
            v-if="!manualCategories.length"
            class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
          >
            No existen inscripciones aprobadas en esta competencia para generar certificados manuales.
          </div>

          <div v-else class="mt-5 space-y-4">
            <div>
              <label class="mb-1 block text-sm font-semibold text-slate-800">Categoría</label>
              <select
                v-model="selectedManualCategoryId"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option v-for="categoria in manualCategories" :key="categoria.id" :value="categoria.id">
                  {{ categoria.nombre }} · {{ categoria.integrantes_count }} integrantes
                </option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-semibold text-slate-800">Buscar integrante o equipo</label>
              <input
                v-model="manualSearch"
                type="text"
                placeholder="Escribe el nombre del integrante, equipo o prototipo"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-semibold text-slate-800">Tipo de certificado</label>
              <select
                v-model="selectedManualCertificateType"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option
                  v-for="option in manualTypeOptions"
                  :key="option.value"
                  :value="option.value"
                  :disabled="!option.available"
                >
                  {{ option.label }}{{ option.available ? "" : " · sin plantilla activa" }}
                </option>
              </select>
              <p v-if="!tiposConPlantillaActiva.has(selectedManualCertificateType)" class="mt-1 text-xs text-amber-700">
                Primero activa una plantilla para este tipo de certificado.
              </p>
            </div>
          </div>
        </div>

        <div v-if="selectedManualParticipant" class="rounded-2xl border border-blue-200 bg-blue-50 p-4 shadow-sm sm:p-5">
          <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-700">Integrante seleccionado</p>
          <h3 class="mt-2 text-lg font-bold text-slate-900">{{ selectedManualParticipant.participante }}</h3>
          <p class="mt-1 text-sm text-slate-600">
            {{ selectedManualParticipant.equipo }} · {{ selectedManualParticipant.categoria }}
          </p>
          <p class="mt-1 text-sm text-slate-500">
            {{ selectedManualParticipant.prototipo }} · {{ selectedManualParticipant.institucion || "Sin institución" }}
          </p>

          <a
            :href="manualDownloadUrl || undefined"
            class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold transition sm:w-auto"
            :class="manualDownloadUrl ? 'bg-blue-600 text-white hover:bg-blue-700' : 'cursor-not-allowed bg-slate-200 text-slate-500'"
            :aria-disabled="!manualDownloadUrl"
            @click="handleManualDownloadClick"
          >
            <ArrowDownTrayIcon class="h-5 w-5" />
            Descargar certificado
          </a>
        </div>
      </div>

      <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="text-lg font-bold text-slate-900">Participantes aprobados</h2>
              <p class="text-sm text-slate-500">
                El selector usa únicamente inscripciones aprobadas con sus integrantes registrados.
              </p>
            </div>
            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
              {{ filteredManualParticipants.length }} disponibles
            </span>
          </div>

          <div
            v-if="!filteredManualParticipants.length"
            class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
          >
            No hay integrantes que coincidan con la categoría o la búsqueda actual.
          </div>

          <div v-else class="mt-4 grid gap-3 md:grid-cols-2">
            <button
              v-for="item in filteredManualParticipants"
              :key="item.integrante_id"
              type="button"
              class="rounded-2xl border p-4 text-left transition"
              :class="Number(selectedManualParticipantId) === Number(item.integrante_id)
                ? 'border-blue-500 bg-blue-50 shadow-sm'
                : 'border-slate-200 bg-white hover:border-blue-200 hover:bg-slate-50'"
              @click="selectedManualParticipantId = item.integrante_id"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate text-base font-bold text-slate-900">{{ item.participante }}</p>
                  <p class="mt-1 text-sm font-semibold text-blue-700">{{ item.equipo }}</p>
                </div>
                <span
                  v-if="item.es_capitan"
                  class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-700"
                >
                  Capitán
                </span>
              </div>
              <p class="mt-2 text-sm text-slate-600">{{ item.categoria }}</p>
              <p class="mt-1 text-sm text-slate-500">{{ item.prototipo }}</p>
              <p class="mt-1 text-sm text-slate-500">{{ item.institucion || "Sin institución" }}</p>
            </button>
          </div>
        </div>
      </div>
    </section>

    <Teleport to="body">
      <div v-if="deleteModal.open" class="fixed inset-0 z-[99999] flex min-h-screen items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/50" @click="closeDeleteModal"></div>

        <div class="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50">
          <TrashIcon class="h-7 w-7 text-red-600" />
        </div>
        <h3 class="mt-4 text-center text-xl font-bold text-slate-900">Eliminar plantilla</h3>
        <p class="mt-2 text-center text-sm text-slate-600">
          Esta acción eliminará la plantilla
          <span class="font-semibold text-slate-900">{{ deleteModal.plantilla?.tipo_label }}</span>
          de la base de datos. ¿Deseas continuar?
        </p>

        <div class="mt-6 grid grid-cols-1 gap-2 sm:grid-cols-2 sm:gap-3">
          <button
            type="button"
            class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            @click="closeDeleteModal"
          >
            No, cancelar
          </button>
          <button
            type="button"
            class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700"
            @click="deletePlantilla"
          >
            Sí, eliminar
          </button>
        </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
