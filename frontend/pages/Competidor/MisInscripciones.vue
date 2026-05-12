<script setup>
import { ref, computed, watch } from "vue";
import { useForm, router } from "@inertiajs/vue3";
import CompetidorLayout from "@/layouts/CompetidorLayout.vue";
import SeccionCategorias from "@/components/Categorias/SeccionCategorias.vue";
import ModalDetalleCategoria from "@/components/Categorias/ModalDetalleCategoria.vue";
import ModalFormularioInscripcion from "@/components/Categorias/ModalFormularioInscripcion.vue";
import ModalInscripcionExitosa from "@/components/Categorias/ModalInscripcionExitosa.vue";

import {
  CheckCircleIcon,
  CalendarDaysIcon,
  UserGroupIcon,
  DocumentTextIcon,
  ClockIcon,
  ExclamationTriangleIcon,
  XMarkIcon,
  ArrowUpTrayIcon,
  DocumentIcon,
  XCircleIcon,
} from "@heroicons/vue/24/outline";

defineOptions({ layout: CompetidorLayout });

const props = defineProps({
  categoriasDisponibles: {
    type: Array,
    default: () => [],
  },
  competencias: {
    type: Array,
    default: () => [],
  },
  inscripcionesActivas: {
    type: Array,
    default: () => [],
  },
});

// =====================================================
// DATOS DESDE BACKEND
// =====================================================
const categoriasDisponibles = computed(() => props.categoriasDisponibles ?? []);
const competencias = computed(() => props.competencias ?? []);

// =====================================================
// FLUJO INSCRIPCIÓN A CATEGORÍA
// =====================================================
const selectedCategoria = ref(null);
const isDetalleCategoriaOpen = ref(false);
const isFormularioInscripcionOpen = ref(false);
const isInscripcionExitosaOpen = ref(false);

const inscripcionForm = useForm({
  competencia_id: null,
  categoria_id: null,
  institucion: "",
  nombre_equipo: "",
  nombre_capitan: "",
  nombre_prototipo: "",
  telefono_contacto: "",
  integrantes: [],
});

const comprobanteForm = useForm({
  inscripcion_id: null,
  inscripcion_ids: [],
  comprobante: null,
});

const abrirDetalleCategoria = (categoria) => {
  selectedCategoria.value = categoria;
  isDetalleCategoriaOpen.value = true;
};

const cerrarDetalleCategoria = () => {
  isDetalleCategoriaOpen.value = false;
};

const abrirFormularioInscripcion = (categoria) => {
  selectedCategoria.value = categoria;
  isDetalleCategoriaOpen.value = false;
  isFormularioInscripcionOpen.value = true;
};

const cerrarFormularioInscripcion = () => {
  isFormularioInscripcionOpen.value = false;
  inscripcionForm.clearErrors();
};

const abrirReglamentoCategoria = (categoria) => {
  const url = categoria?.reglamento_url;

  if (!url) {
    alert("Esta categoría aún no tiene reglamento disponible.");
    return;
  }

  window.open(url, "_blank");
};

const enviarFormularioInscripcion = (payload) => {
  if (!selectedCategoria.value) return;

  inscripcionForm.reset();
  inscripcionForm.clearErrors();

  inscripcionForm.competencia_id = selectedCategoria.value.competencia_id;
  inscripcionForm.categoria_id = selectedCategoria.value.id;
  inscripcionForm.institucion = payload.institucion;
  inscripcionForm.nombre_equipo = payload.equipo;
  inscripcionForm.nombre_capitan = payload.capitan;
  inscripcionForm.nombre_prototipo = payload.prototipo;
  inscripcionForm.telefono_contacto = payload.contacto;
  inscripcionForm.integrantes = String(payload.integrantes)
    .split(",")
    .map((item) => item.trim())
    .filter(Boolean);

  inscripcionForm.post("/competidor/inscripciones", {
    preserveScroll: true,
    onSuccess: () => {
      isFormularioInscripcionOpen.value = false;
      isInscripcionExitosaOpen.value = true;
    },
    onError: (errors) => {
      console.error("Errores de validación:", errors);

      const firstError = Object.values(errors)?.[0];
      if (firstError) {
        alert(firstError);
      } else {
        alert("No se pudo registrar la inscripción.");
      }
    },
  });
};

const cerrarInscripcionExitosa = () => {
  isInscripcionExitosaOpen.value = false;

  router.reload({
    only: ["inscripcionesActivas"],
    preserveScroll: true,
  });
};

const verCategoriaDisponible = (category) => {
  abrirDetalleCategoria(category);
};

// =====================================================
// INSCRIPCIONES ACTIVAS
// =====================================================
const inscripcionesActivas = computed(() => props.inscripcionesActivas ?? []);

const totalActivas = computed(() => inscripcionesActivas.value.length);
const selectedInscripcionIds = ref([]);

const hayPendientesPago = computed(() =>
  inscripcionesActivas.value.some(
    (item) =>
      item.estado === "pendiente_pago" || item.estado_comprobante === "rechazado"
  )
);

const selectedItems = computed(() =>
  inscripcionesActivas.value.filter((item) => selectedInscripcionIds.value.includes(item.id))
);

const selectedCount = computed(() => selectedItems.value.length);

const selectableItems = computed(() =>
  inscripcionesActivas.value.filter((item) => item.mostrarPago)
);

const totalSelectedPrice = computed(() =>
  selectedItems.value.reduce((sum, item) => sum + Number(item.costo_inscripcion ?? 0), 0)
);

const allSelected = computed(() =>
  selectableItems.value.length > 0 &&
  selectedInscripcionIds.value.length === selectableItems.value.length
);

const selectedUploadTarget = computed(
  () => selectedItems.value.find((item) => item.mostrarPago) ?? null
);

watch(
  selectableItems,
  (items) => {
    const selectableIds = items.map((item) => item.id);
    selectedInscripcionIds.value = selectedInscripcionIds.value.filter((id) =>
      selectableIds.includes(id)
    );
  },
  { immediate: true }
);

const getCardClasses = (estado, estadoComprobante) => {
  if (estadoComprobante === "rechazado") {
    return "border-red-300 bg-red-50";
  }

  switch (estado) {
    case "confirmado":
      return "border-emerald-300 bg-emerald-50";
    case "pendiente_pago":
      return "border-amber-300 bg-amber-50";
    case "revision":
      return "border-blue-300 bg-blue-50";
    default:
      return "border-slate-200 bg-white";
  }
};

const getBadgeClasses = (estado, estadoComprobante) => {
  if (estadoComprobante === "rechazado") {
    return "bg-red-100 text-red-700";
  }

  switch (estado) {
    case "confirmado":
      return "bg-emerald-100 text-emerald-700";
    case "pendiente_pago":
      return "bg-amber-100 text-amber-700";
    case "revision":
      return "bg-blue-100 text-blue-700";
    default:
      return "bg-slate-100 text-slate-700";
  }
};

const getBadgeLabel = (item) => {
  if (item.estado_comprobante === "rechazado") {
    return "Comprobante Rechazado";
  }

  return item.estadoLabel;
};

const getBadgeIcon = (item) => {
  if (item.estado_comprobante === "rechazado") {
    return XCircleIcon;
  }

  return ClockIcon;
};

const formatPrice = (value) => {
  const amount = Number(value ?? 0);
  if (!Number.isFinite(amount) || amount <= 0) return "0.00";
  return `$ ${amount.toFixed(2)}`;
};

const toggleSelection = (item) => {
  if (!item.mostrarPago) return;

  const exists = selectedInscripcionIds.value.includes(item.id);
  if (exists) {
    selectedInscripcionIds.value = selectedInscripcionIds.value.filter((id) => id !== item.id);
    return;
  }

  selectedInscripcionIds.value = [...selectedInscripcionIds.value, item.id];
};

const toggleSelectAll = () => {
  if (!selectableItems.value.length) {
    selectedInscripcionIds.value = [];
    return;
  }

  if (allSelected.value) {
    selectedInscripcionIds.value = [];
    return;
  }

  selectedInscripcionIds.value = selectableItems.value.map((item) => item.id);
};

const openSelectedUpload = () => {
  const uploadableItems = selectedItems.value.filter((item) => item.mostrarPago);
  if (!uploadableItems.length) return;
  abrirModalComprobante(uploadableItems);
};

const getMensajeEstado = (item) => {
  if (item.estado_comprobante === "rechazado") {
    return (
      item.observacion_rechazo ||
      item.motivo_rechazo ||
      "Tu comprobante fue rechazado. Por favor, sube uno nuevo."
    );
  }

  if (item.estado === "revision") {
    return "Tu comprobante está en revisión por el administrador.";
  }

  if (item.estado === "pendiente_pago") {
    return "Tu inscripción está pendiente. Sube el comprobante para continuar.";
  }

  if (item.estado === "confirmado") {
    return "Tu inscripción ha sido confirmada correctamente.";
  }

  return "";
};

// =====================================================
// MODAL COMPROBANTE
// =====================================================
const isModalComprobanteOpen = ref(false);
const isComprobanteEnviadoOpen = ref(false);
const selectedInscripcion = ref(null);
const selectedComprobanteItems = ref([]);
const fileInputRef = ref(null);
const comprobanteFile = ref(null);
const comprobanteError = ref("");
const isSendingComprobante = ref(false);

const selectedComprobanteCount = computed(() => selectedComprobanteItems.value.length);
const selectedComprobanteTotal = computed(() =>
  selectedComprobanteItems.value.reduce(
    (sum, item) => sum + Number(item.costo_inscripcion ?? 0),
    0
  )
);

const fileName = computed(() => comprobanteFile.value?.name ?? "");
const fileSize = computed(() => {
  if (!comprobanteFile.value) return "";
  const sizeInKB = comprobanteFile.value.size / 1024;
  if (sizeInKB < 1024) return `${sizeInKB.toFixed(2)} KB`;
  return `${(sizeInKB / 1024).toFixed(2)} MB`;
});

const abrirModalComprobante = (items) => {
  const normalizedItems = (Array.isArray(items) ? items : [items]).filter(Boolean);

  if (!normalizedItems.length) return;

  selectedComprobanteItems.value = normalizedItems;
  selectedInscripcion.value = normalizedItems[0];
  comprobanteFile.value = null;
  comprobanteError.value = "";
  isSendingComprobante.value = false;

  comprobanteForm.reset();
  comprobanteForm.clearErrors();
  comprobanteForm.inscripcion_id = normalizedItems[0].id;
  comprobanteForm.inscripcion_ids = normalizedItems.map((item) => item.id);
  comprobanteForm.comprobante = null;

  isModalComprobanteOpen.value = true;
};

const cerrarModalComprobante = () => {
  isModalComprobanteOpen.value = false;
  selectedComprobanteItems.value = [];
  selectedInscripcion.value = null;
  comprobanteError.value = "";
  comprobanteFile.value = null;
  isSendingComprobante.value = false;

  comprobanteForm.reset();
  comprobanteForm.clearErrors();

  if (fileInputRef.value) {
    fileInputRef.value.value = "";
  }
};

const abrirSelectorArchivo = () => {
  fileInputRef.value?.click();
};

const validarArchivo = (file) => {
  if (!file) return "No se seleccionó ningún archivo.";

  const allowedTypes = ["image/jpeg", "image/png", "application/pdf"];
  const maxSize = 5 * 1024 * 1024;

  if (!allowedTypes.includes(file.type)) {
    return "Formato no permitido. Usa JPG, PNG o PDF.";
  }

  if (file.size > maxSize) {
    return "El archivo supera el tamaño máximo de 5MB.";
  }

  return "";
};

const onFileChange = (event) => {
  const file = event.target.files?.[0] ?? null;
  comprobanteError.value = "";

  if (!file) {
    comprobanteFile.value = null;
    comprobanteForm.comprobante = null;
    return;
  }

  const error = validarArchivo(file);

  if (error) {
    comprobanteFile.value = null;
    comprobanteForm.comprobante = null;
    comprobanteError.value = error;

    if (fileInputRef.value) {
      fileInputRef.value.value = "";
    }
    return;
  }

  comprobanteFile.value = file;
  comprobanteForm.comprobante = file;
};

const onDropFile = (event) => {
  const file = event.dataTransfer?.files?.[0] ?? null;
  comprobanteError.value = "";

  if (!file) return;

  const error = validarArchivo(file);

  if (error) {
    comprobanteFile.value = null;
    comprobanteForm.comprobante = null;
    comprobanteError.value = error;
    return;
  }

  comprobanteFile.value = file;
  comprobanteForm.comprobante = file;
};

const removerArchivo = () => {
  comprobanteFile.value = null;
  comprobanteForm.comprobante = null;
  comprobanteError.value = "";

  if (fileInputRef.value) {
    fileInputRef.value.value = "";
  }
};

const enviarComprobante = () => {
  if (!comprobanteFile.value || !selectedComprobanteItems.value.length) return;

  isSendingComprobante.value = true;
  comprobanteError.value = "";

  comprobanteForm.inscripcion_id = selectedComprobanteItems.value[0].id;
  comprobanteForm.inscripcion_ids = selectedComprobanteItems.value.map((item) => item.id);
  comprobanteForm.comprobante = comprobanteFile.value;

  comprobanteForm.post("/competidor/inscripciones/comprobante", {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      isSendingComprobante.value = false;
      isModalComprobanteOpen.value = false;
      isComprobanteEnviadoOpen.value = true;
      selectedInscripcionIds.value = [];
      selectedComprobanteItems.value = [];
      selectedInscripcion.value = null;

      comprobanteFile.value = null;
      comprobanteError.value = "";

      if (fileInputRef.value) {
        fileInputRef.value.value = "";
      }
    },
    onError: (errors) => {
      isSendingComprobante.value = false;

      const firstError = Object.values(errors)?.[0];
      comprobanteError.value = firstError || "No se pudo subir el comprobante.";
    },
  });
};

const cerrarComprobanteEnviado = () => {
  isComprobanteEnviadoOpen.value = false;
  selectedInscripcionIds.value = [];

  router.reload({
    only: ["inscripcionesActivas"],
    preserveScroll: true,
  });
};

const subirComprobante = (item) => {
  abrirModalComprobante(item);
};
</script>

<template>
  <div class="w-full">
    <div class="mx-auto w-full max-w-[1180px] px-4 sm:px-6 lg:px-4 py-6 space-y-6">
      <section>
        <h1 class="text-2xl font-bold text-slate-900">Mis Inscripciones</h1>
        <p class="mt-1 text-sm text-slate-500">
          Gestiona tus inscripciones actuales e inscríbete en nuevas categorías
        </p>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
        <div class="flex items-start gap-4">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50">
            <CheckCircleIcon class="h-6 w-6 text-emerald-600" />
          </div>

          <div>
            <h2 class="text-lg font-semibold text-slate-900">
              Mis Inscripciones Activas
            </h2>
            <p class="mt-1 text-sm text-slate-500">
              {{ totalActivas }} inscripciones activas
            </p>
          </div>
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
          <div
            class="grid grid-cols-[42px_2fr_1.1fr_1.2fr_1.1fr_0.9fr] items-center gap-4 border-b border-slate-200 bg-slate-50 px-5 py-4 text-[11px] font-semibold uppercase tracking-wide text-slate-500"
          >
            <button
              type="button"
              class="flex h-7 w-7 items-center justify-center rounded-md border-2 transition"
              :class="
                selectableItems.length
                  ? allSelected
                    ? 'border-blue-600 bg-blue-600'
                    : 'border-slate-400 bg-white hover:border-blue-500'
                  : 'cursor-not-allowed border-slate-200 bg-slate-100'
              "
              :disabled="!selectableItems.length"
              @click="toggleSelectAll"
            >
              <CheckCircleIcon v-if="allSelected" class="h-4 w-4 text-white" />
            </button>
            <div>CATEGORÍA / EQUIPO</div>
            <div>INTEGRANTES</div>
            <div>PROTOTIPO</div>
            <div>ESTADO</div>
            <div class="text-right">PRECIO</div>
          </div>

          <div class="divide-y divide-slate-200">
            <article
              v-for="item in inscripcionesActivas"
              :key="`table-${item.id}`"
              class="grid grid-cols-[42px_2fr_1.1fr_1.2fr_1.1fr_0.9fr] items-center gap-4 px-5 py-5 transition"
              :class="getCardClasses(item.estado, item.estado_comprobante)"
            >
              <button
                type="button"
                class="flex h-7 w-7 items-center justify-center rounded-md border-2 transition"
                :class="
                  !item.mostrarPago
                    ? 'cursor-not-allowed border-slate-200 bg-slate-100'
                    : selectedInscripcionIds.includes(item.id)
                    ? 'border-blue-600 bg-blue-600'
                    : 'border-slate-400 bg-white hover:border-blue-500'
                "
                :disabled="!item.mostrarPago"
                @click="toggleSelection(item)"
              >
                <CheckCircleIcon
                  v-if="selectedInscripcionIds.includes(item.id)"
                  class="h-4 w-4 text-white"
                />
              </button>

              <div class="min-w-0">
                <h3 class="text-xl font-bold leading-tight text-slate-900">
                  {{ item.categoria }}
                </h3>
                <p class="mt-2 text-sm text-slate-700">
                  <span class="font-medium">Equipo:</span> {{ item.equipo }}
                </p>
                <p class="mt-1 text-sm text-slate-500">
                  Inscrito el {{ item.fechaInscripcion }}
                </p>
              </div>

              <div class="min-w-0">
                <div v-if="item.integrantes_nombres?.length" class="space-y-1">
                  <p
                    v-for="integrante in item.integrantes_nombres"
                    :key="`${item.id}-${integrante.nombre}-${integrante.es_capitan}`"
                    class="truncate text-sm text-slate-700"
                    :class="integrante.es_capitan ? 'font-semibold text-slate-900' : ''"
                  >
                    {{ integrante.es_capitan ? `${integrante.nombre} (capitán)` : integrante.nombre }}
                  </p>
                </div>
                <p v-else class="text-sm text-slate-500">Sin integrantes</p>
              </div>

              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-slate-700">
                  {{ item.prototipo || "Sin prototipo" }}
                </p>
              </div>

              <div>
                <span
                  class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium whitespace-nowrap"
                  :class="getBadgeClasses(item.estado, item.estado_comprobante)"
                >
                  <component :is="getBadgeIcon(item)" class="mr-1 h-4 w-4" />
                  {{ getBadgeLabel(item) }}
                </span>
              </div>

              <div class="text-right">
                <p class="text-3xl font-bold tracking-tight text-slate-900">
                  {{ formatPrice(item.costo_inscripcion) }}
                </p>
              </div>
            </article>
          </div>
        </div>

        <div
          class="mt-6 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white px-5 py-5 shadow-sm sm:flex-row sm:items-center sm:justify-between"
        >
          <p class="text-sm text-slate-600">
            {{ selectedCount }} inscripci{{ selectedCount === 1 ? "ón seleccionada" : "ones seleccionadas" }}
          </p>

          <div class="flex flex-col items-start gap-3 sm:items-end">
            <div class="text-left sm:text-right">
              <p class="text-sm text-slate-500">Total a pagar</p>
              <p class="text-4xl font-bold tracking-tight text-slate-900">
                {{ formatPrice(totalSelectedPrice) }}
              </p>
            </div>

            <button
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-medium text-white shadow-md transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-300"
              :disabled="!selectedUploadTarget"
              @click="openSelectedUpload"
            >
              <ArrowUpTrayIcon class="h-4 w-4" />
              Subir Comprobante de Pago
            </button>
          </div>
        </div>

        <div v-if="false" class="mt-6 overflow-hidden rounded-2xl border border-slate-200">
          <div
            class="hidden grid-cols-[2.1fr_1.6fr_1.2fr_1.2fr_0.9fr_1.4fr] items-center gap-4 bg-slate-50 px-5 py-4 text-xs font-semibold uppercase tracking-wide text-slate-500 lg:grid"
          >
            <div>Categoría / Equipo</div>
            <div>Competencia</div>
            <div>Prototipo</div>
            <div>Estado</div>
            <div class="text-right">Precio</div>
            <div class="text-right">Acción</div>
          </div>

          <div class="divide-y divide-slate-200">
            <article
              v-for="item in inscripcionesActivas"
              :key="item.id"
              class="transition"
              :class="getCardClasses(item.estado, item.estado_comprobante)"
            >
              <div
                class="grid gap-5 px-5 py-5 lg:grid-cols-[2.1fr_1.6fr_1.2fr_1.2fr_0.9fr_1.4fr] lg:items-center"
              >
                <div class="min-w-0">
                  <div class="flex items-start gap-3">
                    <span
                      class="mt-1 inline-flex h-5 w-5 shrink-0 rounded border-2"
                      :class="
                        item.estado === 'confirmado'
                          ? 'border-emerald-500 bg-emerald-500'
                          : item.estado_comprobante === 'rechazado'
                          ? 'border-red-500 bg-red-500'
                          : item.estado === 'revision'
                          ? 'border-blue-500 bg-blue-500'
                          : 'border-amber-500 bg-amber-500'
                      "
                    ></span>

                    <div class="min-w-0">
                      <h3 class="text-xl font-bold leading-tight text-slate-900">
                        {{ item.categoria }}
                      </h3>
                      <p class="mt-2 text-sm text-slate-700">
                        <span class="font-semibold">Equipo:</span> {{ item.equipo }}
                      </p>
                      <p class="mt-1 text-sm text-slate-500">
                        Inscrito el {{ item.fechaInscripcion }}
                      </p>
                    </div>
                  </div>
                </div>

                <div class="min-w-0">
                  <div class="flex items-start gap-3">
                    <CalendarDaysIcon class="mt-0.5 h-5 w-5 shrink-0 text-slate-400" />
                    <div class="min-w-0">
                      <p class="text-sm text-slate-500">Competencia</p>
                      <p class="truncate text-base font-semibold text-slate-900">
                        {{ item.competencia }}
                      </p>
                      <div class="mt-2 flex items-center gap-2 text-slate-500">
                        <ClockIcon class="h-4 w-4" />
                        <span class="text-sm">{{ item.fechaCompetencia }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="min-w-0">
                  <div class="flex items-start gap-3">
                    <DocumentTextIcon class="mt-0.5 h-5 w-5 shrink-0 text-slate-400" />
                    <div class="min-w-0">
                      <p class="text-sm text-slate-500">Prototipo</p>
                      <p class="truncate text-base font-semibold text-slate-900">
                        {{ item.prototipo || "Sin prototipo" }}
                      </p>
                      <div class="mt-2 flex items-center gap-2 text-slate-500">
                        <UserGroupIcon class="h-4 w-4" />
                        <span class="text-sm">{{ item.integrantes }} integrantes</span>
                      </div>
                    </div>
                  </div>
                </div>

                <div>
                  <span
                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium whitespace-nowrap"
                    :class="getBadgeClasses(item.estado, item.estado_comprobante)"
                  >
                    <component :is="getBadgeIcon(item)" class="mr-1 h-4 w-4" />
                    {{ getBadgeLabel(item) }}
                  </span>
                </div>

                <div class="lg:text-right">
                  <p class="text-sm text-slate-500 lg:hidden">Precio</p>
                  <p class="text-3xl font-bold tracking-tight text-slate-900">
                    {{ formatPrice(item.costo_inscripcion) }}
                  </p>
                </div>

                <div class="lg:flex lg:justify-end">
                  <button
                    v-if="item.mostrarPago"
                    type="button"
                    class="w-full rounded-xl px-4 py-2.5 text-sm font-medium text-white transition lg:w-auto lg:min-w-[220px]"
                    :class="
                      item.estado_comprobante === 'rechazado'
                        ? 'bg-red-600 hover:bg-red-700'
                        : 'bg-blue-600 hover:bg-blue-700'
                    "
                    @click="subirComprobante(item)"
                  >
                    {{
                      item.estado_comprobante === "rechazado"
                        ? "Volver a Subir Comprobante"
                        : "Subir Comprobante de Pago"
                    }}
                  </button>
                </div>
              </div>

              <div
                v-if="getMensajeEstado(item)"
                class="border-t border-black/5 px-5 pb-5"
              >
                <div
                  class="rounded-2xl border px-4 py-4"
                  :class="
                    item.estado_comprobante === 'rechazado'
                      ? 'border-red-200 bg-red-50'
                      : item.estado === 'confirmado'
                      ? 'border-emerald-200 bg-emerald-50'
                      : item.estado === 'revision'
                      ? 'border-blue-200 bg-blue-50'
                      : 'border-amber-200 bg-amber-50'
                  "
                >
                  <div
                    v-if="item.estado_comprobante === 'rechazado'"
                    class="flex items-start gap-3"
                  >
                    <XCircleIcon class="mt-0.5 h-5 w-5 text-red-600" />
                    <div>
                      <h4 class="text-sm font-semibold text-red-800">
                        Comprobante rechazado
                      </h4>

                      <p
                        v-if="item.motivo_rechazo"
                        class="mt-1 text-sm text-red-700"
                      >
                        <span class="font-semibold">Motivo:</span>
                        {{ item.motivo_rechazo }}
                      </p>

                      <p
                        v-if="item.observacion_rechazo"
                        class="mt-1 text-sm text-red-700"
                      >
                        {{ item.observacion_rechazo }}
                      </p>

                      <p
                        v-if="!item.motivo_rechazo && !item.observacion_rechazo"
                        class="mt-1 text-sm text-red-700"
                      >
                        Tu comprobante fue rechazado. Por favor, sube uno nuevo.
                      </p>
                    </div>
                  </div>

                  <p
                    v-else
                    class="text-sm"
                    :class="
                      item.estado === 'confirmado'
                        ? 'text-emerald-700'
                        : item.estado === 'revision'
                        ? 'text-blue-700'
                        : 'text-amber-700'
                    "
                  >
                    {{ getMensajeEstado(item) }}
                  </p>
                </div>
              </div>
            </article>
          </div>
        </div>

        <div
          v-if="hayPendientesPago"
          class="mt-6 rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4"
        >
          <div class="flex items-start gap-3">
            <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 text-amber-600" />
            <div>
              <h4 class="text-sm font-semibold text-amber-800">
                Completa tu inscripción
              </h4>
              <p class="mt-1 text-sm text-amber-700">
                Tienes inscripciones pendientes de pago o comprobantes rechazados.
                Sube tu comprobante para continuar con la validación.
              </p>
            </div>
          </div>
        </div>
      </section>

      <SeccionCategorias
        title="Categorías Disponibles"
        subtitle="Explora e inscríbete en nuevas categorías"
        :categories="categoriasDisponibles"
        button-text="Ver"
        @action="verCategoriaDisponible"
      />

      <ModalDetalleCategoria
        :open="isDetalleCategoriaOpen"
        :categoria="selectedCategoria"
        @close="cerrarDetalleCategoria"
        @open-form="abrirFormularioInscripcion"
        @open-reglamento="abrirReglamentoCategoria"
      />

      <ModalFormularioInscripcion
        :open="isFormularioInscripcionOpen"
        :categoria="selectedCategoria"
        @close="cerrarFormularioInscripcion"
        @submitted="enviarFormularioInscripcion"
      />

      <ModalInscripcionExitosa
        :open="isInscripcionExitosaOpen"
        title="¡Inscripción Exitosa!"
        message="Tu inscripción ha sido registrada correctamente. Recibirás un correo de confirmación pronto."
        @close="cerrarInscripcionExitosa"
      />
    </div>

    <!-- MODAL SUBIR COMPROBANTE -->
    <Teleport to="body">
      <div
        v-if="isModalComprobanteOpen"
        class="fixed inset-0 z-[10040] bg-black/60 px-4 py-6 overflow-y-auto"
      >
        <div class="min-h-full w-full flex items-start sm:items-center justify-center">
          <!-- Modal -->
          <div class="w-full max-w-[640px] overflow-hidden rounded-2xl bg-white shadow-2xl">
            <!-- Header -->
            <div class="relative bg-blue-600 px-5 py-4 text-white">
            <button
              type="button"
              class="absolute right-4 top-4 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/20 text-white transition hover:bg-white/30"
              @click="cerrarModalComprobante"
            >
              <XMarkIcon class="h-5 w-5" />
            </button>

            <h3 class="text-2xl font-bold">
              {{
                selectedInscripcion?.estado_comprobante === "rechazado"
                  ? "Volver a Subir Comprobante"
                  : "Subir Comprobante de Pago"
              }}
            </h3>
            <p class="mt-1 text-sm text-blue-100">
              {{ selectedComprobanteCount }} inscripci{{ selectedComprobanteCount === 1 ? "ón seleccionada" : "ones seleccionadas" }}
            </p>
          </div>

          <div class="space-y-5 p-5">
            <div class="rounded-xl border border-blue-200 bg-blue-50/60 p-4">
              <h4 class="text-lg font-semibold text-slate-900">
                Detalle de Inscripciones
              </h4>

              <div class="mt-4 space-y-3">
                <div
                  v-for="item in selectedComprobanteItems"
                  :key="`modal-${item.id}`"
                  class="flex items-center justify-between gap-4 rounded-xl bg-white px-4 py-3"
                >
                  <div class="min-w-0">
                    <p class="truncate text-base font-semibold text-slate-900">
                      {{ item.categoria }}
                    </p>
                    <p class="mt-0.5 truncate text-sm text-slate-600">
                      {{ item.equipo }}
                    </p>
                  </div>

                  <p class="shrink-0 text-lg font-bold text-slate-900">
                    {{ formatPrice(item.costo_inscripcion) }}
                  </p>
                </div>
              </div>

              <div class="mt-4 border-t border-blue-200 pt-4">
                <div class="flex items-center justify-between gap-4">
                  <p class="text-xl font-semibold text-slate-900">Total a Pagar:</p>
                  <p class="text-4xl font-bold tracking-tight text-blue-600">
                    {{ formatPrice(selectedComprobanteTotal) }}
                  </p>
                </div>
              </div>
            </div>

            <div v-if="false" class="rounded-xl border border-blue-200 bg-blue-50/60 p-4">
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <p class="text-slate-500">Categoría:</p>
                  <p class="font-semibold text-slate-900">
                    {{ selectedInscripcion?.categoria }}
                  </p>
                </div>

                <div>
                  <p class="text-slate-500">Precio:</p>
                  <p class="font-semibold text-slate-900">
                    {{ formatPrice(selectedInscripcion?.costo_inscripcion) }}
                  </p>
                </div>

                <div>
                  <p class="text-slate-500">Equipo:</p>
                  <p class="font-semibold text-slate-900">
                    {{ selectedInscripcion?.equipo }}
                  </p>
                </div>

                <div>
                  <p class="text-slate-500">Competencia:</p>
                  <p class="font-semibold text-slate-900">
                    {{ selectedInscripcion?.competencia }}
                  </p>
                </div>
              </div>
            </div>

            <div
              v-if="selectedComprobanteCount === 1 && selectedInscripcion?.estado_comprobante === 'rechazado' && (selectedInscripcion?.motivo_rechazo || selectedInscripcion?.observacion_rechazo)"
              class="rounded-xl border border-red-200 bg-red-50 px-4 py-4"
            >
              <h4 class="text-sm font-semibold text-red-800">
                Motivo del rechazo anterior
              </h4>

              <p
                v-if="selectedInscripcion?.motivo_rechazo"
                class="mt-2 text-sm text-red-700"
              >
                <span class="font-semibold">Motivo:</span>
                {{ selectedInscripcion?.motivo_rechazo }}
              </p>

              <p
                v-if="selectedInscripcion?.observacion_rechazo"
                class="mt-1 text-sm text-red-700"
              >
                {{ selectedInscripcion?.observacion_rechazo }}
              </p>
            </div>

            <input
              ref="fileInputRef"
              type="file"
              class="hidden"
              accept=".jpg,.jpeg,.png,.pdf"
              @change="onFileChange"
            />

            <div
              v-if="!comprobanteFile"
              class="cursor-pointer rounded-2xl border border-dashed border-slate-300 px-6 py-8 text-center transition hover:border-blue-400 hover:bg-slate-50"
              @click="abrirSelectorArchivo"
              @dragover.prevent
              @drop.prevent="onDropFile"
            >
              <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-blue-100">
                <ArrowUpTrayIcon class="h-7 w-7 text-blue-600" />
              </div>

              <p class="mt-4 text-lg font-semibold text-slate-900">
                Arrastra tu archivo aquí
              </p>
              <p class="mt-2 text-sm text-slate-600">
                o haz clic para seleccionar
              </p>
              <p class="mt-3 text-xs text-slate-500">
                JPG, PNG o PDF (max. 5MB)
              </p>
            </div>

            <div
              v-else
              class="flex items-center justify-between rounded-2xl border border-dashed border-slate-300 px-5 py-6"
            >
              <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100">
                  <DocumentIcon class="h-5 w-5 text-blue-600" />
                </div>

                <div>
                  <p class="break-all text-sm font-semibold text-slate-900">
                    {{ fileName }}
                  </p>
                  <p class="text-xs text-slate-500">
                    {{ fileSize }}
                  </p>
                </div>
              </div>

              <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                @click="removerArchivo"
              >
                <XMarkIcon class="h-5 w-5" />
              </button>
            </div>

            <p v-if="comprobanteError" class="text-sm font-medium text-red-600">
              {{ comprobanteError }}
            </p>

            <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-4">
              <div class="flex items-start gap-3">
                <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 text-amber-600" />
                <div>
                  <h4 class="text-sm font-semibold text-amber-800">
                    Información importante
                  </h4>
                  <ul class="mt-2 space-y-1 text-sm text-amber-700">
                    <li>• Asegúrate de que el comprobante sea legible</li>
                    <li>• Verifica que incluya la fecha y monto del pago</li>
                    <li>• El proceso de verificación puede tomar hasta 24 horas</li>
                  </ul>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3 pt-1">
              <button
                type="button"
                class="rounded-xl bg-slate-100 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-200"
                @click="cerrarModalComprobante"
              >
                Cancelar
              </button>

              <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-medium text-white transition"
                :class="
                  comprobanteFile && !isSendingComprobante
                    ? 'bg-blue-600 hover:bg-blue-700'
                    : 'cursor-not-allowed bg-blue-300'
                "
                :disabled="!comprobanteFile || isSendingComprobante"
                @click="enviarComprobante"
              >
                <ArrowUpTrayIcon class="h-4 w-4" />
                {{ isSendingComprobante ? "Enviando..." : "Enviar Comprobante" }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
    </Teleport>

    <!-- MODAL ÉXITO COMPROBANTE -->
    <Teleport to="body">
      <div
        v-if="isComprobanteEnviadoOpen"
        class="fixed inset-0 z-[10050] flex items-center justify-center bg-black/70 p-4"
        @click="cerrarComprobanteEnviado"
      >
        <div
          class="w-full max-w-[340px] rounded-2xl bg-white px-6 py-8 text-center shadow-2xl"
          @click.stop
        >
          <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100">
            <CheckCircleIcon class="h-8 w-8 text-emerald-600" />
          </div>

          <h3 class="mt-4 text-2xl font-bold text-slate-900">
            ¡Comprobante Enviado!
          </h3>
          <p class="mt-3 text-sm leading-6 text-slate-600">
            Tu comprobante está en revisión. Te notificaremos cuando sea aprobado.
          </p>

          <button
            type="button"
            class="mt-6 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800"
            @click="cerrarComprobanteEnviado"
          >
            Entendido
          </button>
        </div>
      </div>
    </Teleport>
  </div>
</template>
