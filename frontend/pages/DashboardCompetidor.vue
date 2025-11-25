<script setup>
import CompetidorLayout from '../layouts/CompetidorLayout.vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const user = page.props.auth?.user ?? { name: 'Usuario' };
</script>

<template>
  <CompetidorLayout>
    <div>
      <h1 class="text-3xl font-bold text-slate-900">
        ¡Hola, {{ user.name.toUpperCase() }}! 👋
      </h1>
      <p class="text-slate-500 mt-2">
        Este es el Dashboard del competidor (versión base). Luego lo ponemos bonito como en Figma.
      </p>
    </div>
  </CompetidorLayout>
</template>
