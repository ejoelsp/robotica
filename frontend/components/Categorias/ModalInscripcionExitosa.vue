<script setup>
import { CheckCircleIcon } from "@heroicons/vue/24/outline";

defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: "¡Inscripción Exitosa!",
  },
  message: {
    type: String,
    default:
      "Tu inscripción ha sido registrada correctamente. Recibirás un correo de confirmación pronto.",
  },
});

const emit = defineEmits(["close"]);

const handleClose = () => {
  emit("close");
};
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[10080] flex items-center justify-center bg-black/70 p-4"
      @click="handleClose"
    >
      <div
        class="w-full max-w-[360px] rounded-2xl bg-white px-6 py-8 text-center shadow-2xl"
        @click.stop
      >
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100">
          <CheckCircleIcon class="h-8 w-8 text-emerald-600" />
        </div>

        <h3 class="mt-4 text-2xl font-bold text-slate-900">
          {{ title }}
        </h3>

        <p class="mt-3 text-sm leading-6 text-slate-600">
          {{ message }}
        </p>

        <button
          type="button"
          class="mt-6 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800"
          @click="handleClose"
        >
          Entendido
        </button>
      </div>
    </div>
  </Teleport>
</template>