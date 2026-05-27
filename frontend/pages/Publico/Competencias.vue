<script setup>
import { computed } from "vue";

const props = defineProps({
  competencias: {
    type: Array,
    default: () => [],
  },
});

const competencias = computed(() => props.competencias ?? []);

const formatFechaRango = (inicio, fin) => {
  if (!inicio) return "Fecha por definir";

  const opts = { day: "2-digit", month: "short", year: "numeric" };
  const inicioTexto = new Date(inicio).toLocaleDateString("es-EC", opts);

  if (!fin || fin === inicio) return inicioTexto;

  return `Del ${inicioTexto} al ${new Date(fin).toLocaleDateString("es-EC", opts)}`;
};

const openEventLink = (url) => {
  if (!url) return;

  let target = String(url).trim();
  if (!/^https?:\/\//i.test(target)) target = `https://${target}`;

  window.open(target, "_blank");
};
</script>

<template>
  <section class="mx-auto max-w-7xl px-6 py-12">
    <div class="mb-8">
      <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">
        Club de Robótica ESPOCH
      </p>
      <h1 class="mt-2 text-3xl font-bold text-slate-900">
        Competencias
      </h1>
      <p class="mt-2 max-w-2xl text-sm text-slate-600">
        Consulta la información general de las competencias registradas.
      </p>
    </div>

    <div
      v-if="competencias.length === 0"
      class="rounded-2xl border border-dashed border-slate-200 bg-white p-10 text-center text-slate-500"
    >
      No hay competencias publicadas por el momento.
    </div>

    <div v-else class="grid grid-cols-1 gap-6 md:grid-cols-2">
      <article
        v-for="competencia in competencias"
        :key="competencia.id"
        class="overflow-hidden rounded-2xl border bg-white shadow-sm"
      >
        <div class="h-56 bg-slate-100">
          <img
            v-if="competencia.imagen_url"
            :src="competencia.imagen_url"
            :alt="`Portada de ${competencia.nombre}`"
            class="h-full w-full object-cover"
          />
          <div v-else class="flex h-full w-full items-center justify-center bg-slate-200">
            <span class="rounded-xl bg-white/80 px-4 py-2 text-sm font-semibold text-slate-600">
              Competencia sin portada
            </span>
          </div>
        </div>

        <div class="space-y-4 p-5">
          <div class="flex flex-wrap items-center gap-2">
            <span
              v-if="competencia.estado"
              class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700"
            >
              Evento principal
            </span>
            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
              {{ competencia.tipo_competencia || "Sin tipo" }}
            </span>
          </div>

          <div>
            <h2 class="text-xl font-bold text-slate-900">
              {{ competencia.nombre }}
            </h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">
              {{ competencia.descripcion || "Descripcion pendiente." }}
            </p>
          </div>

          <div class="space-y-2 text-sm text-slate-600">
            <p>
              <span class="font-semibold text-slate-800">Fechas:</span>
              {{ formatFechaRango(competencia.fecha_inicio, competencia.fecha_fin) }}
            </p>
            <p>
              <span class="font-semibold text-slate-800">Tipo:</span>
              {{ competencia.tipo_competencia || "No definido" }}
            </p>
          </div>

          <button
            v-if="competencia.enlace_evento"
            type="button"
            @click="openEventLink(competencia.enlace_evento)"
            class="rounded-xl bg-black px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900"
          >
            Abrir enlace del evento
          </button>
        </div>
      </article>
    </div>
  </section>
</template>
