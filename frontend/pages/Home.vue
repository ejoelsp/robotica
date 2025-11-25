<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../layouts/AppLayout.vue';
import torneo from '../assets/logos/torneo.png';

</script>


<template>
  <AppLayout>
    <!-- HERO principal -->
    <section class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-10 py-20 px-6">
      
      <!-- Columna texto -->
      <div class="flex flex-col justify-center gap-6">
        <h1 class="text-4xl md:text-5xl font-bold text-slate-900 leading-tight">
          Gestión integral de competencias de robótica ESPOCH
        </h1>

        <p class="text-slate-600 text-lg">
          Inscripciones en línea, resultados en tiempo real y difusión interactiva
          para participantes, jueces e instituciones.
        </p>

        <div class="flex flex-wrap gap-4">
          <Link
            href="/competencias"
            class="px-6 py-3 rounded-md bg-black text-white text-sm font-semibold hover:bg-slate-900"
          >
            Ver próximas competencias
          </Link>
          <button
            class="px-6 py-3 rounded-md border border-slate-300 text-sm text-slate-700 hover:bg-slate-50"
          >
            Ver resultados en vivo
          </button>
        </div>
      </div>

      <!-- Columna tarjeta con gradiente -->
      <div class="flex items-center justify-center">
        <div
          class="w-full h-64 md:h-80 rounded-3xl bg-gradient-to-br from-blue-500 to-purple-500 flex flex-col items-center justify-center text-white text-lg font-medium"
        >
          <div class="mb-4">
            <img 
              :src="torneo" 
              class="w-24 h-24 object-contain drop-shadow-lg"
              alt="Torneo"
            />
          </div>
          <span>Sistema de Gestión de Competencias</span>
        </div>
      </div>

    </section>
  </AppLayout>
</template>

