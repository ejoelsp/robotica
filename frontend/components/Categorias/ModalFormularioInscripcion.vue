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
});

const emit = defineEmits(["close", "submitted"]);

const form = reactive({
  institucion: "",
  categoria: "",
  equipo: "",
  capitan: "",
  prototipo: "",
  contacto: "",
  integrantes: "",
});

const resetForm = () => {
  form.institucion = "";
  form.categoria = props.categoria?.nombre ?? "";
  form.equipo = "";
  form.capitan = "";
  form.prototipo = "";
  form.contacto = "";
  form.integrantes = "";
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
    }
  }
);

const normalizedForm = computed(() => ({
  institucion: form.institucion.trim(),
  categoria: form.categoria.trim(),
  equipo: form.equipo.trim(),
  capitan: form.capitan.trim(),
  prototipo: form.prototipo.trim(),
  contacto: form.contacto.trim(),
  integrantes: form.integrantes.trim(),
}));

const formCompleto = computed(() => {
  const data = normalizedForm.value;

  return (
    data.institucion &&
    data.categoria &&
    data.equipo &&
    data.capitan &&
    data.prototipo &&
    data.contacto &&
    data.integrantes
  );
});

const handleClose = () => {
  emit("close");
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

            <h3 class="pr-12 text-2xl font-bold">Formulario de Inscripción</h3>
            <p class="mt-1 text-sm text-blue-100">
              Completa todos los campos para registrar tu equipo
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
                    type="text"
                    placeholder="Nombre de tu institución"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                  />
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">
                    Nombre del Equipo <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.equipo"
                    type="text"
                    placeholder="Nombre de tu equipo"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                  />
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">
                    Nombre del Capitán del Equipo <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.capitan"
                    type="text"
                    placeholder="Nombre del capitán"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                  />
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">
                    Nombre del Prototipo <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.prototipo"
                    type="text"
                    placeholder="Nombre del robot/prototipo"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                  />
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">
                    Número de Contacto <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.contacto"
                    type="text"
                    placeholder="+593 999 999 999"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                  />
                </div>

                <div class="md:col-span-2">
                  <label class="mb-1 block text-sm font-medium text-slate-700">
                    Nombres de los Integrantes <span class="text-red-500">*</span>
                  </label>
                  <textarea
                    v-model="form.integrantes"
                    rows="4"
                    placeholder="Escribe los nombres de todos los integrantes del equipo (sepáralos por comas)"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                  ></textarea>
                  <p class="mt-2 text-xs text-slate-500">
                    Ejemplo: Juan Pérez, María García, Carlos López
                  </p>
                </div>
              </div>

              <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-4">
                <div class="flex items-start gap-3">
                  <InformationCircleIcon class="mt-0.5 h-5 w-5 text-blue-600" />
                  <p class="text-sm leading-6 text-blue-700">
                    Asegúrate de que todos los datos sean correctos antes de enviar
                    la inscripción. El capitán del equipo debe existir como usuario
                    registrado en el sistema.
                  </p>
                </div>
              </div>
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
                Enviar Inscripción
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>