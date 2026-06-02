<script setup>
import CompetidorLayout from "@/layouts/CompetidorLayout.vue";
import {
  ArrowDownTrayIcon,
  ClockIcon,
  DocumentCheckIcon,
  TrophyIcon,
} from "@heroicons/vue/24/outline";

defineOptions({ layout: CompetidorLayout });

defineProps({
  certificadosCompetidor: {
    type: Object,
    default: () => ({ summary: {}, items: [] }),
  },
});
</script>

<template>
  <div class="mx-auto w-full max-w-[1180px] space-y-5 px-3 py-5 sm:space-y-6 sm:px-6 sm:py-6 lg:px-4">
    <section>
      <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Mis Certificados</h1>
      <p class="mt-1 text-sm text-slate-500">
        Descarga los certificados generados según tus resultados o tu estado de participación.
      </p>
    </section>

    <section class="grid gap-3 sm:gap-4 md:grid-cols-2">
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="text-sm font-medium text-slate-500">Certificados posibles</p>
            <p class="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl">
              {{ certificadosCompetidor.summary?.total ?? 0 }}
            </p>
          </div>
          <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50">
            <DocumentCheckIcon class="h-6 w-6 text-blue-600" />
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm sm:p-5">
        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="text-sm font-medium text-emerald-700">Disponibles</p>
            <p class="mt-2 text-2xl font-bold text-emerald-900 sm:text-3xl">
              {{ certificadosCompetidor.summary?.disponibles ?? 0 }}
            </p>
          </div>
          <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white">
            <TrophyIcon class="h-6 w-6 text-emerald-600" />
          </div>
        </div>
      </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-200 px-4 py-3.5 sm:px-5 sm:py-4">
        <h2 class="text-lg font-bold text-slate-900">Certificados</h2>
      </div>

      <div class="divide-y divide-slate-200">
        <article
          v-for="item in certificadosCompetidor.items"
          :key="item.integrante_id"
          class="grid gap-4 px-4 py-4 sm:gap-5 sm:px-5 sm:py-5 lg:grid-cols-[1.4fr_1.2fr_1fr_auto] lg:items-center"
        >
          <div class="min-w-0">
            <h3 class="text-lg font-bold text-slate-900 sm:text-xl">{{ item.participante }}</h3>
            <p class="mt-1 text-sm text-slate-600">
              <span class="font-semibold">Equipo:</span> {{ item.equipo }}
            </p>
            <p class="mt-1 text-sm text-slate-500">{{ item.institucion || "Sin institución" }}</p>
          </div>

          <div class="min-w-0">
            <p class="text-sm font-semibold text-blue-700">{{ item.categoria }}</p>
            <p class="mt-1 text-sm text-slate-500 sm:truncate">{{ item.competencia }}</p>
            <p class="mt-1 text-sm text-slate-500 sm:truncate">Prototipo: {{ item.prototipo }}</p>
          </div>

          <div>
            <p class="text-sm text-slate-500">Tipo</p>
            <p class="mt-1 text-base font-bold text-slate-900">
              {{ item.tipo_label || "Pendiente" }}
            </p>
            <p v-if="item.posicion" class="mt-1 text-sm text-slate-500">Posición {{ item.posicion }}</p>
          </div>

          <div class="flex w-full lg:justify-end">
            <a
              v-if="item.disponible"
              :href="`/competidor/certificados/${item.integrante_id}/descargar`"
              class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 sm:w-auto"
            >
              <ArrowDownTrayIcon class="h-5 w-5" />
              Descargar PDF
            </a>
            <div
              v-else
              class="inline-flex w-full items-start gap-2 rounded-xl bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 sm:w-auto sm:max-w-[260px] sm:items-center"
            >
              <ClockIcon class="h-5 w-5 shrink-0" />
              <span>{{ item.mensaje }}</span>
            </div>
          </div>
        </article>

        <div
          v-if="!certificadosCompetidor.items?.length"
          class="px-4 py-10 text-center text-sm text-slate-500 sm:px-5 sm:py-12 sm:text-base"
        >
          Todavía no tienes inscripciones aprobadas para certificados.
        </div>
      </div>
    </section>
  </div>
</template>
