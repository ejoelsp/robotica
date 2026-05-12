<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import JuezLayout from "@/layouts/JuezLayout.vue";
import RegistroResultadosJuez from "@/components/Resultados/RegistroResultadosJuez.vue";
import {
  ClipboardDocumentListIcon,
  TrophyIcon,
  ShieldCheckIcon,
} from "@heroicons/vue/24/outline";

defineOptions({
  layout: JuezLayout,
});

const page = usePage();

const user = computed(() => page.props.juez ?? page.props.auth?.user ?? {});
const competenciaActual = computed(() => page.props.competenciaActual ?? null);
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">
        Bienvenido, {{ user.name || "Juez" }}
      </h1>
      <p class="mt-1 text-sm text-slate-500">
        Tus evaluaciones ya usan la configuracion real de categorias y mecanismos definida por el administrador.
      </p>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
      <div class="rounded-3xl border border-blue-200 bg-blue-50 p-5">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-sm font-semibold text-blue-700">Competencia activa</p>
            <p class="mt-3 text-xl font-bold text-blue-900">
              {{ competenciaActual?.nombre || "Sin competencia activa" }}
            </p>
          </div>
          <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-100">
            <TrophyIcon class="h-7 w-7 text-blue-700" />
          </div>
        </div>
      </div>

      <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-sm font-semibold text-emerald-700">Flujo habilitado</p>
            <p class="mt-3 text-xl font-bold text-emerald-900">
              Evaluacion con versionado
            </p>
          </div>
          <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100">
            <ShieldCheckIcon class="h-7 w-7 text-emerald-700" />
          </div>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-200 bg-white p-5">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-sm font-semibold text-slate-700">Panel de trabajo</p>
            <p class="mt-3 text-xl font-bold text-slate-900">
              Registro por categoria y ronda
            </p>
          </div>
          <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100">
            <ClipboardDocumentListIcon class="h-7 w-7 text-slate-700" />
          </div>
        </div>
      </div>
    </div>

    <RegistroResultadosJuez mode="remote" />
  </div>
</template>
