<script setup>
import AdminLayout from "@/layouts/AdminLayout.vue";
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useForm, usePage, router } from "@inertiajs/vue3";
import {
  CalendarDaysIcon,
  ExclamationTriangleIcon,
  LinkIcon,
  MagnifyingGlassIcon,
  PencilSquareIcon,
  PhotoIcon,
  TrashIcon,
  UserGroupIcon,
  XMarkIcon,
} from "@heroicons/vue/24/outline";

defineOptions({ layout: AdminLayout });

const page = usePage();

const isFeedbackOpen = ref(false);
const feedbackMessage = ref("");
const feedbackType = ref("success");
let feedbackTimer = null;

const isDeleteConfirmOpen = ref(false);
const deletingCompetition = ref(null);

const showFeedback = (message, type = "success") => {
  if (!message) return;

  feedbackMessage.value = message;
  feedbackType.value = type;
  isFeedbackOpen.value = true;

  clearTimeout(feedbackTimer);
  feedbackTimer = setTimeout(() => {
    isFeedbackOpen.value = false;
  }, type === "success" ? 2500 : 4500);
};

watch(
  () => [page.props.flash?.success, page.props.flash?.error],
  ([success, error]) => {
    const message = success || error;
    if (!message) return;
    showFeedback(message, success ? "success" : "error");
  },
  { immediate: true }
);

const competencias = computed(() => page.props.competencias ?? []);

const searchTerm = ref("");
const isModalOpen = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const imageInput = ref(null);
const existingImageUrl = ref("");
const imagePreviewUrl = ref("");
const imageError = ref("");
const logoInput = ref(null);
const existingLogoUrl = ref("");
const logoPreviewUrl = ref("");
const logoError = ref("");

const isCommitteeModalOpen = ref(false);
const committeeCompetitionId = ref(null);
const committeeEditingId = ref(null);
const committeePhotoInput = ref(null);
const committeeExistingPhotoUrl = ref("");
const committeePhotoPreviewUrl = ref("");
const committeePhotoError = ref("");

const tiposCompetencia = [
  { value: "Nacional", label: "Nacional" },
  { value: "Internacional", label: "Internacional" },
];

const acceptedImageTypes = ["image/jpeg", "image/png", "image/webp"];

const form = useForm({
  nombre: "",
  fecha_inicio: "",
  fecha_fin: "",
  descripcion: "",
  enlace_evento: "",
  tipo_competencia: "",
  imagen: null,
  logo: null,
  estado: false,
});

const committeeForm = useForm({
  nombres: "",
  apellidos: "",
  correo: "",
  rol_comite: "",
  foto: null,
  orden: 0,
  estado: true,
});

const fechaErrors = ref({ inicio: "", fin: "" });
const enlaceError = ref("");
const committeeTouchedFields = ref({
  nombres: false,
  apellidos: false,
  correo: false,
  rol_comite: false,
});
const committeeNamePattern = /^[\p{L}\s]+$/u;
const committeeEmailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const formatFechaRango = (inicio, fin) => {
  if (!inicio) return "Fecha por definir";

  const opts = { day: "2-digit", month: "short", year: "numeric" };
  const iStr = new Date(inicio).toLocaleDateString("es-EC", opts);
  if (!fin || fin === inicio) return iStr;

  const fStr = new Date(fin).toLocaleDateString("es-EC", opts);
  return `Del ${iStr} al ${fStr}`;
};

const tipoBadgeClass = (tipo) => {
  const t = (tipo || "").toLowerCase();
  if (t === "nacional") return "bg-blue-50 text-blue-700";
  if (t === "internacional") return "bg-purple-50 text-purple-700";
  return "bg-slate-100 text-slate-600";
};

const filteredCompetitions = computed(() => {
  if (!searchTerm.value.trim()) return competencias.value;

  const term = searchTerm.value.toLowerCase();
  return competencias.value.filter((c) => {
    const nombre = (c.nombre ?? "").toLowerCase();
    const tipo = (c.tipo_competencia ?? "").toLowerCase();
    const desc = (c.descripcion ?? "").toLowerCase();
    return nombre.includes(term) || tipo.includes(term) || desc.includes(term);
  });
});

const previewImageUrl = computed(() => imagePreviewUrl.value || existingImageUrl.value || "");
const previewLogoUrl = computed(() => logoPreviewUrl.value || existingLogoUrl.value || "");
const committeePreviewPhotoUrl = computed(
  () => committeePhotoPreviewUrl.value || committeeExistingPhotoUrl.value || ""
);

const selectedCommitteeCompetition = computed(() =>
  competencias.value.find((item) => Number(item.id) === Number(committeeCompetitionId.value))
);

const selectedCommitteeMembers = computed(
  () => selectedCommitteeCompetition.value?.comite_organizadores ?? []
);

const isFormValid = computed(() => {
  const camposRequeridos =
    form.nombre.trim() &&
    form.fecha_inicio &&
    form.fecha_fin &&
    form.enlace_evento.trim() &&
    form.tipo_competencia;

  const sinErrores =
    !fechaErrors.value.inicio &&
    !fechaErrors.value.fin &&
    !enlaceError.value &&
    !imageError.value &&
    !logoError.value;

  return !!(camposRequeridos && sinErrores);
});


const isCommitteeFormValid = computed(() => {
  const committeeFieldErrors = {};
  const nombres = (committeeForm.nombres || "").trim();
  const apellidos = (committeeForm.apellidos || "").trim();
  const correo = (committeeForm.correo || "").trim();
  const rolComite = (committeeForm.rol_comite || "").trim();

  if (!nombres) {
    committeeFieldErrors.nombres = "El nombre es obligatorio.";
  } else if (!committeeNamePattern.test(nombres)) {
    committeeFieldErrors.nombres = "El nombre solo puede contener letras y espacios.";
  }

  if (!apellidos) {
    committeeFieldErrors.apellidos = "El apellido es obligatorio.";
  } else if (!committeeNamePattern.test(apellidos)) {
    committeeFieldErrors.apellidos = "El apellido solo puede contener letras y espacios.";
  }

  if (!correo) {
    committeeFieldErrors.correo = "El correo electrónico es obligatorio.";
  } else if (!committeeEmailPattern.test(correo)) {
    committeeFieldErrors.correo = "Ingresa un correo electrónico válido.";
  }

  if (!rolComite) {
    committeeFieldErrors.rol_comite = "El rol dentro del comité es obligatorio.";
  } else if (!committeeNamePattern.test(rolComite)) {
    committeeFieldErrors.rol_comite = "El rol dentro del comité solo puede contener letras y espacios.";
  }

  return !!(
    committeeForm.nombres.trim() &&
    committeeForm.apellidos.trim() &&
    committeeForm.correo.trim() &&
    committeeForm.rol_comite.trim() &&
    Object.keys(committeeFieldErrors).length === 0 &&
    !committeePhotoError.value
  );
});

const committeeFieldErrors = computed(() => {
  const errors = {};
  const nombres = (committeeForm.nombres || "").trim();
  const apellidos = (committeeForm.apellidos || "").trim();
  const correo = (committeeForm.correo || "").trim();
  const rolComite = (committeeForm.rol_comite || "").trim();

  if (!nombres) {
    errors.nombres = "El nombre es obligatorio.";
  } else if (!committeeNamePattern.test(nombres)) {
    errors.nombres = "El nombre solo puede contener letras y espacios.";
  }

  if (!apellidos) {
    errors.apellidos = "El apellido es obligatorio.";
  } else if (!committeeNamePattern.test(apellidos)) {
    errors.apellidos = "El apellido solo puede contener letras y espacios.";
  }

  if (!correo) {
    errors.correo = "El correo electrónico es obligatorio.";
  } else if (!committeeEmailPattern.test(correo)) {
    errors.correo = "Ingresa un correo electrónico válido.";
  }

  if (!rolComite) {
    errors.rol_comite = "El rol dentro del comité es obligatorio.";
  } else if (!committeeNamePattern.test(rolComite)) {
    errors.rol_comite = "El rol dentro del comité solo puede contener letras y espacios.";
  }

  return errors;
});

const visibleCommitteeFieldError = (field) => {
  return committeeTouchedFields.value[field]
    ? committeeFieldErrors.value[field] || committeeForm.errors[field]
    : committeeForm.errors[field];
};

const markCommitteeTouched = (field) => {
  committeeTouchedFields.value[field] = true;
  if (committeeForm.errors[field]) committeeForm.clearErrors(field);
};

const sanitizeCommitteeName = (field) => {
  committeeForm[field] = (committeeForm[field] || "").replace(/[^\p{L}\s]/gu, "");
  if (committeeForm.errors[field]) committeeForm.clearErrors(field);
};

const sanitizeCommitteeEmail = () => {
  committeeForm.correo = (committeeForm.correo || "").replace(/\s/g, "").toLowerCase();
  if (committeeForm.errors.correo) committeeForm.clearErrors("correo");
};

const sanitizeCommitteeRole = () => {
  committeeForm.rol_comite = (committeeForm.rol_comite || "").replace(/[^\p{L}\s]/gu, "");
  if (committeeForm.errors.rol_comite) committeeForm.clearErrors("rol_comite");
};

const validarFechas = () => {
  const hoy = new Date().toISOString().split("T")[0];
  fechaErrors.value.inicio = "";
  fechaErrors.value.fin = "";

  if (form.fecha_inicio && form.fecha_inicio < hoy) {
    fechaErrors.value.inicio = "La fecha de inicio debe ser mayor a la actual.";
  }

  if (form.fecha_inicio && form.fecha_fin && form.fecha_fin < form.fecha_inicio) {
    fechaErrors.value.fin = "La fecha de fin debe ser igual o mayor que la fecha de inicio.";
  }
};

const validarEnlace = () => {
  enlaceError.value = "";
  const val = (form.enlace_evento || "").trim();

  if (!val) {
    enlaceError.value = "El enlace del evento es obligatorio.";
    return;
  }

  let url = val;
  if (!/^https?:\/\//i.test(url)) url = `https://${url}`;

  try {
    const parsed = new URL(url);
    if (!parsed.hostname || !parsed.hostname.includes(".")) {
      throw new Error("Dominio inválido");
    }
  } catch {
    enlaceError.value =
      "Ingresa un enlace válido. Ejemplo: https://riotronic.espoch.edu.ec/evento";
  }
};

const revokePreviewUrl = () => {
  if (imagePreviewUrl.value?.startsWith("blob:")) {
    URL.revokeObjectURL(imagePreviewUrl.value);
  }
  imagePreviewUrl.value = "";
};

const revokeLogoPreviewUrl = () => {
  if (logoPreviewUrl.value?.startsWith("blob:")) {
    URL.revokeObjectURL(logoPreviewUrl.value);
  }
  logoPreviewUrl.value = "";
};

const revokeCommitteePreviewUrl = () => {
  if (committeePhotoPreviewUrl.value?.startsWith("blob:")) {
    URL.revokeObjectURL(committeePhotoPreviewUrl.value);
  }
  committeePhotoPreviewUrl.value = "";
};

const resetForm = () => {
  form.reset();
  form.clearErrors();
  fechaErrors.value = { inicio: "", fin: "" };
  enlaceError.value = "";
  imageError.value = "";
  logoError.value = "";
  existingImageUrl.value = "";
  existingLogoUrl.value = "";
  revokePreviewUrl();
  revokeLogoPreviewUrl();

  if (imageInput.value) {
    imageInput.value.value = "";
  }

  if (logoInput.value) {
    logoInput.value.value = "";
  }
};

const resetCommitteeForm = () => {
  committeeForm.reset();
  committeeForm.clearErrors();
  committeeForm.estado = true;
  committeeForm.orden = 0;
  committeeEditingId.value = null;
  committeeExistingPhotoUrl.value = "";
  committeePhotoError.value = "";
  committeeTouchedFields.value = {
    nombres: false,
    apellidos: false,
    correo: false,
    rol_comite: false,
  };
  revokeCommitteePreviewUrl();

  if (committeePhotoInput.value) {
    committeePhotoInput.value.value = "";
  }
};

const onImageChange = (event) => {
  const file = event.target.files?.[0] || null;
  imageError.value = "";
  form.imagen = null;
  revokePreviewUrl();

  if (!file) return;

  if (!acceptedImageTypes.includes(file.type)) {
    imageError.value = "Solo se permiten imágenes JPG, JPEG, PNG o WEBP.";
    if (imageInput.value) imageInput.value.value = "";
    return;
  }

  form.imagen = file;
  imagePreviewUrl.value = URL.createObjectURL(file);
};

const onLogoChange = (event) => {
  const file = event.target.files?.[0] || null;
  logoError.value = "";
  form.logo = null;
  revokeLogoPreviewUrl();

  if (!file) return;

  if (!acceptedImageTypes.includes(file.type)) {
    logoError.value = "Solo se permiten imágenes JPG, JPEG, PNG o WEBP.";
    if (logoInput.value) logoInput.value.value = "";
    return;
  }

  form.logo = file;
  logoPreviewUrl.value = URL.createObjectURL(file);
};

const onCommitteePhotoChange = (event) => {
  const file = event.target.files?.[0] || null;
  committeePhotoError.value = "";
  committeeForm.foto = null;
  revokeCommitteePreviewUrl();

  if (!file) return;

  if (!acceptedImageTypes.includes(file.type)) {
    committeePhotoError.value = "Solo se permiten imagenes JPG, JPEG, PNG o WEBP.";
    if (committeePhotoInput.value) committeePhotoInput.value.value = "";
    return;
  }

  committeeForm.foto = file;
  committeePhotoPreviewUrl.value = URL.createObjectURL(file);
};

const openModal = () => {
  isEditing.value = false;
  editingId.value = null;
  resetForm();
  isModalOpen.value = true;
};

const openEditModal = (competition) => {
  isEditing.value = true;
  editingId.value = competition?.id ?? null;

  resetForm();

  form.nombre = competition?.nombre ?? "";
  form.fecha_inicio = (competition?.fecha_inicio ?? "").slice(0, 10);
  form.fecha_fin = (competition?.fecha_fin ?? "").slice(0, 10);
  form.descripcion = competition?.descripcion ?? "";
  form.enlace_evento = competition?.enlace_evento ?? "";
  form.tipo_competencia = competition?.tipo_competencia ?? "";
  form.estado = !!competition?.estado;
  existingImageUrl.value = competition?.imagen_url ?? "";
  existingLogoUrl.value = competition?.logo_url ?? "";

  isModalOpen.value = true;
};

const closeModal = () => {
  isModalOpen.value = false;
  resetForm();
};

const openCommitteeModal = (competition) => {
  committeeCompetitionId.value = competition?.id ?? null;
  resetCommitteeForm();
  isCommitteeModalOpen.value = true;
};

const closeCommitteeModal = () => {
  isCommitteeModalOpen.value = false;
  committeeCompetitionId.value = null;
  resetCommitteeForm();
};

const openCommitteeEdit = (member) => {
  committeeEditingId.value = member?.id ?? null;
  committeeForm.nombres = member?.nombres ?? "";
  committeeForm.apellidos = member?.apellidos ?? "";
  committeeForm.correo = member?.correo ?? "";
  committeeForm.rol_comite = member?.rol_comite ?? "";
  committeeForm.orden = Number(member?.orden ?? 0);
  committeeForm.estado = !!member?.estado;
  committeeForm.foto = null;
  committeeExistingPhotoUrl.value = member?.foto_url ?? "";
  committeePhotoError.value = "";
  committeeTouchedFields.value = {
    nombres: false,
    apellidos: false,
    correo: false,
    rol_comite: false,
  };
  revokeCommitteePreviewUrl();

  if (committeePhotoInput.value) {
    committeePhotoInput.value.value = "";
  }
};

const openDeleteConfirm = (competition) => {
  deletingCompetition.value = competition;
  isDeleteConfirmOpen.value = true;
};

const closeDeleteConfirm = () => {
  isDeleteConfirmOpen.value = false;
  deletingCompetition.value = null;
};

const submitForm = () => {
  validarFechas();
  validarEnlace();
  if (!isFormValid.value) return;

  const options = {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      closeModal();
    },
    onError: (errors) => {
      if (errors?.imagen && !imageError.value) {
        imageError.value = errors.imagen;
      }
      if (errors?.logo && !logoError.value) {
        logoError.value = errors.logo;
      }
    },
  };

  if (isEditing.value) {
    if (!editingId.value) return;

    form
      .transform((data) => ({
        ...data,
        _method: "put",
      }))
      .post(`/admin/competencias/${editingId.value}`, options);

    return;
  }

  form.transform((data) => data).post("/admin/competencias", options);
};

const submitCommitteeForm = () => {
  committeeTouchedFields.value = {
    nombres: true,
    apellidos: true,
    correo: true,
    rol_comite: true,
  };

  if (!selectedCommitteeCompetition.value || !isCommitteeFormValid.value) return;

  const competitionId = selectedCommitteeCompetition.value.id;
  const options = {
    forceFormData: true,
    preserveScroll: true,
    only: ["competencias", "flash"],
    onSuccess: () => {
      resetCommitteeForm();
    },
    onError: (errors) => {
      if (errors?.foto && !committeePhotoError.value) {
        committeePhotoError.value = errors.foto;
      }
    },
  };

  if (committeeEditingId.value) {
    committeeForm
      .transform((data) => ({
        ...data,
        _method: "put",
      }))
      .post(
        `/admin/competencias/${competitionId}/comite/${committeeEditingId.value}`,
        options
      );

    return;
  }

  committeeForm
    .transform((data) => data)
    .post(`/admin/competencias/${competitionId}/comite`, options);
};

const deleteCompetition = () => {
  const competition = deletingCompetition.value;
  if (!competition?.id) return;

  router.delete(`/admin/competencias/${competition.id}`, {
    preserveScroll: true,
    onSuccess: (responsePage) => {
      closeDeleteConfirm();

      const success = responsePage?.props?.flash?.success;
      const error = responsePage?.props?.flash?.error;
      if (success || error) {
        showFeedback(success || error, success ? "success" : "error");
      }
    },
    onError: () => {
      closeDeleteConfirm();
      showFeedback("No se pudo completar la eliminación de la competencia.", "error");
    },
  });
};

const openCompetitionLink = (competition) => {
  if (!competition.enlace_evento) return;

  let url = competition.enlace_evento.trim();
  if (!/^https?:\/\//i.test(url)) url = `https://${url}`;
  window.open(url, "_blank");
};

const toggleCompetencia = (id) => {
  router.patch(`/admin/competencias/${id}/toggle`, {}, { preserveScroll: true });
};

watch(() => form.fecha_inicio, validarFechas);
watch(() => form.fecha_fin, validarFechas);
watch(() => form.enlace_evento, validarEnlace);

watch([isModalOpen, isDeleteConfirmOpen, isCommitteeModalOpen], ([formOpen, deleteOpen, committeeOpen]) => {
  document.body.style.overflow = formOpen || deleteOpen || committeeOpen ? "hidden" : "";
});

onBeforeUnmount(() => {
  document.body.style.overflow = "";
  clearTimeout(feedbackTimer);
  revokePreviewUrl();
  revokeLogoPreviewUrl();
  revokeCommitteePreviewUrl();
});
</script>

<template>
  <div class="space-y-5 px-3 py-5 sm:space-y-6 sm:px-6 sm:py-6 lg:px-4">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">
          Gestión de Competencias de Robótica
        </h1>
        <p class="text-sm text-slate-500">
          Administra y registra competencias nacionales e internacionales de robótica.
        </p>
      </div>

      <button
        @click="openModal"
        class="w-full rounded-xl bg-blue-600 px-5 py-2 font-medium text-white shadow hover:bg-blue-700 sm:w-auto"
      >
        + Nueva Competencia
      </button>
    </header>

    <div class="relative">
      <span class="absolute inset-y-0 left-3 flex items-center">
        <MagnifyingGlassIcon class="h-5 w-5 text-slate-400" />
      </span>

      <input
        v-model="searchTerm"
        placeholder="Buscar por nombre o tipo (Nacional / Internacional)..."
        class="w-full rounded-2xl border bg-white py-2.5 pl-11 pr-4 text-sm text-slate-700 shadow placeholder:text-slate-400"
      />
    </div>

    <section class="grid grid-cols-1 gap-4 sm:gap-5 lg:grid-cols-2">
      <article
        v-for="c in filteredCompetitions"
        :key="c.id"
        class="overflow-hidden rounded-3xl border bg-white shadow-sm transition hover:shadow-md"
      >
        <div class="relative h-44 w-full bg-slate-100">
          <img
            v-if="c.imagen_url"
            :src="c.imagen_url"
            :alt="`Portada de ${c.nombre}`"
            class="h-full w-full object-cover"
          />
          <div
            v-else
            class="flex h-full w-full items-end bg-gradient-to-br from-slate-100 via-slate-200 to-slate-300 p-5"
          >
            <div class="rounded-2xl bg-white/70 px-4 py-3 backdrop-blur">
              <p class="mt-1 text-lg font-semibold text-slate-800">
                Competencia sin portada
              </p>
            </div>
          </div>

          <div class="absolute left-3 right-3 top-3 flex flex-wrap justify-end gap-2 sm:left-auto sm:right-4 sm:top-4 sm:flex-nowrap">
            <button
              type="button"
              @click.stop="toggleCompetencia(c.id)"
              class="inline-flex items-center rounded-xl border px-3 py-1.5 text-xs font-medium backdrop-blur transition sm:px-4 sm:py-2 sm:text-sm"
              :class="
                c.estado
                  ? 'border-emerald-200 bg-emerald-50/90 text-emerald-700 hover:bg-emerald-100'
                  : 'border-slate-200 bg-white/90 text-slate-700 hover:bg-slate-100'
              "
            >
              {{ c.estado ? "Evento principal" : "No principal" }}
            </button>

            <button
              @click.stop="openEditModal(c)"
              class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white/90 text-slate-500 backdrop-blur hover:bg-slate-50"
            >
              <PencilSquareIcon class="h-5 w-5" />
            </button>

            <button
              @click.stop="openDeleteConfirm(c)"
              class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-red-100 bg-red-50/95 text-red-500 backdrop-blur hover:bg-red-100"
            >
              <TrashIcon class="h-5 w-5" />
            </button>
          </div>
        </div>

        <div class="p-4 sm:p-5">
          <h3 class="mb-2 break-words pr-0 text-base font-bold text-slate-900 line-clamp-2 sm:pr-16 sm:text-lg">
            {{ c.nombre }}
          </h3>

          <div class="mb-2">
            <span
              class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
              :class="tipoBadgeClass(c.tipo_competencia)"
            >
              {{ c.tipo_competencia || "Sin tipo" }}
            </span>
          </div>

          <p class="mb-3 break-words text-sm text-slate-500 line-clamp-3">
            {{ c.descripcion }}
          </p>

          <div class="space-y-2 text-sm">
            <div class="flex items-center gap-2 text-slate-700">
              <CalendarDaysIcon class="h-4 w-4 text-slate-400" />
              <span>{{ formatFechaRango(c.fecha_inicio, c.fecha_fin) }}</span>
            </div>

            <div v-if="c.enlace_evento" class="flex items-center gap-2">
              <LinkIcon class="h-4 w-4 text-slate-400" />
              <button
                @click.stop="openCompetitionLink(c)"
                class="text-sm text-blue-700 hover:underline"
              >
                Abrir enlace del evento
              </button>
            </div>
          </div>

          <div class="mt-5 border-t border-slate-100 pt-4">
            <button
              type="button"
              @click.stop="openCommitteeModal(c)"
              class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-blue-100 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100"
            >
              <UserGroupIcon class="h-5 w-5" />
              Comité organizador
              <span class="rounded-full bg-white px-2 py-0.5 text-xs text-blue-700">
                {{ c.comite_organizadores?.length ?? 0 }}
              </span>
            </button>
          </div>
        </div>
      </article>
    </section>

    <teleport to="body">
      <transition name="fade">
        <div
          v-if="isModalOpen"
          class="fixed inset-0 z-[80] flex items-center justify-center bg-black/40 backdrop-blur-sm"
        >
          <div class="w-full max-w-3xl rounded-3xl bg-white p-4 shadow-xl sm:p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
              <h2 class="text-lg font-semibold sm:text-xl">
                {{ isEditing ? "Editar Competencia" : "Crear Nueva Competencia" }}
              </h2>
              <button
                @click="closeModal"
                class="rounded-full p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
              >
                <XMarkIcon class="h-6 w-6" />
              </button>
            </div>

            <div class="modal-scroll space-y-4">
              <div>
                <label class="text-xs font-medium">Nombre de la competencia</label>
                <input
                  v-model="form.nombre"
                  type="text"
                  class="w-full rounded-2xl border bg-slate-50 px-3 py-2"
                  placeholder="Ej: Torneo Nacional RIOTRONIC 2025"
                />
                <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-600">
                  {{ form.errors.nombre }}
                </p>
              </div>

              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <label class="text-xs font-medium">Fecha de inicio</label>
                  <input
                    v-model="form.fecha_inicio"
                    type="date"
                    class="w-full rounded-2xl border bg-slate-50 px-3 py-2"
                  />
                  <p v-if="fechaErrors.inicio" class="text-xs text-red-600">
                    {{ fechaErrors.inicio }}
                  </p>
                  <p v-else-if="form.errors.fecha_inicio" class="mt-1 text-xs text-red-600">
                    {{ form.errors.fecha_inicio }}
                  </p>
                </div>

                <div>
                  <label class="text-xs font-medium">Fecha de fin</label>
                  <input
                    v-model="form.fecha_fin"
                    type="date"
                    class="w-full rounded-2xl border bg-slate-50 px-3 py-2"
                  />
                  <p v-if="fechaErrors.fin" class="text-xs text-red-600">
                    {{ fechaErrors.fin }}
                  </p>
                  <p v-else-if="form.errors.fecha_fin" class="mt-1 text-xs text-red-600">
                    {{ form.errors.fecha_fin }}
                  </p>
                </div>
              </div>

              <div>
                <label class="text-xs font-medium">Enlace del evento</label>
                <input
                  v-model="form.enlace_evento"
                  type="url"
                  placeholder="https://riotronic.espoch.edu.ec/"
                  class="w-full rounded-2xl border bg-slate-50 px-3 py-2"
                />
                <p v-if="enlaceError" class="mt-1 text-xs text-red-600">
                  {{ enlaceError }}
                </p>
                <p v-else-if="form.errors.enlace_evento" class="mt-1 text-xs text-red-600">
                  {{ form.errors.enlace_evento }}
                </p>
                <p v-else class="mt-1 text-[11px] text-slate-400">
                  Ingresa la página oficial, formulario externo o landing del evento
                  (ej. https://riotronic.espoch.edu.ec).
                </p>
              </div>

              <div>
                <label class="text-xs font-medium">Descripción</label>
                <textarea
                  v-model="form.descripcion"
                  rows="3"
                  class="w-full rounded-2xl border bg-slate-50 px-3 py-2"
                ></textarea>
                <p v-if="form.errors.descripcion" class="mt-1 text-xs text-red-600">
                  {{ form.errors.descripcion }}
                </p>
              </div>

              <div>
                <label class="text-xs font-medium">Tipo de competencia</label>
                <select
                  v-model="form.tipo_competencia"
                  class="w-full rounded-2xl border bg-slate-50 px-3 py-2"
                >
                  <option value="" disabled>Selecciona una opción</option>
                  <option
                    v-for="t in tiposCompetencia"
                    :key="t.value"
                    :value="t.value"
                  >
                    {{ t.label }}
                  </option>
                </select>
                <p v-if="form.errors.tipo_competencia" class="mt-1 text-xs text-red-600">
                  {{ form.errors.tipo_competencia }}
                </p>
              </div>

              <div class="space-y-3">
                <div>
                  <label class="text-xs font-medium">Imagen / portada</label>
                  <input
                    ref="imageInput"
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    class="block w-full text-sm text-slate-700 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-white hover:file:bg-slate-800"
                    @change="onImageChange"
                  />
                  <p class="mt-1 text-[11px] text-slate-400">
                    Formatos permitidos: JPG, JPEG, PNG y WEBP. Tamaño máximo: 5 MB.
                  </p>
                  <p v-if="imageError" class="mt-1 text-xs text-red-600">
                    {{ imageError }}
                  </p>
                  <p v-else-if="form.errors.imagen" class="mt-1 text-xs text-red-600">
                    {{ form.errors.imagen }}
                  </p>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                  <div v-if="previewImageUrl" class="h-48 w-full">
                    <img
                      :src="previewImageUrl"
                      alt="Vista previa de la portada"
                      class="h-full w-full object-cover"
                    />
                  </div>
                  <div
                    v-else
                    class="flex h-48 w-full items-center justify-center bg-gradient-to-br from-slate-100 via-slate-200 to-slate-300"
                  >
                    <div class="text-center text-slate-500">
                      <PhotoIcon class="mx-auto h-10 w-10 text-slate-400" />
                      <p class="mt-2 text-sm font-medium">Sin imagen seleccionada</p>
                      <p class="text-xs text-slate-400">
                        La tarjeta mostrará un placeholder elegante.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <div class="space-y-3">
                <div>
                  <label class="text-xs font-medium">Logo del evento</label>
                  <input
                    ref="logoInput"
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    class="block w-full text-sm text-slate-700 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-white hover:file:bg-slate-800"
                    @change="onLogoChange"
                  />
                  <p class="mt-1 text-[11px] text-slate-400">
                    Este logo se usará en el formato formal de reclamos.
                  </p>
                  <p v-if="logoError" class="mt-1 text-xs text-red-600">
                    {{ logoError }}
                  </p>
                  <p v-else-if="form.errors.logo" class="mt-1 text-xs text-red-600">
                    {{ form.errors.logo }}
                  </p>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                  <div v-if="previewLogoUrl" class="flex h-32 w-full items-center justify-center bg-white p-4">
                    <img
                      :src="previewLogoUrl"
                      alt="Vista previa del logo"
                      class="max-h-full max-w-full object-contain"
                    />
                  </div>
                  <div
                    v-else
                    class="flex h-32 w-full items-center justify-center bg-slate-50"
                  >
                    <div class="text-center text-slate-500">
                      <PhotoIcon class="mx-auto h-8 w-8 text-slate-400" />
                      <p class="mt-2 text-sm font-medium">Sin logo seleccionado</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-4 flex flex-col-reverse gap-2 border-t pt-4 sm:flex-row sm:justify-end sm:gap-3">
              <button
                @click="closeModal"
                class="w-full rounded-xl border px-4 py-2 text-slate-700 hover:bg-slate-50 sm:w-auto"
              >
                Cancelar
              </button>

              <button
                @click="submitForm"
                :disabled="!isFormValid || form.processing"
                :class="[
                  'w-full rounded-xl px-5 py-2 font-semibold sm:w-auto',
                  !isFormValid || form.processing
                    ? 'cursor-not-allowed bg-slate-300 text-slate-500'
                    : 'bg-blue-600 text-white hover:bg-blue-700',
                ]"
              >
                {{
                  form.processing
                    ? "Guardando..."
                    : isEditing
                      ? "Actualizar Competencia"
                      : "Crear Competencia"
                }}
              </button>
            </div>
          </div>
        </div>
      </transition>
    </teleport>

    <teleport to="body">
      <transition name="fade">
        <div
          v-if="isCommitteeModalOpen"
          class="fixed inset-0 z-[82] flex items-center justify-center bg-black/40 px-4 py-6 backdrop-blur-sm"
          @click.self="closeCommitteeModal"
        >
          <div class="flex max-h-[92vh] w-full max-w-6xl flex-col rounded-3xl bg-white p-4 shadow-xl sm:p-6">
            <div class="mb-5 flex items-start justify-between gap-4">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">
                  {{ selectedCommitteeCompetition?.nombre || "Competencia" }}
                </p>
                <h2 class="text-lg font-semibold text-slate-900 sm:text-xl">
                  Comité organizador
                </h2>
              </div>

              <button
                @click="closeCommitteeModal"
                class="rounded-full p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                aria-label="Cerrar"
              >
                <XMarkIcon class="h-6 w-6" />
              </button>
            </div>

            <div class="grid min-h-0 grid-cols-1 gap-4 overflow-y-auto pr-1 sm:gap-6 lg:grid-cols-[1fr_380px]">
              <section class="space-y-3">
                <div
                  v-if="selectedCommitteeMembers.length === 0"
                  class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center"
                >
                  <UserGroupIcon class="mx-auto h-10 w-10 text-slate-300" />
                  <p class="mt-3 text-sm font-semibold text-slate-700">
                    Aún no hay integrantes registrados.
                  </p>
                  <p class="mt-1 text-xs text-slate-500">
                    Agrega integrantes para mostrarlos luego en Contacto.
                  </p>
                </div>

                <article
                  v-for="member in selectedCommitteeMembers"
                  :key="member.id"
                  class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center"
                >
                  <div class="h-16 w-16 shrink-0 overflow-hidden rounded-2xl bg-slate-100">
                    <img
                      v-if="member.foto_url"
                      :src="member.foto_url"
                      :alt="`${member.nombres} ${member.apellidos}`"
                      class="h-full w-full object-cover"
                    />
                    <div v-else class="flex h-full w-full items-center justify-center text-slate-400">
                      <UserGroupIcon class="h-7 w-7" />
                    </div>
                  </div>

                  <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                      <h3 class="break-words text-base font-semibold text-slate-900">
                        {{ member.nombres }} {{ member.apellidos }}
                      </h3>
                      <span
                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="member.estado ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                      >
                        {{ member.estado ? "Activo" : "Inactivo" }}
                      </span>
                    </div>
                    <p class="mt-1 text-sm font-medium text-blue-700">
                      {{ member.rol_comite }}
                    </p>
                    <p v-if="member.correo" class="mt-1 break-words text-sm text-slate-500">
                      {{ member.correo }}
                    </p>
                    <p class="mt-1 text-xs text-slate-400">
                      Orden: {{ member.orden }}
                    </p>
                  </div>

                  <div class="flex shrink-0 flex-wrap gap-2">
                    <button
                      type="button"
                      @click="openCommitteeEdit(member)"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50"
                      aria-label="Editar integrante"
                    >
                      <PencilSquareIcon class="h-5 w-5" />
                    </button>
                  </div>
                </article>
              </section>

              <aside class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <h3 class="text-base font-semibold text-slate-900">
                  {{ committeeEditingId ? "Editar integrante" : "Nuevo integrante" }}
                </h3>

                <div class="mt-4 space-y-4">
                  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    <div>
                      <label class="text-xs font-medium text-slate-700">Nombre(s)</label>
                      <input
                        v-model="committeeForm.nombres"
                        type="text"
                        class="mt-1 w-full rounded-xl border bg-white px-3 py-2 text-sm"
                        :class="visibleCommitteeFieldError('nombres') ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-slate-200'"
                        @input="sanitizeCommitteeName('nombres')"
                        @blur="markCommitteeTouched('nombres')"
                      />
                      <p v-if="visibleCommitteeFieldError('nombres')" class="mt-1 text-xs text-red-600">
                        {{ visibleCommitteeFieldError('nombres') }}
                      </p>
                    </div>

                    <div>
                      <label class="text-xs font-medium text-slate-700">Apellido(s)</label>
                      <input
                        v-model="committeeForm.apellidos"
                        type="text"
                        class="mt-1 w-full rounded-xl border bg-white px-3 py-2 text-sm"
                        :class="visibleCommitteeFieldError('apellidos') ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-slate-200'"
                        @input="sanitizeCommitteeName('apellidos')"
                        @blur="markCommitteeTouched('apellidos')"
                      />
                      <p v-if="visibleCommitteeFieldError('apellidos')" class="mt-1 text-xs text-red-600">
                        {{ visibleCommitteeFieldError('apellidos') }}
                      </p>
                    </div>
                  </div>

                  <div>
                    <label class="text-xs font-medium text-slate-700">Correo electrónico</label>
                    <input
                      v-model="committeeForm.correo"
                      type="email"
                      class="mt-1 w-full rounded-xl border bg-white px-3 py-2 text-sm"
                      :class="visibleCommitteeFieldError('correo') ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-slate-200'"
                      placeholder="correo@espoch.edu.ec"
                      @input="sanitizeCommitteeEmail"
                      @blur="markCommitteeTouched('correo')"
                    />
                    <p v-if="visibleCommitteeFieldError('correo')" class="mt-1 text-xs text-red-600">
                      {{ visibleCommitteeFieldError('correo') }}
                    </p>
                  </div>

                  <div>
                    <label class="text-xs font-medium text-slate-700">Rol dentro del comité</label>
                    <input
                      v-model="committeeForm.rol_comite"
                      type="text"
                      class="mt-1 w-full rounded-xl border bg-white px-3 py-2 text-sm"
                      :class="visibleCommitteeFieldError('rol_comite') ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-slate-200'"
                      placeholder="Coordinador general"
                      @input="sanitizeCommitteeRole"
                      @blur="markCommitteeTouched('rol_comite')"
                    />
                    <p v-if="visibleCommitteeFieldError('rol_comite')" class="mt-1 text-xs text-red-600">
                      {{ visibleCommitteeFieldError('rol_comite') }}
                    </p>
                  </div>

                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="text-xs font-medium text-slate-700">Orden de visualización</label>
                      <input
                        v-model.number="committeeForm.orden"
                        type="number"
                        min="0"
                        class="mt-1 w-full rounded-xl border bg-white px-3 py-2 text-sm"
                      />
                      <p v-if="committeeForm.errors.orden" class="mt-1 text-xs text-red-600">
                        {{ committeeForm.errors.orden }}
                      </p>
                    </div>

                    <label class="flex items-end gap-2 pb-2 text-sm font-medium text-slate-700">
                      <input
                        v-model="committeeForm.estado"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-blue-600"
                      />
                      Activo
                    </label>
                  </div>

                  <div>
                    <label class="text-xs font-medium text-slate-700">Foto</label>
                    <input
                      ref="committeePhotoInput"
                      type="file"
                      accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                      class="mt-1 block w-full text-sm text-slate-700 file:mr-3 file:rounded-xl file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-white hover:file:bg-slate-800"
                      @change="onCommitteePhotoChange"
                    />
                    <p v-if="committeePhotoError" class="mt-1 text-xs text-red-600">
                      {{ committeePhotoError }}
                    </p>
                    <p v-else-if="committeeForm.errors.foto" class="mt-1 text-xs text-red-600">
                      {{ committeeForm.errors.foto }}
                    </p>
                    <p
                      v-if="committeeEditingId && committeeExistingPhotoUrl && !committeeForm.foto"
                      class="mt-1 text-xs text-slate-500"
                    >
                      La foto actual se conservara si no seleccionas una nueva.
                    </p>
                  </div>

                  <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div v-if="committeePreviewPhotoUrl" class="h-44 w-full">
                      <img
                        :src="committeePreviewPhotoUrl"
                        alt="Vista previa de integrante"
                        class="h-full w-full object-cover"
                      />
                    </div>
                    <div v-else class="flex h-44 w-full items-center justify-center text-slate-400">
                      <PhotoIcon class="h-10 w-10" />
                    </div>
                  </div>
                </div>

                <div class="mt-5 flex flex-wrap justify-end gap-2 border-t pt-4">
                  <button
                    type="button"
                    @click="resetCommitteeForm"
                    class="rounded-xl border px-4 py-2 text-sm text-slate-700 hover:bg-white"
                  >
                    {{ committeeEditingId ? "Cancelar edición" : "Limpiar" }}
                  </button>
                  <button
                    type="button"
                    @click="submitCommitteeForm"
                    :disabled="!isCommitteeFormValid || committeeForm.processing"
                    :class="[
                      'rounded-xl px-4 py-2 text-sm font-semibold',
                      !isCommitteeFormValid || committeeForm.processing
                        ? 'cursor-not-allowed bg-slate-300 text-slate-500'
                        : 'bg-blue-600 text-white hover:bg-blue-700',
                    ]"
                  >
                    {{
                      committeeForm.processing
                        ? "Guardando..."
                        : committeeEditingId
                          ? "Actualizar"
                          : "Agregar"
                    }}
                  </button>
                </div>
              </aside>
            </div>
          </div>
        </div>
      </transition>
    </teleport>

    <teleport to="body">
      <transition name="fade">
        <div
          v-if="isDeleteConfirmOpen"
          class="fixed inset-0 z-[85] flex items-center justify-center bg-black/45 backdrop-blur-sm"
          @click.self="closeDeleteConfirm"
        >
          <div class="w-full max-w-md rounded-3xl border bg-white p-4 shadow-2xl sm:p-6">
            <div class="flex items-start gap-4">
              <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-red-100 text-red-600">
                <ExclamationTriangleIcon class="h-6 w-6" />
              </div>

              <div class="flex-1">
                <h3 class="text-lg font-semibold text-slate-900">
                  Confirmar eliminación
                </h3>
                <p class="mt-2 text-sm text-slate-600">
                  ¿Deseas eliminar la competencia
                  <span class="font-semibold text-slate-900">
                    {{ deletingCompetition?.nombre || "seleccionada" }}
                  </span>?
                </p>
                <p class="mt-2 text-xs text-slate-400">
                  Esta acción solo se completará si la competencia no tiene categorías asociadas.
                </p>
              </div>

              <button
                class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                @click="closeDeleteConfirm"
                aria-label="Cerrar"
              >
                <XMarkIcon class="h-5 w-5" />
              </button>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3">
              <button
                class="w-full rounded-xl border px-4 py-2 text-slate-700 hover:bg-slate-50 sm:w-auto"
                @click="closeDeleteConfirm"
              >
                Cancelar
              </button>
              <button
                class="w-full rounded-xl bg-red-600 px-4 py-2 font-semibold text-white hover:bg-red-700 sm:w-auto"
                @click="deleteCompetition"
              >
                Eliminar competencia
              </button>
            </div>
          </div>
        </div>
      </transition>
    </teleport>

    <teleport to="body">
      <transition name="fade">
        <div
          v-if="isFeedbackOpen"
          class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 backdrop-blur-sm"
          @click.self="isFeedbackOpen = false"
        >
          <div class="w-full max-w-md rounded-3xl border bg-white p-4 shadow-2xl sm:p-6">
            <div class="flex items-start gap-3">
              <div
                class="flex h-10 w-10 items-center justify-center rounded-2xl"
                :class="feedbackType === 'success' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
              >
                <span class="text-lg font-bold">{{ feedbackType === "success" ? "OK" : "!" }}</span>
              </div>

              <div class="flex-1">
                <h3 class="text-base font-semibold text-slate-900">
                  {{ feedbackType === "success" ? "Listo" : "Aviso" }}
                </h3>
                <p class="mt-1 text-sm text-slate-600">
                  {{ feedbackMessage }}
                </p>
              </div>

              <button
                class="text-xl leading-none text-slate-400 hover:text-slate-700"
                @click="isFeedbackOpen = false"
                aria-label="Cerrar"
              >
                ×
              </button>
            </div>

            <div class="mt-4 flex justify-end">
              <button
                class="rounded-xl px-4 py-2 font-semibold text-white"
                :class="feedbackType === 'success' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700'"
                @click="isFeedbackOpen = false"
              >
                Aceptar
              </button>
            </div>
          </div>
        </div>
      </transition>
    </teleport>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.modal-scroll {
  max-height: 60vh;
  overflow-y: auto;
  padding-right: 4px;
}

.modal-scroll::-webkit-scrollbar {
  width: 6px;
}

.modal-scroll::-webkit-scrollbar-thumb {
  background-color: #cbd7e163;
  border-radius: 999px;
}
</style>
