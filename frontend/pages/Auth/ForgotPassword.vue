<script setup>
import { computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const form = useForm({
  email: '',
});

const generalError = computed(() => page.props.errors?.general || '');

const onSubmit = () => {
  form.clearErrors();
  form.post('/recuperar-contrasena', {
    preserveScroll: true,
  });
};
</script>

<template>
  <section class="min-h-[70vh] flex items-center justify-center px-4">
    <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
      <h1 class="mb-2 text-2xl font-semibold text-slate-900">
        Recuperar contraseña
      </h1>
      <p class="mb-6 text-sm text-slate-500">
        Ingresa el correo con el que te registraste. Te enviaremos un código de recuperación.
      </p>

      <div
        v-if="generalError"
        class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700"
      >
        {{ generalError }}
      </div>

      <form class="space-y-4" @submit.prevent="onSubmit">
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700" for="email">
            Correo electrónico
          </label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
            autocomplete="email"
            required
          />
          <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">
            {{ form.errors.email }}
          </p>
        </div>

        <button
          type="submit"
          class="w-full rounded-md bg-black py-2.5 text-sm font-semibold text-white hover:bg-slate-900 disabled:opacity-60"
          :disabled="form.processing"
        >
          {{ form.processing ? 'Enviando...' : 'Enviar código' }}
        </button>
      </form>

      <p class="mt-6 text-center text-xs text-slate-500">
        ¿Recordaste tu contraseña?
        <Link href="/login" class="font-medium text-blue-600 hover:underline">
          Inicia sesión aquí
        </Link>
      </p>
    </div>
  </section>
</template>
