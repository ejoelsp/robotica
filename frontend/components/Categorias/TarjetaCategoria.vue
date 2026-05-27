<script setup>
import { computed } from "vue";

const props = defineProps({
  categoria: {
    type: Object,
    required: true,
  },
  buttonText: {
    type: String,
    default: "Ver",
  },
});

const emit = defineEmits(["action"]);

const imageSrc = computed(() => props.categoria?.imagen_url || null);

const priceLabel = computed(() => {
  const amount = Number(props.categoria?.costo_inscripcion ?? 0);
  if (!Number.isFinite(amount) || amount <= 0) return "Gratis";
  return `$ ${amount.toFixed(2)}`;
});

const handleClick = () => {
  emit("action", props.categoria);
};
</script>

<template>
  <article
    class="flex h-full min-h-[200px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
  >
    <div class="h-40 w-full overflow-hidden bg-slate-100">
      <img
        v-if="imageSrc"
        :src="imageSrc"
        :alt="categoria.nombre"
        class="h-full w-full object-cover"
      />

      <div
        v-else
        class="flex h-full w-full items-center justify-center bg-black"
      >
        <div class="h-20 w-20 rounded-full bg-white"></div>
      </div>
    </div>

    <div class="flex flex-1 flex-col p-2">
      <div class="min-h-[1px]">
        <h3 class="text-xl font-bold leading-tight text-slate-900 text-center">
          {{ categoria.nombre }}
        </h3>
      </div>

      <div class="mt-auto flex justify-center pb-1 pt-5">
        <span
          class="inline-flex items-center rounded-full bg-blue-50 px-4 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-200"
        >
          {{ priceLabel }}
        </span>
      </div>

      <button
        type="button"
        class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-700"
        @click="handleClick"
      >
        {{ buttonText }}
      </button>
    </div>
  </article>
</template>
