<script setup>
import { computed } from "vue";
import {
  XMarkIcon,
  InformationCircleIcon,
  DocumentTextIcon,
  UserPlusIcon,
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

const emit = defineEmits(["close", "open-form", "open-reglamento"]);

const nombreCategoria = computed(() => props.categoria?.nombre ?? "");

const descripcionCategoria = computed(() => {
  return (
    props.categoria?.descripcion ||
    "Esta categoría aún no tiene una descripción configurada."
  );
});

const handleClose = () => {
  emit("close");
};

const handleOpenForm = () => {
  if (!props.categoria) return;
  emit("open-form", props.categoria);
};

const handleOpenReglamento = () => {
  if (!props.categoria) return;
  emit("open-reglamento", props.categoria);
};
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[10060] flex items-center justify-center bg-black/70 p-4"
      @click="handleClose"
    >
      <div
        class="w-full max-w-[520px] overflow-hidden rounded-2xl bg-white shadow-2xl"
        @click.stop
      >
        <!-- Header -->
        <div class="relative bg-blue-600 px-5 py-5 text-white">
          <button
            type="button"
            class="absolute right-4 top-4 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/20 text-white transition hover:bg-white/30"
            @click="handleClose"
          >
            <XMarkIcon class="h-5 w-5" />
          </button>

          <h3 class="pr-12 text-2xl font-bold leading-tight">
            {{ nombreCategoria }}
          </h3>
        </div>

        <!-- Body -->
        <div class="space-y-5 p-5">
          <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-4">
            <div class="flex items-start gap-3">
              <InformationCircleIcon class="mt-2 h-20 w-20 text-blue-600" />
              <div>
                <h4 class="text-sm font-semibold text-blue-800">
                  Información Importante
                </h4>
                <p class="mt-1 text-sm leading-6 text-blue-700">
                  Asegúrate de leer el reglamento completo antes de inscribirte.
                  Tu equipo debe cumplir con todos los requisitos especificados
                  para participar correctamente en esta categoría.
                </p>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <button
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-200"
              @click="handleOpenReglamento"
            >
              <DocumentTextIcon class="h-5 w-5" />
              Reglamento
            </button>

            <button
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-blue-700"
              @click="handleOpenForm"
            >
              <UserPlusIcon class="h-5 w-5" />
              Inscribirse
            </button>
          </div>

          <button
            type="button"
            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
            @click="handleClose"
          >
            Cancelar
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>