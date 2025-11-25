<script setup>
import { computed, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';


const throttleError = ref('');

// Inertia form (para enviar el POST)
const form = useForm({
  email: '',
  password: '',
  remember: false,
});

// Props de la página que vienen desde el backend
const page = usePage();

// Leemos el loginError que mandamos desde el controlador
const loginError = computed(() => page.props.loginError || '');

// Si quieres recuperar el email que mandamos en "old"
const old = computed(() => page.props.old || {});

// Cuando se monte el componente, si hay old.email lo ponemos
if (old.value.email) {
  form.email = old.value.email;
  form.remember = !!old.value.remember;
}

const onSubmit = () => {
  throttleError.value = ''; // limpiamos el error de bloqueo

  form.post('/login', {
    preserveScroll: true,

    onError: (errors) => {
      // Aquí intentamos capturar el error que manda el throttle (429)
      // Laravel normalmente manda algo como { message: "Too Many Attempts." }
      if (errors && errors.message) {
        const msg = Array.isArray(errors.message) ? errors.message[0] : errors.message;

        // Puedes mostrarlo tal cual o traducirlo a algo amigable:
        throttleError.value = 'Has excedido el número de intentos. Intenta de nuevo en un momento.';
        console.log('Throttle error:', msg);
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

        <!-- BLOQUE ROJO DE ERROR GENERAL (FUERA DEL FORMULARIO) -->
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
            <input
              v-model="form.password"
              id="password"
              type="password"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm
                     focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              autocomplete="current-password"
            />
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
