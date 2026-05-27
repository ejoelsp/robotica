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
  <div class="mx-auto w-full max-w-[1180px] space-y-6 px-4 py-6 sm:px-6 lg:px-4">
    <section>
      <h1 class="text-2xl font-bold text-slate-900">Mis Certificados</h1>
      <p class="mt-1 text-sm text-slate-500">
        Descarga los certificados generados según los resultados publicados.
      </p>
    </section>

    <section class="grid gap-4 md:grid-cols-2">
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="text-sm font-medium text-slate-500">Certificados posibles</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">
              {{ certificadosCompetidor.summary?.total ?? 0 }}
            </p>
          </div>
          <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50">
            <DocumentCheckIcon class="h-6 w-6 text-blue-600" />
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="text-sm font-medium text-emerald-700">Disponibles</p>
            <p class="mt-2 text-3xl font-bold text-emerald-900">
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
      <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-lg font-bold text-slate-900">Certificados</h2>
      </div>

      <div class="divide-y divide-slate-200">
        <article
          v-for="item in certificadosCompetidor.items"
          :key="item.integrante_id"
          class="grid gap-5 px-5 py-5 lg:grid-cols-[1.4fr_1.2fr_1fr_auto] lg:items-center"
        >
          <div class="min-w-0">
            <h3 class="truncate text-xl font-bold text-slate-900">{{ item.participante }}</h3>
            <p class="mt-1 text-sm text-slate-600">
              <span class="font-semibold">Equipo:</span> {{ item.equipo }}
            </p>
            <p class="mt-1 text-sm text-slate-500">{{ item.institucion || "Sin institución" }}</p>
          </div>

          <div class="min-w-0">
            <p class="text-sm font-semibold text-blue-700">{{ item.categoria }}</p>
            <p class="mt-1 truncate text-sm text-slate-500">{{ item.competencia }}</p>
            <p class="mt-1 truncate text-sm text-slate-500">Prototipo: {{ item.prototipo }}</p>
          </div>

          <div>
            <p class="text-sm text-slate-500">Tipo</p>
            <p class="mt-1 text-base font-bold text-slate-900">
              {{ item.tipo_label || "Pendiente" }}
            </p>
            <p v-if="item.posicion" class="mt-1 text-sm text-slate-500">Posición {{ item.posicion }}</p>
          </div>

          <div class="flex lg:justify-end">
            <a
              v-if="item.disponible"
              :href="`/competidor/certificados/${item.integrante_id}/descargar`"
              class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
            >
              <ArrowDownTrayIcon class="h-5 w-5" />
              Descargar PDF
            </a>
            <div
              v-else
              class="inline-flex max-w-[260px] items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700"
            >
              <ClockIcon class="h-5 w-5 shrink-0" />
              <span>{{ item.mensaje }}</span>
            </div>
          </div>
        </article>

        <div v-if="!certificadosCompetidor.items?.length" class="px-5 py-12 text-center text-slate-500">
          Todavía no tienes inscripciones aprobadas para certificados.
        </div>
      </div>
    </section>
  </div>
</template>
