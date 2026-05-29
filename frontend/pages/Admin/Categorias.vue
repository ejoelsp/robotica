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
  tipo: "libre",
  orden: "",
  cantidad_intentos: 1,
  intentos_consecutivos: false,
  clasifican_cantidad: "",
  criterio_clasificacion: "mayor_puntaje",
  es_final: false,
  estado: "borrador",
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
  max_integrantes: 2,
  estado: true,
  mecanismo_calificacion_id: "",
  unidad_resultado: "",
  orden_ranking: "desc",
  requiere_aprobacion_admin: true,
  visible_publico_en_vivo: true,
  permite_edicion_juez: true,
  pdf: null,
  imagen: null,
});
const touchedNombre = ref(false);
const nombrePattern = /^[\p{L}\p{N}\s]+$/u;

const nombreFieldError = computed(() => {
  const nombre = String(form.nombre ?? "").trim();
  if (!nombre) return "El nombre es obligatorio.";
  if (!nombrePattern.test(nombre)) return "El nombre solo puede contener letras, numeros y espacios.";
  return "";
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
  if (errors.max_integrantes) return (showToast(errors.max_integrantes, "warning", 4500), true);
  if (errors.estado) return (showToast(errors.estado, "warning", 4500), true);
  if (errors.mecanismo_calificacion_id) return (showToast(errors.mecanismo_calificacion_id, "warning", 4500), true);
  if (errors.unidad_resultado) return (showToast(errors.unidad_resultado, "warning", 4500), true);
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
  const list = !term
    ? categories.value
    : categories.value.filter((c) => (c.nombre ?? "").toLowerCase().includes(term));

  return [...list].sort((a, b) =>
    String(a?.nombre ?? "").localeCompare(String(b?.nombre ?? ""), "es", { sensitivity: "base" })
  );
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
  const maxIntegrantes = Number(form.max_integrantes ?? 2);
  const hasIntegrantesValido = Number.isInteger(maxIntegrantes) && maxIntegrantes >= 1 && maxIntegrantes <= 5;

  if (!hasCompetencia || !hasNombre || !hasCostoValido || !hasIntegrantesValido) return false;

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
  form.visible_publico_en_vivo = form.visible_publico_en_vivo ?? true;
  form.permite_edicion_juez = true;
}

function applyMechanismDefaults(code) {
  const defaults = mechanismDefaults[code];
  if (!defaults) return;

  form.unidad_resultado = defaults.unidad;
  form.orden_ranking = defaults.orden;
}

function officialMechanismIdForCategory(cat) {
  return cat?.config_calificacion?.mecanismo_calificacion_id ?? defaultMechanismId();
}

watch(
  () => form.mecanismo_calificacion_id,
  () => {
    if (isHydratingForm.value) return;
    applyMechanismDefaults(selectedMechanism.value?.codigo);
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
const liveResultsEnabled = (cat) => cat?.config_calificacion?.visible_publico_en_vivo ?? true;
const liveResultsLabel = (cat) => (liveResultsEnabled(cat) ? "Activa" : "Inactiva");
const liveResultsBadge = (cat) => liveResultsEnabled(cat)
  ? "bg-blue-50 text-blue-700 ring-blue-200"
  : "bg-slate-100 text-slate-600 ring-slate-200";

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
  touchedNombre.value = false;
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
  form.max_integrantes = 2;
  form.estado = true;
  form.visible_publico_en_vivo = true;
  form.mecanismo_calificacion_id = defaultMechanismId();
  ensureCategoryEvaluationDefaults();
  form.pdf = null;
  form.imagen = null;

  if (pdfInput.value) pdfInput.value.value = null;
  if (imageInput.value) imageInput.value.value = null;
  touchedNombre.value = false;
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
  form.max_integrantes = Number(cat?.max_integrantes ?? 2);
  form.estado = !!cat?.estado;
  form.mecanismo_calificacion_id = officialMechanismIdForCategory(cat);
  ensureCategoryEvaluationDefaults();
  form.visible_publico_en_vivo = cat?.config_calificacion?.visible_publico_en_vivo ?? true;
  form.pdf = null;
  form.imagen = null;

  if (pdfInput.value) pdfInput.value.value = null;
  if (imageInput.value) imageInput.value.value = null;
  touchedNombre.value = false;
  isHydratingForm.value = false;
}

function sanitizeNombreCategoria() {
  form.nombre = String(form.nombre ?? "").replace(/[^\p{L}\p{N}\s]/gu, "");
  if (form.errors.nombre) form.clearErrors("nombre");
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
  touchedNombre.value = true;

  const compId = Number(form.competencia_id);
  if (!Number.isInteger(compId) || compId <= 0) {
    showToast("Selecciona una competencia válida.", "warning", 3500);
    return;
  }

  if (nombreFieldError.value) return;

  const costo = Number(form.costo_inscripcion ?? 0);
  if (!Number.isFinite(costo) || costo < 0 || costo > 999999.99) {
    showToast("Ingresa un costo de inscripción válido.", "warning", 3500);
    return;
  }

  const maxIntegrantes = Number(form.max_integrantes ?? 2);
  if (!Number.isInteger(maxIntegrantes) || maxIntegrantes < 1 || maxIntegrantes > 5) {
    showToast("Selecciona una cantidad de integrantes valida.", "warning", 3500);
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

  // CREATE
  if (!isEditing.value) {
    const targetCompetenciaId = Number(form.competencia_id);

    try {
      form.processing = true;
      form.clearErrors();

      const fd = new FormData();
      fd.append("competencia_id", String(form.competencia_id ?? ""));
      fd.append("nombre", String(form.nombre ?? ""));
      fd.append("costo_inscripcion", String(form.costo_inscripcion ?? 0));
      fd.append("max_integrantes", String(form.max_integrantes ?? 2));
      fd.append("estado", form.estado ? "1" : "0");
      fd.append("mecanismo_calificacion_id", Number(form.mecanismo_calificacion_id || 0) > 0
        ? String(form.mecanismo_calificacion_id)
        : "");
      fd.append("unidad_resultado", "");
      fd.append("orden_ranking", "desc");
      fd.append("requiere_aprobacion_admin", form.requiere_aprobacion_admin ? "1" : "0");
      fd.append("visible_publico_en_vivo", form.visible_publico_en_vivo ? "1" : "0");
      fd.append("permite_edicion_juez", form.permite_edicion_juez ? "1" : "0");
      if (form.pdf) fd.append("pdf", form.pdf);
      if (form.imagen) fd.append("imagen", form.imagen);

      await axios.post("/admin/categorias", fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });

      showToast("Categoría creada", "success", 3000);
      closeModal();

      router.get(
        "/admin/categorias",
        { competencia_id: targetCompetenciaId },
        {
          replace: true,
          preserveScroll: true,
          preserveState: false,
          only: ["competenciaId", "categorias", "competencias", "mecanismosCalificacion", "flash"],
        }
      );
    } catch (err) {
      if (err?.response?.status === 422 && err.response.data?.errors) {
        form.setError(err.response.data.errors);
        isModalOpen.value = true;
        showFormErrors();
        return;
      }

      console.error(err);
      showToast(err?.response?.data?.message || "No se pudo crear la categoría.", "error", 4500);
      isModalOpen.value = true;
    } finally {
      form.processing = false;
    }

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
    fd.append("max_integrantes", String(form.max_integrantes ?? 2));
    fd.append("estado", form.estado ? "1" : "0");
    fd.append("mecanismo_calificacion_id", String(form.mecanismo_calificacion_id || defaultMechanismId()));
    fd.append("unidad_resultado", "");
    fd.append("orden_ranking", "desc");
    fd.append("requiere_aprobacion_admin", "1");
    fd.append("visible_publico_en_vivo", form.visible_publico_en_vivo ? "1" : "0");
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

const normalizeEditableRondaCriterion = (criterio) => (
  ["mayor_puntaje", "menor_tiempo", "mayor_promedio"].includes(criterio)
    ? criterio
    : "mayor_puntaje"
);

const rondaCriterioLabel = (criterio) => ({
  mayor_puntaje: "mayor puntaje",
  menor_tiempo: "menor tiempo",
  mayor_promedio: "mayor promedio",
  ganador_enfrentamiento: "automático por enfrentamiento",
}[criterio] ?? "mayor puntaje");

const rondaCriterioOptions = [
  { value: "mayor_puntaje", label: "Mayor puntaje" },
  { value: "menor_tiempo", label: "Menor tiempo" },
  { value: "mayor_promedio", label: "Mayor promedio" },
];

function resetRondaForm() {
  rondaEditingId.value = null;
  rondaForm.value = {
    tipo: "libre",
    orden: "",
    cantidad_intentos: 1,
    intentos_consecutivos: false,
    clasifican_cantidad: "",
    criterio_clasificacion: "mayor_puntaje",
    es_final: false,
    estado: "borrador",
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
    tipo: ronda.tipo ?? "libre",
    orden: ronda.orden ?? "",
    cantidad_intentos: ronda.cantidad_intentos ?? 1,
    intentos_consecutivos: !!ronda.intentos_consecutivos,
    clasifican_cantidad: ronda.clasifican_cantidad ?? "",
    criterio_clasificacion: normalizeEditableRondaCriterion(ronda.criterio_clasificacion ?? "mayor_puntaje"),
    es_final: !!ronda.es_final,
    estado: ronda.estado ?? "borrador",
  };
}

async function saveRonda() {
  if (!rondasCategoria.value?.id) return;

  if (Number(rondaForm.value.cantidad_intentos || 0) < 1) {
    showToast("La cantidad de intentos debe ser al menos 1.", "warning", 3500);
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
      errors.tipo?.[0]
        || errors.orden?.[0]
        || errors.cantidad_intentos?.[0]
        || errors.intentos_consecutivos?.[0]
        || errors.clasifican_cantidad?.[0]
        || errors.criterio_clasificacion?.[0]
        || errors.estado?.[0]
        || "No se pudo guardar la ronda.",
      "error",
      4500
    );
  } finally {
    rondasSaving.value = false;
  }
}

async function clasificarRonda(ronda) {
  if (!rondasCategoria.value?.id) return;

  if (!ronda.clasifican_cantidad) {
    showToast("Configura cuántos participantes pasan antes de clasificar.", "warning", 4000);
    return;
  }

  openAlertModal({
    title: "Cerrar y clasificar",
    message: `Se cerrará "${ronda.nombre}" y se asignarán los mejores ${ronda.clasifican_cantidad} a la siguiente ronda configurada.`,
    confirmText: "Clasificar",
    cancelText: "Cancelar",
    variant: "warning",
    onConfirm: async () => {
      try {
        await axios.post(`/admin/categorias/${rondasCategoria.value.id}/rondas/${ronda.id}/clasificar`);
        showToast("Clasificación generada correctamente", "success", 3500);
        await loadRondas(rondasCategoria.value);
      } catch (error) {
        const errors = error?.response?.data?.errors ?? {};
        showToast(
          errors.ronda?.[0]
            || errors.clasifican_cantidad?.[0]
            || error?.response?.data?.message
            || "No se pudo clasificar la ronda.",
          "error",
          5000
        );
      }
    },
  });
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
    <div class="mx-auto w-full max-w-[1180px] px-3 py-5 space-y-5 sm:px-6 sm:py-6 sm:space-y-6 lg:px-4">
      <!-- Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Gestión de Categorías</h1>
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
      <div class="grid grid-cols-1 gap-3 md:grid-cols-3 md:gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
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

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
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

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
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

      <!-- Tabla -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
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
          <table class="min-w-[980px] w-full text-sm">
            <thead class="bg-white">
              <tr class="text-left text-black border-b border-slate-200">
                <th class="px-6 py-4 font-medium">Categoría</th>
                <th class="px-6 py-4 font-medium">Precio</th>
                <th class="px-6 py-4 font-medium">Integrantes</th>
                <th class="px-6 py-4 font-medium">Estado</th>
                <th class="px-6 py-4 font-medium">Reglamento (PDF)</th>
                <th class="px-6 py-4 font-medium">Imagen</th>
                <th class="px-6 py-4 font-medium">Resultados en vivo</th>
                <th class="px-6 py-4 font-medium">Acciones</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-200">
              <tr v-for="cat in filtered" :key="cat.id ?? cat.nombre" class="hover:bg-slate-50/60">
                <td class="px-6 py-4">
                  <p class="font-semibold text-slate-900 leading-tight">{{ cat.nombre }}</p>
                </td>

                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex min-w-[78px] items-center justify-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700 whitespace-nowrap">
                    {{ formatPrice(cat.costo_inscripcion) }}
                  </span>
                </td>

                <td class="px-6 py-4">
                  <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 ring-1 ring-blue-200">
                    {{ cat.max_integrantes ?? 2 }}
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
                  <span
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ring-1"
                    :class="liveResultsBadge(cat)"
                  >
                    {{ liveResultsLabel(cat) }}
                  </span>
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
                <td colspan="8" class="px-6 py-10 text-center text-slate-500">
                  No se encontraron categorías con ese criterio.
                </td>
              </tr>
            </tbody>
          </table>
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

              <div class="p-4 space-y-4 overflow-y-auto sm:p-5">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Nombre</label>
                  <input
                    v-model="form.nombre"
                    class="w-full px-3 py-2.5 rounded-xl border focus:outline-none focus:ring-2"
                    :class="(touchedNombre && nombreFieldError) || form.errors.nombre ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-slate-200 focus:ring-blue-500'"
                    @input="sanitizeNombreCategoria"
                    @blur="touchedNombre = true"
                    placeholder="Ej: Seguidor de Línea"
                  />
                  <p v-if="touchedNombre && nombreFieldError" class="text-xs text-red-600 mt-1">{{ nombreFieldError }}</p>
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

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Cantidad de integrantes</label>
                  <select
                    v-model.number="form.max_integrantes"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option v-for="cantidad in [1, 2, 3, 4, 5]" :key="cantidad" :value="cantidad">
                      {{ cantidad }} integrante{{ cantidad === 1 ? "" : "s" }}
                    </option>
                  </select>
                  <p class="text-xs text-slate-500 mt-1">El capitán cuenta como integrante.</p>
                  <p v-if="form.errors.max_integrantes" class="text-xs text-red-600 mt-1">
                    {{ form.errors.max_integrantes }}
                  </p>
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

                <div class="flex items-center justify-between rounded-xl bg-blue-50 border border-blue-100 px-4 py-3">
                  <div>
                    <p class="text-sm font-medium text-slate-800">Publicar resultados en vivo</p>
                    <p class="text-xs text-slate-600">Mostrar automáticamente los resultados al público</p>
                  </div>

                  <button
                    type="button"
                    @click="form.visible_publico_en_vivo = !form.visible_publico_en_vivo"
                    class="w-12 h-7 rounded-full transition relative"
                    :class="form.visible_publico_en_vivo ? 'bg-blue-600' : 'bg-slate-300'"
                    :aria-pressed="form.visible_publico_en_vivo"
                  >
                    <span
                      class="absolute top-0.5 h-6 w-6 rounded-full bg-white transition"
                      :class="form.visible_publico_en_vivo ? 'left-6' : 'left-0.5'"
                    />
                  </button>
                </div>
                <p v-if="form.errors.visible_publico_en_vivo" class="text-xs text-red-600 -mt-2">
                  {{ form.errors.visible_publico_en_vivo }}
                </p>

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

              <div class="border-t border-slate-200 p-4 flex flex-col-reverse gap-2 sm:p-5 sm:flex-row sm:justify-end sm:gap-3">
                <button
                  type="button"
                  @click="closeModal"
                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 transition sm:w-auto"
                >
                  Cancelar
                </button>

                <button
                  type="button"
                  @click="save"
                  :disabled="!canSaveCategory || form.processing"
                  class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-blue-600 sm:w-auto"
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
          <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-4 sm:p-5">
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

          <div class="grid min-h-0 grid-cols-1 gap-4 overflow-y-auto p-4 sm:gap-5 sm:p-5 lg:grid-cols-[1fr_1.25fr]">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <h3 class="font-semibold text-slate-900">
                  {{ rondaEditingId ? "Editar ronda" : "Nueva ronda" }}
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                  El nombre se genera automáticamente con el tipo y el orden de la ronda.
                </p>

                <p class="mt-1 text-xs text-slate-500">
                  Si no marcas intentos consecutivos, todos hacen el intento 1 antes de pasar al intento 2.
                </p>

                <div class="mt-4 space-y-4">
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
                      <label class="mb-1 block text-sm font-medium text-slate-700">Orden</label>
                      <input
                        v-model="rondaForm.orden"
                        type="number"
                        min="1"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Automático"
                      />
                    </div>
                  </div>

                  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                      <label class="mb-1 block text-sm font-medium text-slate-700">Intentos por ronda</label>
                      <input
                        v-model="rondaForm.cantidad_intentos"
                        type="number"
                        min="1"
                        max="10"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      />
                    </div>

                    <div>
                      <label class="mb-1 block text-sm font-medium text-slate-700">Clasifican</label>
                      <input
                        v-model="rondaForm.clasifican_cantidad"
                        type="number"
                        min="1"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Opcional"
                      />
                    </div>
                  </div>

                  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                      <label class="mb-1 block text-sm font-medium text-slate-700">Criterio</label>
                      <select
                        v-model="rondaForm.criterio_clasificacion"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      >
                        <option v-for="option in rondaCriterioOptions" :key="option.value" :value="option.value">
                          {{ option.label }}
                        </option>
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

                  <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-700">
                    <input
                      v-model="rondaForm.es_final"
                      type="checkbox"
                      class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                    />
                    Es ronda final
                  </label>

                  <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700">
                    <input
                      v-model="rondaForm.intentos_consecutivos"
                      type="checkbox"
                      class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span>
                      <span class="block font-medium">Intentos consecutivos</span>
                      <span class="block text-xs text-slate-500">Cada participante hace todos sus intentos seguidos.</span>
                    </span>
                  </label>

                  <div class="flex flex-col gap-2 pt-2 sm:flex-row sm:gap-3">
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
                      class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 transition hover:bg-slate-50 sm:min-w-[110px]"
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
                        <span class="inline-flex rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 ring-1 ring-indigo-200">
                          {{ ronda.cantidad_intentos }} intento{{ Number(ronda.cantidad_intentos) === 1 ? "" : "s" }}
                        </span>
                        <span class="inline-flex rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-medium text-cyan-700 ring-1 ring-cyan-200">
                          {{ ronda.intentos_consecutivos ? "Intentos consecutivos" : "Intentos por ronda" }}
                        </span>
                        <span v-if="ronda.clasifican_cantidad" class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200">
                          Clasifican {{ ronda.clasifican_cantidad }}
                        </span>
                      </div>
                      <p class="mt-2 text-xs text-slate-500">
                        Orden {{ ronda.orden }} · {{ rondaCriterioLabel(ronda.criterio_clasificacion) }}
                      </p>
                    </div>

                    <div class="flex gap-2">
                      <button
                        v-if="false"
                        type="button"
                        @click="clasificarRonda(ronda)"
                        class="inline-flex h-9 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!ronda.clasifican_cantidad || !ronda.has_resultados"
                        title="Cerrar y clasificar"
                      >
                        Clasificar
                      </button>

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
                No hay rondas creadas para esta categoría.
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

      <div class="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-4 shadow-xl sm:p-6">
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

        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3">
          <button
            v-if="alertModal.cancelText"
            type="button"
            @click="closeAlertModal"
            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 transition sm:w-auto"
          >
            {{ alertModal.cancelText }}
          </button>

          <button
            type="button"
            @click="confirmAlertModal"
            class="w-full px-4 py-2.5 rounded-xl text-white transition sm:w-auto"
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
