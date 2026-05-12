<script setup>
import { computed } from "vue";

const props = defineProps({
  competencia: {
    type: Object,
    default: null,
  },
  integrantes: {
    type: Array,
    default: () => [],
  },
});

const integrantes = computed(() => props.integrantes ?? []);
</script>

<template>
  <section class="mx-auto max-w-7xl px-6 py-12">
    <div class="mb-8">
      <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">
        Contacto
      </p>
      <h1 class="mt-2 text-3xl font-bold text-slate-900">
        Comite organizador
      </h1>
      <p class="mt-2 max-w-2xl text-sm text-slate-600">
        Integrantes responsables de la organizacion
        <span v-if="competencia">de {{ competencia.nombre }}</span>.
      </p>
    </div>

    <div
      v-if="integrantes.length === 0"
      class="rounded-2xl border border-dashed border-slate-200 bg-white p-10 text-center text-slate-500"
    >
      Aun no hay integrantes activos del comite organizador para mostrar.
    </div>

    <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
      <article
        v-for="integrante in integrantes"
        :key="integrante.id"
        class="rounded-2xl border bg-white p-5 shadow-sm"
      >
        <div class="flex items-start gap-4">
          <div class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-slate-100">
            <img
              v-if="integrante.foto_url"
              :src="integrante.foto_url"
              :alt="`${integrante.nombres} ${integrante.apellidos}`"
              class="h-full w-full object-cover"
            />
            <div v-else class="flex h-full w-full items-center justify-center text-xl font-bold text-slate-400">
              {{ integrante.nombres?.charAt(0) }}{{ integrante.apellidos?.charAt(0) }}
            </div>
          </div>

          <div class="min-w-0">
            <h2 class="break-words text-lg font-bold text-slate-900">
              {{ integrante.nombres }} {{ integrante.apellidos }}
            </h2>
            <p class="mt-1 text-sm font-semibold text-blue-700">
              {{ integrante.rol_comite }}
            </p>
            <a
              v-if="integrante.correo"
              :href="`mailto:${integrante.correo}`"
              class="mt-2 block break-words text-sm text-slate-600 hover:text-blue-700"
            >
              {{ integrante.correo }}
            </a>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>
