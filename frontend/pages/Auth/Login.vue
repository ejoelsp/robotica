<script setup>
import { computed, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';

const throttleError = ref('');
const page = usePage();
const mostrarPassword = ref(false);
const sessionExpiredMessage = ref('');

const form = useForm({
  email: '',
  password: '',
  remember: false,
});

if (typeof window !== 'undefined') {
  const params = new URLSearchParams(window.location.search);
  const storedMessage = window.sessionStorage.getItem('session-expired-message');

  if (storedMessage) {
    sessionExpiredMessage.value = storedMessage;
    window.sessionStorage.removeItem('session-expired-message');
  } else if (params.get('expired') === '1') {
    sessionExpiredMessage.value = 'Tu sesión ha expirado por inactividad. Inicia sesión nuevamente.';
  }
}

// Error general de login o aviso de sesión expirada.
const loginError = computed(() => page.props.loginError || sessionExpiredMessage.value || '');


// Datos antiguos (old) si los estás usando
const old = computed(() => page.props.old || {});

if (old.value.email) {
  form.email = old.value.email;
  form.remember = !!old.value.remember;
}


const onSubmit = () => {
  throttleError.value = '';

  form.post('/login', {
    preserveScroll: true,
    onError: (errors) => {
      if (errors && errors.message) {
        const msg = Array.isArray(errors.message) ? errors.message[0] : errors.message;
        throttleError.value = 'Has excedido el número de intentos. Intenta de nuevo en un momento.';
        console.log('Throttle error:', msg);
      }
    },
  });
};
</script>

<template>
  <section class="flex min-h-[70vh] items-center justify-center px-3 py-6 sm:px-4">
    <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
      <h1 class="mb-2 text-xl font-semibold text-slate-900 sm:text-2xl">
        Iniciar sesión
      </h1>
      <p class="mb-5 text-sm text-slate-500 sm:mb-6">
        Accede a tu cuenta para gestionar competencias, inscripciones y resultados.
      </p>


      <!-- BLOQUE ROJO DE ERROR GENERAL -->
      <div
        v-if="loginError"
        class="mb-4 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700"
      >
        {{ loginError }}
      </div>

      <!-- BLOQUE NARANJA PARA BLOQUEO POR INTENTOS (THROTTLE) -->
      <div
        v-if="throttleError"
        class="mb-4 rounded-lg bg-orange-50 border border-orange-200 px-3 py-2 text-xs text-orange-700"
      >
        {{ throttleError }}
      </div>

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
            autocomplete="email"
          />
        </div>

        <!-- Password -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1" for="password">
            Contraseña
          </label>
          <div class="relative">
            <input
              v-model="form.password"
              id="password"
              :type="mostrarPassword ? 'text' : 'password'"
              class="w-full rounded-md border border-slate-300 px-3 py-2 pr-11 text-sm
                     focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              autocomplete="current-password"
            />
            <button
              type="button"
              class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-500 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
              :aria-label="mostrarPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
              :title="mostrarPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
              @click="mostrarPassword = !mostrarPassword"
            >
              <EyeSlashIcon v-if="mostrarPassword" class="h-5 w-5" />
              <EyeIcon v-else class="h-5 w-5" />
            </button>
          </div>
        </div>

        <!-- Recordarme -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <label class="flex items-center gap-2 text-xs text-slate-600">
            <input
              v-model="form.remember"
              type="checkbox"
              class="rounded border-slate-300"
            />
            <span>Recordarme</span>
          </label>

          <Link
            href="/recuperar-contrasena"
            class="text-xs text-blue-600 hover:underline"
          >
            ¿Olvidaste tu contraseña?
          </Link>
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
</template>
