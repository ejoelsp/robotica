<script setup>
import { Link } from '@inertiajs/vue3';
// import AppLayout from '../layouts/AppLayout.vue';
import torneo from '../assets/logos/torneo.png';

</script>


<template>
    <!-- HERO principal -->
    <section class="mx-auto grid max-w-7xl grid-cols-1 gap-8 px-4 py-10 sm:px-6 sm:py-14 md:grid-cols-2 md:gap-10 md:py-20">
      
      <!-- Columna texto -->
      <div class="flex flex-col justify-center gap-5 sm:gap-6">
        <h1 class="text-3xl font-bold leading-tight text-slate-900 sm:text-4xl md:text-5xl">
          Gestión integral de competencias de robótica ESPOCH
        </h1>

        <p class="text-base text-slate-600 sm:text-lg">
          Inscripciones en línea, resultados en tiempo real y difusión interactiva
          para participantes, jueces e instituciones.
        </p>

        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:gap-4">
          <Link
            href="/competencias"
            class="inline-flex w-full items-center justify-center rounded-md bg-black px-6 py-3 text-sm font-semibold text-white hover:bg-slate-900 sm:w-auto"
          >
            Ver próximas competencias
          </Link>
          <Link
            href="/resultados"
            class="inline-flex w-full items-center justify-center rounded-md border border-slate-300 px-6 py-3 text-sm text-slate-700 hover:bg-slate-50 sm:w-auto"
          >
            Ver resultados
          </Link>
        </div>
      </div>

      <!-- Columna tarjeta con gradiente -->
      <div class="flex items-center justify-center">
        <div
          class="flex h-56 w-full flex-col items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-purple-500 text-base font-medium text-white sm:h-64 md:h-80 md:rounded-3xl md:text-lg"
        >
          <div class="mb-4">
            <img 
              :src="torneo" 
              class="h-20 w-20 object-contain drop-shadow-lg sm:h-24 sm:w-24"
              alt="Torneo"
            />
          </div>
          <span>Sistema de Gestión de Competencias</span>
        </div>
      </div>

    </section>
</template>

