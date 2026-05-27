<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { ArrowPathIcon } from "@heroicons/vue/24/outline";
import JuezLayout from "@/layouts/JuezLayout.vue";
import RegistroResultadosJuez from "@/components/Resultados/RegistroResultadosJuez.vue";


defineOptions({
  layout: JuezLayout,
});

const page = usePage();

const user = computed(() => page.props.juez ?? page.props.auth?.user ?? {});

function refreshPage() {
  window.location.reload();
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">
          Bienvenido, {{ user.name || "Juez" }}
        </h1>
        <p class="mt-1 text-sm text-slate-500">
          Tus evaluaciones ya usan la configuración real de categorías y mecanismos definida por el administrador.
        </p>
      </div>

      <button
        type="button"
        class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
        @click="refreshPage"
      >
        <ArrowPathIcon class="h-5 w-5" />
        Actualizar
      </button>
    </div>

    <RegistroResultadosJuez mode="remote" />
  </div>
</template>
