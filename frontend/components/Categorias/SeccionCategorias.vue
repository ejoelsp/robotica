<script setup>
import { computed, ref, watch } from "vue";
import {
  Squares2X2Icon,
  ChevronLeftIcon,
  ChevronRightIcon,
} from "@heroicons/vue/24/outline";
import TarjetaCategoria from "@/components/Categorias/TarjetaCategoria.vue";

const props = defineProps({
  title: {
    type: String,
    default: "Categorías Disponibles",
  },
  subtitle: {
    type: String,
    default: "Explora e inscríbete en nuevas categorías",
  },
  categories: {
    type: Array,
    default: () => [],
  },
  buttonText: {
    type: String,
    default: "Ver",
  },
  itemsPerPage: {
    type: Number,
    default: 10,
  },
});

const emit = defineEmits(["action"]);

const currentPage = ref(1);

const totalPages = computed(() => {
  const total = props.categories.length;
  const perPage = Math.max(props.itemsPerPage, 1);
  return Math.max(Math.ceil(total / perPage), 1);
});

const paginatedCategories = computed(() => {
  const start = (currentPage.value - 1) * props.itemsPerPage;
  const end = start + props.itemsPerPage;
  return props.categories.slice(start, end);
});

const canGoPrev = computed(() => currentPage.value > 1);
const canGoNext = computed(() => currentPage.value < totalPages.value);

const goPrev = () => {
  if (!canGoPrev.value) return;
  currentPage.value--;
};

const goNext = () => {
  if (!canGoNext.value) return;
  currentPage.value++;
};

const goToPage = (page) => {
  if (page < 1 || page > totalPages.value) return;
  currentPage.value = page;
};

const onCardAction = (categoria) => {
  emit("action", categoria);
};

// Si cambia el array y la página actual queda fuera de rango, corregirla
watch(
  () => props.categories.length,
  () => {
    if (currentPage.value > totalPages.value) {
      currentPage.value = totalPages.value;
    }
  }
);
</script>

<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
    <!-- Header -->
    <div class="flex items-start gap-4">
      <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50">
        <Squares2X2Icon class="h-6 w-6 text-blue-600" />
      </div>

      <div>
        <h2 class="text-lg font-semibold text-slate-900">
          {{ title }}
        </h2>
        <p class="mt-1 text-sm text-slate-500">
          {{ subtitle }}
        </p>
      </div>
    </div>

    <!-- Grid paginado -->
    <div
      v-if="paginatedCategories.length"
      class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-5 auto-rows-fr"
    >
      <TarjetaCategoria
        v-for="categoria in paginatedCategories"
        :key="categoria.id"
        :categoria="categoria"
        :button-text="buttonText"
        @action="onCardAction"
      />
    </div>

    <!-- Vacío -->
    <div
      v-else
      class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center"
    >
      <p class="text-sm font-medium text-slate-700">
        No hay categorías disponibles por ahora.
      </p>
      <p class="mt-1 text-sm text-slate-500">
        Cuando existan categorías activas, aparecerán aquí.
      </p>
    </div>

        <!-- Paginación -->
    <div
      v-if="totalPages > 1"
      class="mt-6 flex items-center justify-center gap-6"
    >
      <!-- Flecha izquierda -->
      <button
        type="button"
        class="inline-flex h-12 w-12 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
        :disabled="!canGoPrev"
        @click="goPrev"
      >
        <ChevronLeftIcon class="h-6 w-6" />
      </button>

      <!-- Números -->
      <div class="flex items-center gap-2">
        <button
          v-for="page in totalPages"
          :key="page"
          type="button"
          class="inline-flex h-12 min-w-[44px] items-center justify-center rounded-2xl px-4 text-sm font-medium transition"
          :class="
            page === currentPage
              ? 'bg-blue-600 text-white shadow-sm'
              : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
          "
          @click="goToPage(page)"
        >
          {{ page }}
        </button>
      </div>

      <!-- Flecha derecha -->
      <button
        type="button"
        class="inline-flex h-12 w-12 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
        :disabled="!canGoNext"
        @click="goNext"
      >
        <ChevronRightIcon class="h-6 w-6" />
      </button>
    </div>
  </section>
</template>
