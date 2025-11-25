
<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';

const form = useForm({
  email: '',
  password: '',
  remember: false,
});

const generalError = ref('');

const onSubmit = () => {
  generalError.value = '';
  form.clearErrors();

  form.post('/login', {
    preserveScroll: true,
    onError: (errors) => {
      // Si el backend devolvió un error general por email
      if (errors.email && errors.email.includes('credenciales')) {
        generalError.value = errors.email;
      }
    },
  });
};
</script>

<template>
  <AppLayout>
    <section class="min-h-[70vh] flex items-center justify-center px-4">
      <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <h1 class="text-2xl font-semibold text-slate-900 mb-2">
          Iniciar sesión
        </h1>
        <p class="text-sm text-slate-500 mb-6">
          Accede a tu cuenta para gestionar competencias, inscripciones y resultados.
        </p>

        <form @submit.prevent="onSubmit" class="space-y-4">
          <!-- Email -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1" for="email">
              Correo electrónico
            </label>
            <input
              v-model="form.email"
              id="email"
              type="email"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm
                     focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              required
              autocomplete="email"
            />
            <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">
              {{ form.errors.email }}
            </p>
          </div>

          <!-- Password -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1" for="password">
              Contraseña
            </label>
            <input
              v-model="form.password"
              id="password"
              type="password"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm
                     focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              required
              autocomplete="current-password"
            />
            <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">
              {{ form.errors.password }}
            </p>
          </div>

          <!-- Recordarme -->
          <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-xs text-slate-600">
              <input
                v-model="form.remember"
                type="checkbox"
                class="rounded border-slate-300"
              />
              <span>Recordarme en este dispositivo</span>
            </label>

            <button
              type="button"
              class="text-xs text-blue-600 hover:underline"
            >
              ¿Olvidaste tu contraseña?
            </button>
          </div>

          <!-- Error general -->
          <div v-if="generalError" class="text-xs text-red-500">
            {{ generalError }}
          </div>

          <!-- Botón -->
          <button
            type="submit"
            class="w-full rounded-md bg-black text-white py-2.5 text-sm font-semibold hover:bg-slate-900 disabled:opacity-60"
            :disabled="form.processing"
          >
            <span v-if="!form.processing">Entrar</span>
            <span v-else>Verificando...</span>
          </button>
        </form>

        <p class="mt-6 text-xs text-center text-slate-500">
          ¿Aún no tienes cuenta?
          <Link href="/register" class="text-blue-600 hover:underline font-medium">
            Regístrate aquí
          </Link>
        </p>
      </div>
    </section>
  </AppLayout>
</template>

