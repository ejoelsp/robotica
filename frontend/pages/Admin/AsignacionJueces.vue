<script setup>
import AdminLayout from "@/layouts/AdminLayout.vue";
import FormatoRegistroJuez from "@/components/Categorias/FormatoRegistroJuez.vue";
import { computed, ref, watch } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import axios from "axios";

import {
  MagnifyingGlassIcon,
  PlusIcon,
  UserPlusIcon,
  ClipboardDocumentCheckIcon,
  CheckCircleIcon,
  ClockIcon,
  ShieldCheckIcon,
  PencilSquareIcon,
  ExclamationCircleIcon,
  Cog6ToothIcon,
  TrashIcon,
  XMarkIcon,
} from "@heroicons/vue/24/outline";

defineOptions({ layout: AdminLayout });

const page = usePage();

const categorias = computed(() => page.props.categorias ?? []);
const jueces = computed(() => page.props.jueces ?? []);
const asignaciones = computed(() => page.props.asignaciones ?? []);
const competencia = computed(() => page.props.competencia ?? null);
const configJueces = computed(() => page.props.configJueces ?? {
  competencia_id: page.props.competenciaId ?? null,
  jueces_principales_requeridos: 1,
  jueces_apoyo_requeridos: 2,
});
const flash = computed(() => page.props.flash ?? {});

const search = ref("");
const isAsignacionModalOpen = ref(false);
const isNuevoJuezModalOpen = ref(false);
const categoriaSeleccionada = ref(null);
const isEditingAsignacion = ref(false);
const asignacionEditId = ref(null);
const isEditingConfigJueces = ref(false);
const activeWindow = ref("asignaciones");
const updatingJuezId = ref(null);
const isEstadoJuezModalOpen = ref(false);
const juezEstadoSeleccionado = ref(null);
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

const showSuccessModal = ref(false);
const successMessage = ref("");
const juezSubmitting = ref(false);

watch(
  () => flash.value.success,
  (msg) => {
    if (msg) {
      successMessage.value = msg;
      showSuccessModal.value = true;
    }
  },
  { immediate: true }
);

function closeSuccessModal() {
  showSuccessModal.value = false;
  successMessage.value = "";
}

function showMessage(message) {
  successMessage.value = message;
  showSuccessModal.value = true;
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

const nuevaAsignacionForm = useForm({
  categoria_id: "",
  juez_principal_ids: [],
  jueces_apoyo_ids: [],
});

const configJuecesForm = useForm({
  competencia_id: configJueces.value.competencia_id ?? "",
  jueces_principales_requeridos: configJueces.value.jueces_principales_requeridos ?? 1,
  jueces_apoyo_requeridos: configJueces.value.jueces_apoyo_requeridos ?? 2,
});

watch(
  () => configJueces.value,
  (value) => {
    configJuecesForm.competencia_id = value.competencia_id ?? "";
    configJuecesForm.jueces_principales_requeridos = value.jueces_principales_requeridos ?? 1;
    configJuecesForm.jueces_apoyo_requeridos = value.jueces_apoyo_requeridos ?? 2;
  },
  { deep: true }
);

const juezForm = useForm({
  name: "",
  last_name: "",
  email: "",
  telefono: "+593",
});

const juezTouchedFields = ref({
  name: false,
  last_name: false,
  email: false,
  telefono: false,
});

const namePattern = /^[\p{L}\s]+$/u;
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const phonePattern = /^\+\d{1,14}$/;

const juezFieldErrors = computed(() => {
  const errors = {};
  const name = juezForm.name.trim();
  const lastName = juezForm.last_name.trim();
  const email = juezForm.email.trim();
  const phone = juezForm.telefono.trim();

  if (!name) {
    errors.name = "El nombre es obligatorio.";
  } else if (!namePattern.test(name)) {
    errors.name = "El nombre solo puede contener letras y espacios.";
  }

  if (!lastName) {
    errors.last_name = "El apellido es obligatorio.";
  } else if (!namePattern.test(lastName)) {
    errors.last_name = "El apellido solo puede contener letras y espacios.";
  }

  if (!email) {
    errors.email = "El correo electrónico es obligatorio.";
  } else if (!emailPattern.test(email)) {
    errors.email = "Ingresa un correo electrónico válido.";
  }

  if (!phone || phone === "+") {
    errors.telefono = "El teléfono es obligatorio.";
  } else if (!phonePattern.test(phone)) {
    errors.telefono = "Usa el formato +593 seguido solo de números, sin espacios.";
  }

  return errors;
});

const canSubmitJuez = computed(() => {
  return Object.keys(juezFieldErrors.value).length === 0 && !juezSubmitting.value;
});

function clearFieldError(field) {
  if (juezForm.errors[field]) {
    juezForm.clearErrors(field);
  }
}

function visibleJuezFieldError(field) {
  return juezTouchedFields.value[field]
    ? juezFieldErrors.value[field] || juezForm.errors[field]
    : juezForm.errors[field];
}

function markJuezTouched(field) {
  juezTouchedFields.value[field] = true;
  clearFieldError(field);
}

function sanitizeJuezName(field) {
  juezForm[field] = juezForm[field].replace(/[^\p{L}\s]/gu, "");
  clearFieldError(field);
}

function sanitizeJuezEmail() {
  juezForm.email = juezForm.email.replace(/\s/g, "").toLowerCase();
  clearFieldError("email");
}

function sanitizeJuezPhone() {
  const digits = juezForm.telefono.replace(/\D/g, "").slice(0, 14);
  juezForm.telefono = `+${digits}`.slice(0, 15);
  clearFieldError("telefono");
}

function normalizeErrors(errors = {}) {
  return Object.fromEntries(
    Object.entries(errors).map(([field, value]) => [
      field,
      Array.isArray(value) ? value[0] ?? "" : value,
    ])
  );
}

const categoriasResumen = computed(() => {
  return categorias.value.map((categoria) => {
    const asignacionesCategoria = asignaciones.value.filter(
      (a) => Number(a.categoria_id) === Number(categoria.id)
    );

    const principalesRequeridos = Number(configJueces.value.jueces_principales_requeridos ?? 1);
    const apoyosRequeridos = Number(configJueces.value.jueces_apoyo_requeridos ?? 2);

    const principales = asignacionesCategoria.filter(
      (a) => (a.rol ?? "").toLowerCase() === "principal"
    ).length;

    const apoyos = asignacionesCategoria.filter(
      (a) => (a.rol ?? "").toLowerCase() === "apoyo"
    ).length;

    return {
      id: categoria.id,
      nombre: categoria.nombre,
      principales,
      apoyos,
      principalesRequeridos,
      apoyosRequeridos,
      pendientePrincipal: principales < principalesRequeridos,
      apoyosFaltantes: Math.max(apoyosRequeridos - apoyos, 0),
      completa: principales >= principalesRequeridos && apoyos >= apoyosRequeridos,
    };
  });
});

const categoriasPendientes = computed(() => {
  return categoriasResumen.value.filter((c) => !c.completa);
});

const asignacionesAgrupadas = computed(() => {
  return categorias.value
    .map((categoria) => {
      const asignacionesCategoria = asignaciones.value.filter(
        (a) => Number(a.categoria_id) === Number(categoria.id)
      );

      if (!asignacionesCategoria.length) return null;

      const principales = asignacionesCategoria.filter(
        (a) => (a.rol ?? "").toLowerCase() === "principal"
      );

      const apoyos = asignacionesCategoria.filter(
        (a) => (a.rol ?? "").toLowerCase() === "apoyo"
      );

      const principal = principales[0] ?? null;
      const asignacionReferencia = principal ?? apoyos[0] ?? asignacionesCategoria[0];

      return {
        id: categoria.id,
        categoria_id: categoria.id,
        categoria_nombre: categoria.nombre,
        principales,
        principal,
        apoyos,
        asignacionReferencia,
      };
    })
    .filter(Boolean);
});

const filteredAsignacionesAgrupadas = computed(() => {
  const term = search.value.trim().toLowerCase();

  if (!term) return asignacionesAgrupadas.value;

  return asignacionesAgrupadas.value.filter((row) => {
    const categoria = (row.categoria_nombre ?? "").toLowerCase();
    const principalesTexto = row.principales
      .flatMap((principal) => [principal.juez_nombre ?? "", principal.juez_email ?? ""])
      .join(" ")
      .toLowerCase();
    const apoyosTexto = row.apoyos
      .flatMap((apoyo) => [apoyo.juez_nombre ?? "", apoyo.juez_email ?? ""])
      .join(" ")
      .toLowerCase();

    return (
      categoria.includes(term) ||
      principalesTexto.includes(term) ||
      apoyosTexto.includes(term)
    );
  });
});

const juecesTabla = computed(() => {
  return jueces.value.map((juez) => ({
    ...juez,
    estado_texto: juez.estado_texto ?? (juez.estado ? "Activo" : "Inactivo"),
  }));
});

const resumenJueces = computed(() => {
  const activos = juecesTabla.value.filter((item) => item.estado).length;

  return {
    total: juecesTabla.value.length,
    activos,
    inactivos: juecesTabla.value.length - activos,
  };
});

const stats = computed(() => {
  const totalAsignaciones = asignaciones.value.length;
  const categoriasCompletas = categoriasResumen.value.filter((c) => c.completa).length;
  const categoriasPendientesCount = categoriasPendientes.value.length;
  const juecesActivos = jueces.value.filter((juez) => juez.estado).length;

  return {
    totalAsignaciones,
    categoriasCompletas,
    categoriasPendientesCount,
    juecesActivos,
  };
});

const juecesDisponiblesApoyo = computed(() => {
  const principales = nuevaAsignacionForm.juez_principal_ids.map(Number);

  return juecesTabla.value.filter(
    (juez) => juez.estado && !principales.includes(Number(juez.id))
  );
});

const juecesDisponiblesPrincipal = computed(() => {
  return juecesTabla.value.filter((juez) => juez.estado);
});

const principalesRequeridosAsignacion = computed(() => {
  return Number(configJueces.value.jueces_principales_requeridos ?? 1);
});

const apoyosRequeridosAsignacion = computed(() => {
  return Number(configJueces.value.jueces_apoyo_requeridos ?? 2);
});

const canSubmitAsignacion = computed(() => {
  return (
    nuevaAsignacionForm.juez_principal_ids.length === principalesRequeridosAsignacion.value &&
    nuevaAsignacionForm.jueces_apoyo_ids.length === apoyosRequeridosAsignacion.value &&
    !nuevaAsignacionForm.processing
  );
});

const totalJuecesPorCategoria = computed(() => {
  return Number(configJueces.value.jueces_principales_requeridos ?? 1)
    + Number(configJueces.value.jueces_apoyo_requeridos ?? 2);
});

const canSubmitConfigJueces = computed(() => {
  return (
    Number(configJuecesForm.competencia_id) > 0 &&
    Number(configJuecesForm.jueces_principales_requeridos) >= 1 &&
    Number(configJuecesForm.jueces_apoyo_requeridos) >= 0 &&
    !configJuecesForm.processing
  );
});

function toggleJuezPrincipal(juezId) {
  const id = Number(juezId);
  const actuales = nuevaAsignacionForm.juez_principal_ids.map(Number);
  const maxPrincipales = principalesRequeridosAsignacion.value;

  if (actuales.includes(id)) {
    nuevaAsignacionForm.juez_principal_ids = actuales.filter((item) => item !== id);
    return;
  }

  if (actuales.length >= maxPrincipales) {
    nuevaAsignacionForm.setError(
      "juez_principal_ids",
      `Esta competencia requiere máximo ${maxPrincipales} jueces principales por categoría.`
    );
    return;
  }

  nuevaAsignacionForm.clearErrors("juez_principal_ids");
  nuevaAsignacionForm.juez_principal_ids = [...actuales, id];
  nuevaAsignacionForm.clearErrors("jueces_apoyo_ids");
  nuevaAsignacionForm.jueces_apoyo_ids = nuevaAsignacionForm.jueces_apoyo_ids
    .map(Number)
    .filter((item) => item !== id);
}

function toggleJuezApoyo(juezId) {
  const id = Number(juezId);
  const actuales = nuevaAsignacionForm.jueces_apoyo_ids.map(Number);
  const maxApoyos = apoyosRequeridosAsignacion.value;

  if (actuales.includes(id)) {
    nuevaAsignacionForm.jueces_apoyo_ids = actuales.filter((item) => item !== id);
    return;
  }

  if (actuales.length >= maxApoyos) {
    nuevaAsignacionForm.setError(
      "jueces_apoyo_ids",
      `Esta competencia requiere máximo ${maxApoyos} jueces de apoyo por categoría.`
    );
    return;
  }

  nuevaAsignacionForm.clearErrors("jueces_apoyo_ids");
  nuevaAsignacionForm.jueces_apoyo_ids = [...actuales, id];
}

function submitAsignacion() {
  if (!canSubmitAsignacion.value) return;

  const wasEditing = isEditingAsignacion.value;

  const options = {
    preserveScroll: true,
    onSuccess: () => {
      closeAsignacionModal();
      nuevaAsignacionForm.reset();
      successMessage.value = wasEditing
        ? "Asignación actualizada correctamente."
        : "Asignación creada correctamente.";
      showSuccessModal.value = true;
    },
    onError: () => {
      console.log("Errores de validación:", nuevaAsignacionForm.errors);
    },
  };

  if (isEditingAsignacion.value) {
    nuevaAsignacionForm.put(
      `/admin/asignaciones-jueces/${asignacionEditId.value}`,
      options
    );
    return;
  }

  nuevaAsignacionForm.post("/admin/asignaciones-jueces", options);
}

function submitConfigJueces() {
  configJuecesForm.put("/admin/asignacion-jueces/configuracion", {
    preserveScroll: true,
    onSuccess: () => {
      isEditingConfigJueces.value = false;
      successMessage.value = "Configuración de jueces actualizada correctamente.";
      showSuccessModal.value = true;
    },
  });
}

function editarConfigJueces() {
  isEditingConfigJueces.value = true;
  configJuecesForm.clearErrors();
}

function openAsignacionFromCategoria(categoria) {
  categoriaSeleccionada.value = categoria;

  nuevaAsignacionForm.reset();
  nuevaAsignacionForm.clearErrors();
  nuevaAsignacionForm.categoria_id = categoria.id;
  nuevaAsignacionForm.juez_principal_ids = [];
  nuevaAsignacionForm.jueces_apoyo_ids = [];

  asignacionEditId.value = null;
  isEditingAsignacion.value = false;
  isAsignacionModalOpen.value = true;
}

function openEditarAsignacion(row) {
  categoriaSeleccionada.value = {
    id: row.categoria_id,
    nombre: row.categoria_nombre,
  };

  const asignacionesCategoria = asignaciones.value.filter(
    (item) => Number(item.categoria_id) === Number(row.categoria_id)
  );

  const principales = asignacionesCategoria.filter(
    (item) => (item.rol ?? "").toLowerCase() === "principal"
  );

  const apoyos = asignacionesCategoria.filter(
    (item) => (item.rol ?? "").toLowerCase() === "apoyo"
  );

  nuevaAsignacionForm.reset();
  nuevaAsignacionForm.clearErrors();
  nuevaAsignacionForm.categoria_id = row.categoria_id;
  nuevaAsignacionForm.juez_principal_ids = principales.map((item) => Number(item.juez_user_id));
  nuevaAsignacionForm.jueces_apoyo_ids = apoyos.map((item) => Number(item.juez_user_id));

  asignacionEditId.value = row.id;
  isEditingAsignacion.value = true;
  isAsignacionModalOpen.value = true;
}

function openEditarAsignacionAgrupada(row) {
  if (!row?.asignacionReferencia) return;
  openEditarAsignacion(row.asignacionReferencia);
}

function closeAsignacionModal() {
  isAsignacionModalOpen.value = false;
  categoriaSeleccionada.value = null;
  asignacionEditId.value = null;
  isEditingAsignacion.value = false;
  nuevaAsignacionForm.clearErrors();
}

function juezInitials(juez) {
  const first = (juez?.nombre ?? juez?.name ?? "").trim().charAt(0);
  const last = (juez?.last_name ?? "").trim().charAt(0);

  return `${first}${last}`.toUpperCase() || "J";
}

const estadoModalAction = computed(() => {
  const nextEstado = !juezEstadoSeleccionado.value?.estado;

  return nextEstado ? "activar" : "desactivar";
});

const estadoModalTitle = computed(() => {
  return estadoModalAction.value === "activar" ? "Activar juez" : "Desactivar juez";
});

function openEstadoJuezModal(juez) {
  if (!juez?.id || updatingJuezId.value) return;

  juezEstadoSeleccionado.value = juez;
  isEstadoJuezModalOpen.value = true;
}

function closeEstadoJuezModal() {
  if (updatingJuezId.value) return;

  isEstadoJuezModalOpen.value = false;
  juezEstadoSeleccionado.value = null;
}

async function confirmarEstadoJuez() {
  const juez = juezEstadoSeleccionado.value;

  if (!juez?.id || updatingJuezId.value) return;

  const nextEstado = !juez.estado;

  updatingJuezId.value = juez.id;

  try {
    const { data } = await axios.patch(
      `/admin/jueces/${juez.id}/estado`,
      { estado: nextEstado },
      {
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      }
    );

    successMessage.value = data.message ?? (
      nextEstado ? "Juez activado correctamente." : "Juez desactivado correctamente."
    );
    showSuccessModal.value = true;
    isEstadoJuezModalOpen.value = false;
    juezEstadoSeleccionado.value = null;
    router.reload({
      only: ["jueces"],
      preserveScroll: true,
      onSuccess: () => {
        activeWindow.value = "jueces";
      },
    });
  } catch (error) {
    successMessage.value = error.response?.data?.message ?? "No se pudo actualizar el estado del juez.";
    showSuccessModal.value = true;
  } finally {
    updatingJuezId.value = null;
  }
}

function openNuevoJuezModal() {
  juezForm.reset();
  juezForm.clearErrors();
  juezForm.telefono = "+593";
  juezTouchedFields.value = {
    name: false,
    last_name: false,
    email: false,
    telefono: false,
  };
  isNuevoJuezModalOpen.value = true;
}

function closeNuevoJuezModal() {
  isNuevoJuezModalOpen.value = false;
  juezForm.reset();
  juezForm.clearErrors();
  juezForm.telefono = "+593";
}

async function guardarJuez() {
  juezTouchedFields.value = {
    name: true,
    last_name: true,
    email: true,
    telefono: true,
  };

  if (!canSubmitJuez.value) return;

  juezSubmitting.value = true;
  juezForm.clearErrors();

  try {
    const { data } = await axios.post(
      "/admin/jueces",
      {
        name: juezForm.name,
        last_name: juezForm.last_name,
        email: juezForm.email,
        telefono: juezForm.telefono,
      },
      {
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      }
    );

    closeNuevoJuezModal();
    successMessage.value = data.message ?? "Juez creado correctamente y correo de activación enviado.";
    showSuccessModal.value = true;
    router.reload({ only: ["jueces"] });
  } catch (error) {
    const responseErrors = normalizeErrors(error.response?.data?.errors ?? {});

    if (Object.keys(responseErrors).length > 0) {
      juezForm.setError(responseErrors);
    } else {
      juezForm.setError("general", error.response?.data?.message ?? "No se pudo crear el juez.");
    }

    isNuevoJuezModalOpen.value = true;
  } finally {
    juezSubmitting.value = false;
  }
}

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

const categoriaModalidad = (categoria) => (
  categoria?.config_calificacion?.modalidad_competencia
    ?? categoria?.config_calificacion?.campos_json?.modalidad_competencia
    ?? "participacion_individual"
);

const isCategoriaEnfrentamiento = (categoria) => {
  const mecanismo = categoria?.config_calificacion?.mecanismo_codigo;

  return categoriaModalidad(categoria) === "enfrentamiento_directo"
    || ["combate", "combate_llaves", "soccer_goles"].includes(mecanismo);
};

const intentosModoLabel = (ronda) => (
  ronda?.intentos_consecutivos ? "Intentos consecutivos" : "Intentos por ronda"
);

const intentosResumenLabel = (ronda) => {
  const cantidad = Number(ronda?.cantidad_intentos ?? 1);
  const intentoTexto = cantidad === 1 ? "intento" : "intentos";
  const modoTexto = ronda?.intentos_consecutivos ? "consecutivos" : "por ronda";

  return `${cantidad} ${intentoTexto} ${modoTexto}`;
};

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
    showMessage(error?.response?.data?.message || "No se pudieron cargar las rondas.");
  } finally {
    rondasLoading.value = false;
  }
}

async function openRondas(cat) {
  if (isCategoriaEnfrentamiento(cat)) {
    showMessage("La gestión de llaves para esta categoría se genera automáticamente.");
    return;
  }

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

async function saveRonda(crearSiguienteRonda = false) {
  if (!rondasCategoria.value?.id) return;

  if (Number(rondaForm.value.cantidad_intentos || 0) < 1) {
    showMessage("La cantidad de intentos debe ser al menos 1.");
    return;
  }

  rondasSaving.value = true;

  try {
    const payload = {
      ...rondaForm.value,
      crear_siguiente_ronda: crearSiguienteRonda,
    };
    const url = rondaEditingId.value
      ? `/admin/categorias/${rondasCategoria.value.id}/rondas/${rondaEditingId.value}`
      : `/admin/categorias/${rondasCategoria.value.id}/rondas`;

    let response;

    if (rondaEditingId.value) {
      response = await axios.put(url, payload);
      showMessage(response?.data?.message || "Ronda actualizada correctamente.");
    } else {
      response = await axios.post(url, payload);
      showMessage(response?.data?.message || "Ronda creada correctamente.");
    }

    resetRondaForm();
    await loadRondas(rondasCategoria.value);
    router.reload({
      preserveScroll: true,
      preserveState: true,
      only: ["categorias", "flash"],
    });
  } catch (error) {
    if (error?.response?.status === 409 && error?.response?.data?.requires_next_round) {
      openAlertModal({
        title: "Crear siguiente ronda",
        message: error.response.data.message || "Esta ronda clasifica participantes. Debes crear una siguiente ronda para recibirlos.",
        confirmText: "Crear siguiente ronda",
        cancelText: "Cancelar",
        variant: "warning",
        onConfirm: () => saveRonda(true),
      });
      return;
    }

    const errors = error?.response?.data?.errors ?? {};
    showMessage(
      errors.tipo?.[0]
        || errors.orden?.[0]
        || errors.cantidad_intentos?.[0]
        || errors.intentos_consecutivos?.[0]
        || errors.clasifican_cantidad?.[0]
        || errors.criterio_clasificacion?.[0]
        || errors.estado?.[0]
        || "No se pudo guardar la ronda."
    );
  } finally {
    rondasSaving.value = false;
  }
}

async function clasificarRonda(ronda) {
  if (!rondasCategoria.value?.id) return;

  if (!ronda.clasifican_cantidad) {
    showMessage("Configura cuántos participantes pasan antes de clasificar.");
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
        showMessage("Clasificación generada correctamente.");
        await loadRondas(rondasCategoria.value);
      } catch (error) {
        const errors = error?.response?.data?.errors ?? {};
        showMessage(
          errors.ronda?.[0]
            || errors.clasifican_cantidad?.[0]
            || error?.response?.data?.message
            || "No se pudo clasificar la ronda."
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
    confirmText: "Sí, eliminar",
    cancelText: "Cancelar",
    variant: "danger",
    onConfirm: async () => {
      try {
        await axios.delete(`/admin/categorias/${rondasCategoria.value.id}/rondas/${ronda.id}`);
        showMessage("Ronda eliminada correctamente.");
        await loadRondas(rondasCategoria.value);
        router.reload({
          preserveScroll: true,
          preserveState: true,
          only: ["categorias", "flash"],
        });
      } catch (error) {
        const errors = error?.response?.data?.errors ?? {};
        showMessage(errors.ronda?.[0] || "No se pudo eliminar la ronda.");
      }
    },
  });
}
</script>

<template>
  <div class="w-full">
    <div class="mx-auto w-full max-w-[1380px] space-y-6 px-4 py-6 sm:px-6 lg:px-6">
      <div class="inline-flex rounded-full bg-slate-200 p-1">
        <button
          type="button"
          @click="activeWindow = 'asignaciones'"
          class="rounded-full px-6 py-3 text-sm font-semibold transition"
          :class="activeWindow === 'asignaciones' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
        >
          Asignaciones
        </button>
        <button
          type="button"
          @click="activeWindow = 'jueces'"
          class="rounded-full px-6 py-3 text-sm font-semibold transition"
          :class="activeWindow === 'jueces' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
        >
          Jueces Activos
        </button>
        <button
          type="button"
          @click="activeWindow = 'formato'"
          class="rounded-full px-6 py-3 text-sm font-semibold transition"
          :class="activeWindow === 'formato' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
        >
          Formato de registro
        </button>
        <button
          type="button"
          @click="activeWindow = 'rondas'"
          class="rounded-full px-6 py-3 text-sm font-semibold transition"
          :class="activeWindow === 'rondas' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
        >
          Gestión de rondas
        </button>
      </div>

      <div v-if="activeWindow === 'formato'">
        <FormatoRegistroJuez
          :categorias="categorias"
        />
      </div>

      <div v-if="activeWindow === 'rondas'" class="overflow-hidden rounded-3xl border border-slate-200 bg-white">
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

        <div v-if="categorias.length" class="divide-y divide-slate-200">
          <div
            v-for="cat in categorias"
            :key="`rondas-${cat.id ?? cat.nombre}`"
            class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
          >
            <div>
              <h3 class="font-semibold text-slate-900">{{ cat.nombre }}</h3>
              <div class="mt-2 flex flex-wrap gap-2">
                <span
                  class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1"
                  :class="isCategoriaEnfrentamiento(cat) ? 'bg-violet-50 text-violet-700 ring-violet-200' : 'bg-blue-50 text-blue-700 ring-blue-200'"
                >
                  <template v-if="isCategoriaEnfrentamiento(cat)">
                    {{ Math.max(Number(cat.rondas_count ?? 1), 1) }} ronda{{ Math.max(Number(cat.rondas_count ?? 1), 1) === 1 ? "" : "s" }} del sistema
                  </template>
                  <template v-else>
                    {{ cat.rondas_count ?? 0 }} rondas configuradas
                  </template>
                </span>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200">
                  {{ cat.inscripciones_count ?? 0 }} equipos inscritos
                </span>
              </div>
            </div>

            <button
              v-if="!isCategoriaEnfrentamiento(cat)"
              type="button"
              @click="openRondas(cat)"
              class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
            >
              <Cog6ToothIcon class="h-5 w-5" />
              Gestionar rondas
            </button>
            <div
              v-else
              class="inline-flex items-center justify-center rounded-xl border border-violet-200 bg-violet-50 px-4 py-2.5 text-sm font-semibold text-violet-700"
            >
              Llaves automáticas
            </div>
          </div>
        </div>

        <div v-else class="px-6 py-10 text-center text-sm text-slate-500">
          No hay categorías activas para poder configurar sus rondas.
        </div>
      </div>

      <div v-if="activeWindow === 'jueces'" class="overflow-hidden rounded-3xl border border-slate-200 bg-white">
        <div
          class="flex flex-col gap-4 border-b border-slate-200 px-6 py-6 lg:flex-row lg:items-center lg:justify-between"
        >
          <div class="flex items-center gap-3">
            <ClipboardDocumentCheckIcon class="h-6 w-6 text-blue-500" />
            <div>
              <h2 class="text-[20px] font-bold text-slate-900">Jueces Activos</h2>
              <p class="mt-1 text-sm text-slate-500">
                {{ resumenJueces.total }} jueces registrados ·
                {{ resumenJueces.activos }} activos ·
                {{ resumenJueces.inactivos }} inactivos
              </p>
            </div>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full">
            <thead class="bg-slate-50">
              <tr class="text-left text-[13px] tracking-wide text-slate-900">
                <th class="px-6 py-4 font-semibold">Foto de perfil</th>
                <th class="px-6 py-4 font-semibold">Nombre y apellido del juez</th>
                <th class="px-6 py-4 font-semibold">Estado</th>
                <th class="px-6 py-4 font-semibold">Contacto</th>
                <th class="px-6 py-4 font-semibold">Acciones</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-200">
              <tr
                v-for="juez in juecesTabla"
                :key="juez.id"
                class="hover:bg-slate-50"
              >
                <td class="px-6 py-5">
                  <img
                    v-if="juez.photo_url"
                    :src="juez.photo_url"
                    :alt="juez.nombre"
                    class="h-12 w-12 rounded-full object-cover ring-1 ring-slate-200"
                  />
                  <div
                    v-else
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-600 ring-1 ring-slate-200"
                  >
                    {{ juezInitials(juez) }}
                  </div>
                </td>

                <td class="px-6 py-5">
                  <p class="text-[16px] font-semibold text-slate-900">
                    {{ juez.nombre || "Juez sin nombre" }}
                  </p>
                  <p class="mt-1 text-sm text-slate-500">
                    {{ juez.email || "Sin correo registrado" }}
                  </p>
                </td>

                <td class="px-6 py-5">
                  <span
                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                    :class="
                      juez.estado
                        ? 'bg-emerald-50 text-emerald-700'
                        : 'bg-amber-50 text-amber-700'
                    "
                  >
                    {{ juez.estado_texto }}
                  </span>
                </td>

                <td class="px-6 py-5 text-sm text-slate-500">
                  {{ juez.telefono || "Sin teléfono" }}
                </td>

                <td class="px-6 py-5">
                  <button
                    type="button"
                    @click.prevent.stop="openEstadoJuezModal(juez)"
                    :disabled="updatingJuezId === juez.id"
                    class="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-50"
                    :class="
                      juez.estado
                        ? 'border-amber-200 text-amber-700 hover:bg-amber-50'
                        : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'
                    "
                  >
                    <ShieldCheckIcon class="h-5 w-5" />
                    {{
                      updatingJuezId === juez.id
                        ? "Actualizando..."
                        : juez.estado
                          ? "Desactivar"
                          : "Activar"
                    }}
                  </button>
                </td>
              </tr>

              <tr v-if="juecesTabla.length === 0">
                <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500">
                  Todavía no hay jueces registrados.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="activeWindow === 'asignaciones'" class="space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
          <div>
            <h1 class="text-[18px] font-bold text-slate-900 sm:text-[20px] lg:text-[22px]">
              Asignación de Jueces
            </h1>
            <p class="mt-1 text-sm text-slate-500">
              Administra la asignación de jueces por categoría
            </p>
          </div>

          <div class="flex w-full justify-end md:w-auto">
            <button
              @click="openNuevoJuezModal"
              class="inline-flex items-center justify-center gap-2 rounded-2xl bg-blue-600 px-5 py-3 text-white transition hover:bg-blue-700"
            >
              <UserPlusIcon class="h-5 w-5" />
              Nuevo Juez
            </button>
          </div>
        </div>

        <form
          @submit.prevent="submitConfigJueces"
          class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
        >
          <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div class="min-w-0">
              <p class="text-sm font-semibold text-blue-700">Configuración de jueces</p>
              <h2 class="mt-1 text-xl font-bold text-slate-900">
                {{ competencia?.nombre ?? "Competencia activa" }}
              </h2>
              <p class="mt-1 text-sm text-slate-500">
                Define la cantidad general requerida para cada categoría de esta competencia.
              </p>
            </div>

            <div class="grid w-full grid-cols-1 gap-4 md:grid-cols-[1fr_1fr_auto] xl:w-auto xl:min-w-[720px]">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                  Jueces Principales por categoría
                </label>
                <input
                  v-model.number="configJuecesForm.jueces_principales_requeridos"
                  type="number"
                  min="1"
                  max="10"
                  :disabled="!isEditingConfigJueces"
                  class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :class="!isEditingConfigJueces ? 'cursor-not-allowed opacity-75' : ''"
                />
                <p
                  v-if="configJuecesForm.errors.jueces_principales_requeridos"
                  class="mt-1 text-xs text-red-600"
                >
                  {{ configJuecesForm.errors.jueces_principales_requeridos }}
                </p>
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                  Jueces de Apoyo por categoría
                </label>
                <input
                  v-model.number="configJuecesForm.jueces_apoyo_requeridos"
                  type="number"
                  min="0"
                  max="20"
                  :disabled="!isEditingConfigJueces"
                  class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :class="!isEditingConfigJueces ? 'cursor-not-allowed opacity-75' : ''"
                />
                <p
                  v-if="configJuecesForm.errors.jueces_apoyo_requeridos"
                  class="mt-1 text-xs text-red-600"
                >
                  {{ configJuecesForm.errors.jueces_apoyo_requeridos }}
                </p>
              </div>

              <button
                v-if="isEditingConfigJueces"
                type="submit"
                :disabled="!canSubmitConfigJueces"
                class="inline-flex h-[48px] items-center justify-center gap-2 rounded-2xl bg-blue-600 px-5 py-3 font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 md:self-end"
              >
                <CheckCircleIcon class="h-5 w-5" />
                {{ configJuecesForm.processing ? "Guardando..." : "Guardar configuración" }}
              </button>

              <button
                v-else
                type="button"
                @click="editarConfigJueces"
                class="inline-flex h-[48px] items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 font-semibold text-slate-800 transition hover:bg-slate-50 md:self-end"
              >
                <PencilSquareIcon class="h-5 w-5" />
                Editar configuración
              </button>
            </div>
          </div>
        </form>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
          <div class="rounded-2xl border border-emerald-200 bg-white px-5 py-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-slate-700">Categorías Completas</p>
                <p class="mt-2 text-3xl font-bold text-emerald-700">
                  {{ stats.categoriasCompletas }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                  Cumplen {{ configJueces.jueces_principales_requeridos }} principal + {{ configJueces.jueces_apoyo_requeridos }} apoyo
                </p>
              </div>
              <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-50">
                <CheckCircleIcon class="h-6 w-6 text-emerald-600" />
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-amber-200 bg-white px-5 py-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-slate-700">Categorías Pendientes</p>
                <p class="mt-2 text-3xl font-bold text-amber-700">
                  {{ stats.categoriasPendientesCount }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                  Faltan jueces para completar el estándar
                </p>
              </div>
              <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-amber-50">
                <ClockIcon class="h-6 w-6 text-amber-600" />
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-blue-200 bg-white px-5 py-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-slate-700">Jueces Activos</p>
                <p class="mt-2 text-3xl font-bold text-blue-700">
                  {{ stats.juecesActivos }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ totalJuecesPorCategoria }} jueces esperados por categoría
                </p>
              </div>
              <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-blue-50">
                <ShieldCheckIcon class="h-6 w-6 text-blue-600" />
              </div>
            </div>
          </div>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white">
          <div class="border-b border-slate-200 px-6 py-6">
            <div class="flex items-center gap-3">
              <ExclamationCircleIcon class="h-6 w-6 text-amber-500" />
              <div>
                <h2 class="text-[20px] font-bold text-slate-900">Categorías Pendientes</h2>
                <p class="mt-1 text-sm text-slate-500">
                  {{ categoriasPendientes.length }} categorías sin asignar
                </p>
              </div>
            </div>
          </div>

          <div class="overflow-x-auto p-5">
            <div class="flex min-w-max gap-4">
            <button
              v-for="categoria in categoriasPendientes"
              :key="categoria.id"
              type="button"
              @click="openAsignacionFromCategoria(categoria)"
              class="group relative min-h-[150px] w-[280px] shrink-0 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-5 text-left transition hover:border-amber-300 hover:bg-amber-100"
            >
              <div class="absolute right-5 top-5 opacity-0 transition group-hover:opacity-100">
                <div
                  class="flex h-8 w-8 items-center justify-center rounded-full bg-white/80 text-amber-600 shadow-sm"
                >
                  <PlusIcon class="h-5 w-5" />
                </div>
              </div>

              <h3 class="pr-10 text-[18px] font-bold text-slate-900">
                {{ categoria.nombre }}
              </h3>

              <div class="mt-3 flex items-center gap-2 text-sm font-medium text-amber-600">
                <ExclamationCircleIcon class="h-4 w-4" />
                <span v-if="categoria.pendientePrincipal">Requiere jueces principales</span>
                <span v-else>Faltan {{ categoria.apoyosFaltantes }} jueces de apoyo</span>
              </div>
            </button>
            </div>

            <div
              v-if="categoriasPendientes.length === 0"
              class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
            >
              No hay categorías pendientes.
            </div>
          </div>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white">
          <div
            class="flex flex-col gap-4 border-b border-slate-200 px-6 py-6 lg:flex-row lg:items-center lg:justify-between"
          >
            <div class="flex items-center gap-3">
              <CheckCircleIcon class="h-6 w-6 text-green-500" />
              <div>
                <h2 class="text-[20px] font-bold text-slate-900">Asignaciones Realizadas</h2>
                <p class="mt-1 text-sm text-slate-500">
                  {{ filteredAsignacionesAgrupadas.length }} asignaciones registradas
                </p>
              </div>
            </div>

            <div class="relative w-full lg:w-[320px]">
              <input
                v-model="search"
                type="text"
                class="w-full rounded-2xl border border-slate-300 bg-white py-3 pl-12 pr-4 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Buscar asignación..."
              />
              <MagnifyingGlassIcon
                class="pointer-events-none absolute left-4 top-3.5 h-5 w-5 text-slate-400"
              />
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full">
              <thead class="bg-slate-50">
                <tr class="text-left text-[13px] tracking-wide text-slate-900">
                  <th class="px-6 py-4 font-semibold">Categoría</th>
                  <th class="px-6 py-4 font-semibold">Jueces Principales</th>
                  <th class="px-6 py-4 font-semibold">Jueces de Apoyo</th>
                  <th class="px-6 py-4 font-semibold">Acciones</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-slate-200">
                <tr
                  v-for="row in filteredAsignacionesAgrupadas"
                  :key="row.id"
                  class="hover:bg-slate-50"
                >
                  <td class="px-6 py-5 text-[16px] font-semibold text-slate-900">
                    {{ row.categoria_nombre ?? "—" }}
                  </td>

                  <td class="px-6 py-5">
                    <template v-if="row.principales.length">
                      <div class="space-y-3">
                        <div
                          v-for="principal in row.principales"
                          :key="principal.id"
                          class="flex items-start gap-3"
                        >
                          <ShieldCheckIcon class="mt-0.5 h-5 w-5 shrink-0 text-blue-600" />
                          <div>
                            <p class="text-[16px] font-semibold text-slate-900">
                              {{ principal.juez_nombre ?? "—" }}
                            </p>
                            <p v-if="principal.juez_email" class="mt-1 text-sm text-slate-500">
                              {{ principal.juez_email ?? "" }}
                            </p>
                          </div>
                        </div>
                      </div>
                    </template>
                    <p v-else class="text-[16px] italic text-slate-400">Sin asignar</p>
                  </td>

                  <td class="px-6 py-5">
                    <template v-if="row.apoyos.length">
                      <div class="space-y-2">
                        <div v-for="apoyo in row.apoyos" :key="apoyo.id">
                          <p class="text-[16px] font-medium text-slate-900">
                            {{ apoyo.juez_nombre ?? "—" }}
                          </p>
                          <p v-if="apoyo.juez_email" class="mt-1 text-sm text-slate-400">
                            {{ apoyo.juez_email }}
                          </p>
                        </div>
                      </div>
                    </template>
                    <p v-else class="text-[16px] italic text-slate-400">Ninguno</p>
                  </td>

                  <td class="px-6 py-5">
                    <button
                      type="button"
                      @click="openEditarAsignacionAgrupada(row)"
                      class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                      title="Editar"
                      :disabled="!row.asignacionReferencia"
                    >
                      <PencilSquareIcon class="h-5 w-5" />
                      Editar
                    </button>
                  </td>
                </tr>

                <tr v-if="filteredAsignacionesAgrupadas.length === 0">
                  <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500">
                    No se encontraron asignaciones.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <Teleport to="body">
        <div v-if="isAsignacionModalOpen" class="fixed inset-0 z-[9999]">
          <div class="absolute inset-0 bg-black/40" @click="closeAsignacionModal"></div>

          <div class="relative grid h-full w-full place-items-center p-4 sm:p-6">
            <div
              class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl"
            >
              <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-6">
                <div>
                  <h2 class="text-xl font-bold text-slate-900">
                    {{ isEditingAsignacion ? "Editar Asignación de Jueces" : "Nueva Asignación de Jueces" }}
                  </h2>
                  <p class="mt-1 text-sm text-slate-500">
                    {{
                      isEditingAsignacion
                        ? "Actualiza los jueces principales y los jueces de apoyo de la categoría"
                        : "Selecciona los jueces principales y luego los jueces de apoyo"
                    }}
                  </p>
                </div>

                <button
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-slate-200 hover:bg-slate-50"
                  @click="closeAsignacionModal"
                  type="button"
                >
                  <XMarkIcon class="h-5 w-5 text-slate-600" />
                </button>
              </div>

              <form @submit.prevent="submitAsignacion" class="flex min-h-0 flex-col">
                <div class="space-y-5 overflow-y-auto p-6">
                  <div
                    v-if="nuevaAsignacionForm.errors.general"
                    class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                  >
                    {{ nuevaAsignacionForm.errors.general }}
                  </div>

                  <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Categoría</label>
                    <div
                      class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-800"
                    >
                      {{ categoriaSeleccionada?.nombre ?? "—" }}
                    </div>
                  </div>

                  <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                    <div class="mb-3">
                      <label class="block text-sm font-medium text-slate-700">
                        Jueces principales
                      </label>
                      <p class="mt-1 text-xs text-slate-500">
                        Selecciona {{ principalesRequeridosAsignacion }} jueces principales para esta categoría.
                      </p>
                    </div>

                    <div class="grid max-h-64 grid-cols-1 gap-3 overflow-y-auto md:grid-cols-2">
                      <label
                        v-for="juez in juecesDisponiblesPrincipal"
                        :key="juez.id"
                        class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 transition hover:border-blue-300 hover:bg-blue-50/50"
                      >
                        <input
                          type="checkbox"
                          class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                          :checked="nuevaAsignacionForm.juez_principal_ids.map(Number).includes(Number(juez.id))"
                          :disabled="
                            !nuevaAsignacionForm.juez_principal_ids.map(Number).includes(Number(juez.id)) &&
                            nuevaAsignacionForm.juez_principal_ids.length >= principalesRequeridosAsignacion
                          "
                          @change="toggleJuezPrincipal(juez.id)"
                        />
                        <div class="min-w-0">
                          <p class="text-sm font-semibold text-slate-900">
                            {{ juez.nombre }}
                          </p>
                          <p class="truncate text-xs text-slate-500">
                            {{ juez.email || "Sin correo registrado" }}
                          </p>
                        </div>
                      </label>
                    </div>

                    <p
                      v-if="nuevaAsignacionForm.errors.juez_principal_ids || nuevaAsignacionForm.errors.juez_principal_id"
                      class="mt-2 text-xs text-red-600"
                    >
                      {{ nuevaAsignacionForm.errors.juez_principal_ids || nuevaAsignacionForm.errors.juez_principal_id }}
                    </p>
                  </div>

                  <div
                    class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4"
                    :class="{ 'opacity-60': nuevaAsignacionForm.juez_principal_ids.length !== principalesRequeridosAsignacion }"
                  >
                    <div class="mb-3">
                      <label class="block text-sm font-medium text-slate-700">
                        Jueces de apoyo
                      </label>
                      <p class="mt-1 text-xs text-slate-500">
                        Selecciona {{ apoyosRequeridosAsignacion }} jueces de apoyo después de escoger los principales.
                      </p>
                    </div>

                    <div
                      v-if="nuevaAsignacionForm.juez_principal_ids.length === principalesRequeridosAsignacion"
                      class="grid max-h-64 grid-cols-1 gap-3 overflow-y-auto md:grid-cols-2"
                    >
                      <label
                        v-for="juez in juecesDisponiblesApoyo"
                        :key="juez.id"
                        class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 transition hover:border-blue-300 hover:bg-blue-50/50"
                      >
                        <input
                          type="checkbox"
                          class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                          :checked="nuevaAsignacionForm.jueces_apoyo_ids.map(Number).includes(Number(juez.id))"
                          :disabled="
                            !nuevaAsignacionForm.jueces_apoyo_ids.map(Number).includes(Number(juez.id)) &&
                            nuevaAsignacionForm.jueces_apoyo_ids.length >= apoyosRequeridosAsignacion
                          "
                          @change="toggleJuezApoyo(juez.id)"
                        />
                        <div class="min-w-0">
                          <p class="text-sm font-semibold text-slate-900">
                            {{ juez.nombre }}
                          </p>
                          <p class="truncate text-xs text-slate-500">
                            {{ juez.email || "Sin correo registrado" }}
                          </p>
                        </div>
                      </label>
                    </div>

                    <div
                      v-else
                      class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center text-sm text-slate-500"
                    >
                      Primero selecciona los jueces principales requeridos para habilitar los jueces de apoyo.
                    </div>

                    <p
                      v-if="nuevaAsignacionForm.errors.jueces_apoyo_ids"
                      class="mt-3 text-xs text-red-600"
                    >
                      {{ nuevaAsignacionForm.errors.jueces_apoyo_ids }}
                    </p>
                  </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-200 p-6">
                  <button
                    @click="closeAsignacionModal"
                    class="rounded-2xl border border-slate-200 px-5 py-3 text-slate-700 transition hover:bg-slate-50"
                    type="button"
                  >
                    Cancelar
                  </button>

                  <button
                    type="submit"
                    :disabled="!canSubmitAsignacion"
                    class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-5 py-3 text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                  >
                    <CheckCircleIcon class="h-5 w-5" />
                    {{
                      isEditingAsignacion
                        ? (nuevaAsignacionForm.processing ? "Guardando..." : "Guardar Cambios")
                        : (nuevaAsignacionForm.processing ? "Guardando..." : "Crear Asignación")
                    }}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </Teleport>

      <Teleport to="body">
        <div v-if="isEstadoJuezModalOpen" class="fixed inset-0 z-[10000]">
          <div class="absolute inset-0 bg-black/40" @click="closeEstadoJuezModal"></div>

          <div class="relative grid h-full w-full place-items-center p-4 sm:p-6">
            <div class="w-full max-w-md overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl">
              <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-6">
                <div>
                  <h2 class="text-xl font-bold text-slate-900">{{ estadoModalTitle }}</h2>
                  <p class="mt-1 text-sm text-slate-500">
                    Esta acción actualizará el acceso del juez en el sistema.
                  </p>
                </div>

                <button
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-slate-200 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                  @click="closeEstadoJuezModal"
                  :disabled="!!updatingJuezId"
                  type="button"
                >
                  <XMarkIcon class="h-5 w-5 text-slate-600" />
                </button>
              </div>

              <div class="space-y-4 p-6">
                <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                  <img
                    v-if="juezEstadoSeleccionado?.photo_url"
                    :src="juezEstadoSeleccionado.photo_url"
                    :alt="juezEstadoSeleccionado.nombre"
                    class="h-12 w-12 rounded-full object-cover ring-1 ring-slate-200"
                  />
                  <div
                    v-else
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-white text-sm font-bold text-slate-600 ring-1 ring-slate-200"
                  >
                    {{ juezInitials(juezEstadoSeleccionado) }}
                  </div>

                  <div class="min-w-0">
                    <p class="truncate text-base font-semibold text-slate-900">
                      {{ juezEstadoSeleccionado?.nombre || "Juez sin nombre" }}
                    </p>
                    <p class="truncate text-sm text-slate-500">
                      {{ juezEstadoSeleccionado?.email || "Sin correo registrado" }}
                    </p>
                  </div>
                </div>

                <p class="text-sm leading-6 text-slate-600">
                  ¿Deseas {{ estadoModalAction }} a este juez?
                </p>
              </div>

              <div class="flex justify-end gap-3 border-t border-slate-200 p-6">
                <button
                  type="button"
                  @click="closeEstadoJuezModal"
                  :disabled="!!updatingJuezId"
                  class="rounded-2xl border border-slate-200 px-5 py-3 text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  Cancelar
                </button>

                <button
                  type="button"
                  @click.prevent.stop="confirmarEstadoJuez"
                  :disabled="!!updatingJuezId"
                  class="inline-flex items-center gap-2 rounded-2xl px-5 py-3 font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-60"
                  :class="estadoModalAction === 'activar' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-amber-600 hover:bg-amber-700'"
                >
                  <ShieldCheckIcon class="h-5 w-5" />
                  {{ updatingJuezId ? "Actualizando..." : estadoModalTitle }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Teleport>

      <Teleport to="body">
        <div v-if="isNuevoJuezModalOpen" class="fixed inset-0 z-[10000]">
          <div class="absolute inset-0 bg-black/40" @click="closeNuevoJuezModal"></div>

          <div class="relative grid h-full w-full place-items-center p-4 sm:p-6">
            <div
              class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
            >
              <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-5">
                <div>
                  <h2 class="text-xl font-bold text-slate-900">Nuevo Juez</h2>
                  <p class="mt-1 text-sm text-slate-500">Crea un usuario con rol juez</p>
                </div>

                <button
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-slate-200 hover:bg-slate-50"
                  @click="closeNuevoJuezModal"
                  type="button"
                >
                  <XMarkIcon class="h-5 w-5 text-slate-600" />
                </button>
              </div>

              <form @submit.prevent="guardarJuez">
                <div class="space-y-4 p-5">
                  <div
                    v-if="juezForm.errors.general"
                    class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                  >
                    {{ juezForm.errors.general }}
                  </div>

                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nombre(s):</label>
                    <input
                      v-model="juezForm.name"
                      @input="sanitizeJuezName('name')"
                      @blur="markJuezTouched('name')"
                      type="text"
                      autocomplete="given-name"
                      class="w-full rounded-xl border px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                      :class="visibleJuezFieldError('name') ? 'border-red-400 focus:ring-red-300' : 'border-slate-300'"
                      placeholder="Ej: Carlos"
                    />
                    <p v-if="visibleJuezFieldError('name')" class="mt-1 text-xs text-red-600">
                      {{ visibleJuezFieldError("name") }}
                    </p>
                  </div>

                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Apellido(s):</label>
                    <input
                      v-model="juezForm.last_name"
                      @input="sanitizeJuezName('last_name')"
                      @blur="markJuezTouched('last_name')"
                      type="text"
                      autocomplete="family-name"
                      class="w-full rounded-xl border px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                      :class="visibleJuezFieldError('last_name') ? 'border-red-400 focus:ring-red-300' : 'border-slate-300'"
                      placeholder="Ej: Álvarez"
                    />
                    <p v-if="visibleJuezFieldError('last_name')" class="mt-1 text-xs text-red-600">
                      {{ visibleJuezFieldError("last_name") }}
                    </p>
                  </div>

                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Correo electrónico:</label>
                    <input
                      v-model="juezForm.email"
                      @input="sanitizeJuezEmail"
                      @blur="markJuezTouched('email')"
                      type="email"
                      autocomplete="email"
                      class="w-full rounded-xl border px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                      :class="visibleJuezFieldError('email') ? 'border-red-400 focus:ring-red-300' : 'border-slate-300'"
                      placeholder="Ej: juez@gmail.com"
                    />
                    <p v-if="visibleJuezFieldError('email')" class="mt-1 text-xs text-red-600">
                      {{ visibleJuezFieldError("email") }}
                    </p>
                  </div>

                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Teléfono:</label>
                    <input
                      v-model="juezForm.telefono"
                      @input="sanitizeJuezPhone"
                      @blur="markJuezTouched('telefono')"
                      type="tel"
                      inputmode="numeric"
                      autocomplete="tel"
                      maxlength="15"
                      class="w-full rounded-xl border px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                      :class="visibleJuezFieldError('telefono') ? 'border-red-400 focus:ring-red-300' : 'border-slate-300'"
                      placeholder="+593"
                    />
                    <p v-if="visibleJuezFieldError('telefono')" class="mt-1 text-xs text-red-600">
                      {{ visibleJuezFieldError("telefono") }}
                    </p>
                  </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-200 p-5">
                  <button
                    @click="closeNuevoJuezModal"
                    class="rounded-xl border border-slate-200 px-5 py-2.5 text-slate-700 transition hover:bg-slate-50"
                    type="button"
                  >
                    Cancelar
                  </button>

                  <button
                    type="submit"
                    :disabled="!canSubmitJuez"
                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                  >
                    <CheckCircleIcon class="h-5 w-5" />
                    {{ juezSubmitting ? "Guardando..." : "Crear juez" }}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </Teleport>

      <Teleport to="body">
        <div v-if="isRondasModalOpen" class="fixed inset-0 z-[10030]">
          <div class="absolute inset-0 bg-black/40" @click="closeRondasModal"></div>

          <div class="relative grid h-full w-full place-items-center p-4 sm:p-6">
            <div class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-slate-200 bg-slate-50 shadow-xl">
              <div class="flex items-start justify-between gap-4 border-b border-slate-200 bg-white p-5">
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

              <div class="grid min-h-0 flex-1 gap-4 overflow-y-auto p-4 lg:grid-cols-[360px_1fr]">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                  <h3 class="text-base font-semibold text-slate-900">
                    {{ rondaEditingId ? "Editar ronda" : "Nueva ronda" }}
                  </h3>
                  <p class="mt-1 text-sm text-slate-500">
                    El nombre se genera automáticamente con el tipo y el orden de la ronda.
                  </p>
                  <p class="mt-1 text-xs text-slate-500">
                    Si no marcas intentos consecutivos, todos hacen el intento 1 antes de pasar al intento 2.
                  </p>

                  <div class="mt-4 space-y-4">
                    <div class="grid grid-cols-1 gap-3">
                      <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Tipo</label>
                        <select
                          v-model="rondaForm.tipo"
                          class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                          <option value="clasificatoria">Clasificatoria</option>
                          <option value="semifinal">Semifinal</option>
                          <option value="final">Final</option>
                          <option value="libre">Libre</option>
                        </select>
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

                    <div class="flex gap-3 pt-2">
                      <button
                        type="button"
                        @click="saveRonda()"
                        :disabled="rondasSaving"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                      >
                        <CheckCircleIcon class="h-5 w-5" />
                        {{ rondasSaving ? "Guardando..." : (rondaEditingId ? "Guardar cambios" : "Crear ronda") }}
                      </button>

                      <button
                        v-if="rondaEditingId"
                        type="button"
                        @click="resetRondaForm"
                        class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                      >
                        Cancelar
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
                          <div class="flex flex-wrap items-center gap-2">
                            <h4 class="font-semibold text-slate-900">{{ ronda.nombre }}</h4>
                            <span
                              class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1"
                              :class="rondaEstadoBadge(ronda.estado)"
                            >
                              {{ rondaEstadoLabel(ronda.estado) }}
                            </span>
                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200">
                              {{ ronda.resultados_count }} evaluaciones
                            </span>
                            <span class="inline-flex rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-medium text-cyan-700 ring-1 ring-cyan-200">
                              {{ intentosResumenLabel(ronda) }}
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
                            title="Editar"
                          >
                            <PencilSquareIcon class="h-5 w-5 text-slate-700" />
                          </button>
                          <button
                            type="button"
                            @click="requestRemoveRonda(ronda)"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="ronda.has_resultados"
                            title="Eliminar"
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
        <div v-if="alertModal.show" class="fixed inset-0 z-[11020]">
          <div class="absolute inset-0 bg-black/40" @click="closeAlertModal"></div>

          <div class="relative grid h-full w-full place-items-center p-4">
            <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-xl">
              <h3 class="text-lg font-bold text-slate-900">{{ alertModal.title }}</h3>
              <p class="mt-2 text-sm text-slate-600">{{ alertModal.message }}</p>

              <div class="mt-6 flex justify-end gap-3">
                <button
                  v-if="alertModal.cancelText"
                  type="button"
                  @click="closeAlertModal"
                  class="rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                  {{ alertModal.cancelText }}
                </button>

                <button
                  type="button"
                  @click="confirmAlertModal"
                  class="rounded-2xl px-4 py-2.5 text-sm font-semibold text-white transition"
                  :class="alertModal.variant === 'danger' ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'"
                >
                  {{ alertModal.confirmText }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Teleport>

      <Teleport to="body">
        <div v-if="showSuccessModal" class="fixed inset-0 z-[11000]">
          <div class="absolute inset-0 bg-black/40" @click="closeSuccessModal"></div>

          <div class="relative grid h-full w-full place-items-center p-4">
            <div
              class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-xl"
            >
              <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                <CheckCircleIcon class="h-9 w-9 text-green-600" />
              </div>

              <h3 class="mt-4 text-xl font-bold text-slate-900">Operación exitosa</h3>
              <p class="mt-2 text-sm text-slate-600">
                {{ successMessage }}
              </p>

              <div class="mt-6">
                <button
                  @click="
                    closeSuccessModal();
                    router.reload({
                      preserveScroll: true,
                      preserveState: true,
                      only: ['competencia', 'configJueces', 'categorias', 'jueces', 'asignaciones', 'flash'],
                    });
                  "
                  class="inline-flex items-center justify-center rounded-2xl bg-blue-600 px-5 py-3 text-white transition hover:bg-blue-700"
                  type="button"
                >
                  Aceptar
                </button>
              </div>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </div>
</template>
