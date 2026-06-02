<script setup>
import { computed, reactive, watch } from "vue";
import {
  XMarkIcon,
  InformationCircleIcon,
  ArrowUpTrayIcon,
} from "@heroicons/vue/24/outline";

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  categoria: {
    type: Object,
    default: null,
  },
  initialData: {
    type: Object,
    default: null,
  },
  mode: {
    type: String,
    default: "create",
  },
  backendErrors: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(["close", "submitted", "clear-backend-error"]);

const isEditMode = computed(() => props.mode === "edit");

const form = reactive({
  institucion: "",
  categoria: "",
  equipo: "",
  capitan: "",
  prototipo: "",
  contacto: "+593",
  integrantes: [],
  aceptaTratamiento: false,
});

const maxIntegrantes = computed(() => {
  const value = Number(props.categoria?.max_integrantes ?? 2);
  return Number.isInteger(value) && value >= 1 && value <= 5 ? value : 2;
});

const integrantesAdicionales = computed(() => Math.max(maxIntegrantes.value - 1, 0));

const syncIntegrantesFields = () => {
  const total = integrantesAdicionales.value;
  form.integrantes = Array.from({ length: total }, (_, index) => form.integrantes[index] ?? "");
};

const resetForm = () => {
  const data = props.initialData ?? {};

  form.institucion = data.institucion ?? "";
  form.categoria = props.categoria?.nombre ?? "";
  form.equipo = data.equipo ?? "";
  form.capitan = data.capitan ?? "";
  form.prototipo = data.prototipo ?? "";
  form.contacto = sanitizeContacto(data.contacto ?? "+593");
  form.integrantes = Array.from(
    { length: integrantesAdicionales.value },
    (_, index) => data.integrantes?.[index] ?? ""
  );
  form.aceptaTratamiento = false;
};

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      resetForm();
    }
  }
);

watch(
  () => props.categoria,
  (categoria) => {
    if (props.open) {
      form.categoria = categoria?.nombre ?? "";
      resetForm();
    }
  }
);

watch(maxIntegrantes, () => {
  if (props.open) {
    syncIntegrantesFields();
  }
});

watch(
  () => props.initialData,
  () => {
    if (props.open) {
      resetForm();
    }
  },
  { deep: true }
);

const normalizedForm = computed(() => ({
  institucion: form.institucion.trim(),
  categoria: form.categoria.trim(),
  equipo: form.equipo.trim(),
  capitan: form.capitan.trim(),
  prototipo: form.prototipo.trim(),
  contacto: form.contacto.trim().replace(/\s+/g, ""),
  integrantes: form.integrantes.map((integrante) => String(integrante ?? "").trim()),
}));

const nombreCompetencia = computed(() =>
  props.categoria?.competencia_nombre
    || props.categoria?.competencia?.nombre
    || "la competencia actual"
);

const modalTitle = computed(() =>
  isEditMode.value ? "Editar inscripción" : "Formulario de inscripción"
);

const modalSubtitle = computed(() =>
  isEditMode.value
    ? "Actualiza la información registrada antes de subir el comprobante"
    : "Completa todos los campos para registrar tu equipo"
);

const submitLabel = computed(() =>
  isEditMode.value ? "Guardar cambios" : "Enviar inscripción"
);

const sanitizeContacto = (value) => {
  const digits = String(value ?? "").replace(/\D/g, "").slice(0, 15);
  return `+${digits}`;
};

const onContactoInput = (event) => {
  form.contacto = sanitizeContacto(event.target.value);
};

const prevenirEspacioContacto = (event) => {
  if (event.key === " ") {
    event.preventDefault();
  }
};

const rules = {
  institucion: /^[\p{L}\p{N} .-]+$/u,
  equipo: /^[\p{L}\p{N} -]+$/u,
  nombrePersona: /^[\p{L} ]+$/u,
  prototipo: /^[\p{L}\p{N} .-]+$/u,
  contacto: /^\+\d{1,15}$/,
};

const errors = computed(() => {
  const data = normalizedForm.value;
  const result = {};

  if (data.institucion && !rules.institucion.test(data.institucion)) {
    result.institucion = "Permite letras, números, espacios, puntos y guiones.";
  }

  if (data.equipo && !rules.equipo.test(data.equipo)) {
    result.equipo = "Permite letras, números, espacios y guiones.";
  }

  if (data.capitan && !rules.nombrePersona.test(data.capitan)) {
    result.capitan = "Solo permite letras y espacios.";
  }

  if (data.prototipo && !rules.prototipo.test(data.prototipo)) {
    result.prototipo = "Permite letras, números, espacios, guiones y puntos.";
  }

  if (data.contacto && !rules.contacto.test(data.contacto)) {
    result.contacto = "Debe iniciar con + y tener máximo 15 dígitos, sin espacios.";
  }

  result.integrantes = data.integrantes.map((integrante) => (
    integrante && !rules.nombrePersona.test(integrante)
      ? "Solo permite letras y espacios."
      : ""
  ));

  return result;
});

const displayedErrors = computed(() => {
  const backendErrors = props.backendErrors ?? {};

  return {
    ...errors.value,
    institucion: errors.value.institucion || backendErrors.institucion || "",
    equipo: errors.value.equipo || backendErrors.nombre_equipo || "",
    capitan: errors.value.capitan || backendErrors.nombre_capitan || "",
    prototipo: errors.value.prototipo || backendErrors.nombre_prototipo || "",
    contacto: errors.value.contacto || backendErrors.telefono_contacto || "",
    integrantes: errors.value.integrantes,
  };
});

const hasValidationErrors = computed(() => {
  const fieldErrors = Object.entries(displayedErrors.value)
    .filter(([key]) => key !== "integrantes")
    .some(([, value]) => Boolean(value));

  return fieldErrors || displayedErrors.value.integrantes.some(Boolean);
});

const formCompleto = computed(() => {
  const data = normalizedForm.value;

  return (
    data.institucion &&
    data.categoria &&
    data.equipo &&
    data.capitan &&
    data.prototipo &&
    data.contacto &&
    form.aceptaTratamiento &&
    data.integrantes.length === integrantesAdicionales.value &&
    data.integrantes.every(Boolean) &&
    !hasValidationErrors.value
  );
});

const handleClose = () => {
  emit("close");
};

const clearBackendError = (field) => {
  emit("clear-backend-error", field);
};

const handleSubmit = () => {
  if (!formCompleto.value) return;

  emit("submitted", {
    institucion: normalizedForm.value.institucion,
    categoria: normalizedForm.value.categoria,
    equipo: normalizedForm.value.equipo,
    capitan: normalizedForm.value.capitan,
    prototipo: normalizedForm.value.prototipo,
    contacto: normalizedForm.value.contacto,
    integrantes: normalizedForm.value.integrantes,
  });
};
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[10070] bg-black/70 p-4 sm:p-6"
      @click="handleClose"
    >
      <div class="grid min-h-full place-items-center">
        <div
          class="flex max-h-[92vh] w-full max-w-[760px] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
          @click.stop
        >
          <!-- Header -->
          <div class="relative shrink-0 bg-blue-600 px-5 py-5 text-white">
            <button
              type="button"
              class="absolute right-4 top-4 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/20 text-white transition hover:bg-white/30"
              @click="handleClose"
            >
              <XMarkIcon class="h-5 w-5" />
            </button>

            <h3 class="pr-12 text-2xl font-bold">{{ modalTitle }}</h3>
            <p class="mt-1 text-sm text-blue-100">
              {{ modalSubtitle }}
            </p>
          </div>

          <!-- Body -->
          <div class="flex-1 overflow-y-auto p-5">
            <div class="space-y-5">
              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">
                    Categoría a Participar <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.categoria"
                    type="text"
                    readonly
                    class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none"
                  />
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">
                    Institución o Club <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.institucion"
                    :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-100': displayedErrors.institucion }"
                    type="text"
                    placeholder="Nombre de tu institución/club"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    @input="clearBackendError('institucion')"
                  />
                  <p v-if="displayedErrors.institucion" class="mt-1 text-xs text-red-600">{{ displayedErrors.institucion }}</p>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">
                    Nombre del Equipo <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.equipo"
                    :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-100': displayedErrors.equipo }"
                    type="text"
                    placeholder="Nombre de tu equipo"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    @input="clearBackendError('nombre_equipo')"
                  />
                  <p v-if="displayedErrors.equipo" class="mt-1 text-xs text-red-600">{{ displayedErrors.equipo }}</p>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">
                    Nombres y Apellidos del Capitán del Equipo <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.capitan"
                    :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-100': displayedErrors.capitan }"
                    type="text"
                    placeholder="Nombres y apellidos del capitán"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    @input="clearBackendError('nombre_capitan')"
                  />
                  <p v-if="displayedErrors.capitan" class="mt-1 text-xs text-red-600">{{ displayedErrors.capitan }}</p>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">
                    Nombre del Prototipo <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.prototipo"
                    :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-100': displayedErrors.prototipo }"
                    type="text"
                    placeholder="Nombre del robot/prototipo"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    @input="clearBackendError('nombre_prototipo')"
                  />
                  <p v-if="displayedErrors.prototipo" class="mt-1 text-xs text-red-600">{{ displayedErrors.prototipo }}</p>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">
                    Número de Contacto <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.contacto"
                    :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-100': displayedErrors.contacto }"
                    type="tel"
                    inputmode="tel"
                    placeholder="+593999999999"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    @input="onContactoInput($event); clearBackendError('telefono_contacto')"
                    @keydown="prevenirEspacioContacto"
                  />
                  <p v-if="displayedErrors.contacto" class="mt-1 text-xs text-red-600">{{ displayedErrors.contacto }}</p>
                </div>

                <div v-if="integrantesAdicionales > 0" class="md:col-span-2">
                  <label class="mb-1 block text-sm font-medium text-slate-700">
                    Integrantes adicionales <span class="text-red-500">*</span>
                  </label>
                  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div
                      v-for="index in integrantesAdicionales"
                      :key="index"
                    >
                      <input
                        v-model="form.integrantes[index - 1]"
                        :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-100': errors.integrantes[index - 1] }"
                        type="text"
                        :placeholder="`Nombres y apellidos del integrante ${index + 1}`"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                      />
                      <p v-if="errors.integrantes[index - 1]" class="mt-1 text-xs text-red-600">
                        {{ errors.integrantes[index - 1] }}
                      </p>
                    </div>
                  </div>
                  <p class="mt-2 text-xs text-slate-500">
                    Esta categoría permite {{ maxIntegrantes }} integrante{{ maxIntegrantes === 1 ? "" : "s" }} contando al capitán.
                  </p>
                </div>

                <div v-else class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                  <p class="text-sm text-slate-700">
                    Esta categoría es individual. Solo se registrará el capitán como integrante.
                  </p>
                </div>
              </div>

              <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-4">
                <div class="flex items-start gap-3">
                  <InformationCircleIcon class="mt-0.5 h-10 w-10 text-red-600" />
                  <p class="text-sm leading-6 text-red-700">
                    Verifica cuidadosamente la información antes de enviar la inscripción, debido a que algunos datos serán considerados para la generación de los certificados correspondientes.
                  </p>
                </div>
              </div>

              <label
                class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm leading-6 text-slate-700"
              >
                <input
                  v-model="form.aceptaTratamiento"
                  type="checkbox"
                  class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                />
                <span>
                  Acepto y autorizo el tratamiento de los datos ingresados en este formulario para los fines relacionados con el Concurso de {{ nombreCompetencia }}.
                  <span class="text-red-500">*</span>
                </span>
              </label>
            </div>
          </div>

          <!-- Footer -->
          <div class="shrink-0 border-t border-slate-200 bg-white p-5">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <button
                type="button"
                class="rounded-xl bg-slate-100 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-200"
                @click="handleClose"
              >
                Cancelar
              </button>

              <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-medium text-white transition"
                :class="
                  formCompleto
                    ? 'bg-blue-600 hover:bg-blue-700'
                    : 'cursor-not-allowed bg-blue-300'
                "
                :disabled="!formCompleto"
                @click="handleSubmit"
              >
                <ArrowUpTrayIcon class="h-4 w-4" />
                {{ submitLabel }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
