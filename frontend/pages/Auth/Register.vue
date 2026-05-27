<template>
  <section class="min-h-[70vh] flex items-center justify-center px-4">
    <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
      <h1 class="mb-2 text-2xl font-semibold text-slate-900">
        Crear cuenta
      </h1>
      <p class="mb-4 text-sm text-slate-500">
        Regístrate para gestionar tus competencias, inscripciones y resultados del evento.
      </p>

      <div
        v-if="registerError"
        class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700"
      >
        {{ registerError }}
      </div>

      <form class="space-y-4" @submit.prevent="onSubmit">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700" for="name">
              Nombre(s)
            </label>
            <input
              id="name"
              v-model="form.name"
              type="text"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
              @input="sanitizeName"
            />
            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">
              {{ form.errors.name }}
            </p>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700" for="last_name">
              Apellido(s)
            </label>
            <input
              id="last_name"
              v-model="form.last_name"
              type="text"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
              @input="sanitizeLastName"
            />
            <p v-if="form.errors.last_name" class="mt-1 text-xs text-red-500">
              {{ form.errors.last_name }}
            </p>
          </div>
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700" for="email">
            Correo electrónico
          </label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            :class="[
              'w-full rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 border',
              form.errors.email
                ? 'border-red-400 focus:border-red-500 focus:ring-red-500'
                : 'border-slate-300 focus:border-blue-500 focus:ring-blue-500',
            ]"
            required
          />
          <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">
            {{ form.errors.email }}
          </p>
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700" for="password">
            Contraseña
          </label>
          <div class="relative">
            <input
              id="password"
              v-model="form.password"
              :type="mostrarPassword ? 'text' : 'password'"
              class="w-full rounded-md border border-slate-300 px-3 py-2 pr-11 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
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
          <ul class="mt-2 space-y-1 text-xs">
            <li :class="ruleClass(passwordLengthOk)">
              • Mínimo 8 caracteres
            </li>
            <li :class="ruleClass(passwordHasUpper)">
              • Al menos una letra mayúscula
            </li>
            <li :class="ruleClass(passwordHasLower)">
              • Al menos una letra minúscula
            </li>
            <li :class="ruleClass(passwordHasNumber)">
              • Al menos un número
            </li>
            <li :class="ruleClass(passwordHasSpecial)">
              • Al menos un carácter especial (!@#$%^&*, etc.)
            </li>
          </ul>
          <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">
            {{ form.errors.password }}
          </p>
        </div>

        <div>
          <label
            class="mb-1 block text-sm font-medium text-slate-700"
            for="password_confirmation"
          >
            Confirmar contraseña
          </label>
          <div class="relative">
            <input
              id="password_confirmation"
              v-model="form.password_confirmation"
              :type="mostrarConfirmacionPassword ? 'text' : 'password'"
              class="w-full rounded-md border border-slate-300 px-3 py-2 pr-11 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            />
            <button
              type="button"
              class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-500 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
              :aria-label="mostrarConfirmacionPassword ? 'Ocultar confirmación de contraseña' : 'Mostrar confirmación de contraseña'"
              :title="mostrarConfirmacionPassword ? 'Ocultar confirmación de contraseña' : 'Mostrar confirmación de contraseña'"
              @click="mostrarConfirmacionPassword = !mostrarConfirmacionPassword"
            >
              <EyeSlashIcon v-if="mostrarConfirmacionPassword" class="h-5 w-5" />
              <EyeIcon v-else class="h-5 w-5" />
            </button>
          </div>
          <p v-if="form.password_confirmation && !passwordsMatch" class="mt-1 text-xs text-red-500">
            Las contraseñas no coinciden.
          </p>
        </div>

        <div v-if="formError" class="text-xs text-red-500">
          {{ formError }}
        </div>

        <button
          type="submit"
          class="w-full rounded-md bg-black py-2.5 text-sm font-semibold text-white hover:bg-slate-900 disabled:opacity-60"
          :disabled="form.processing"
        >
          <span v-if="!form.processing">Registrarme</span>
          <span v-else>Registrando...</span>
        </button>
      </form>

      <p class="mt-6 text-center text-xs text-slate-500">
        ¿Ya tienes cuenta?
        <Link href="/login" class="font-medium text-blue-600 hover:underline">
          Inicia sesión aquí
        </Link>
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';

const form = useForm({
  name: '',
  last_name: '',
  email: '',
  password: '',
  password_confirmation: '',
});

const page = usePage();
const mostrarPassword = ref(false);
const mostrarConfirmacionPassword = ref(false);
const registerError = computed(() => page.props.registerError || '');
const old = computed(() => page.props.old || {});

if (old.value.name) {
  form.name = old.value.name;
}
if (old.value.last_name) {
  form.last_name = old.value.last_name;
}
if (old.value.email) {
  form.email = old.value.email;
}

const formError = ref('');
const sanitizeName = () => {
  form.name = form.name
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^A-Za-zÑñ\s]/g, '')
    .toUpperCase();
};

const sanitizeLastName = () => {
  form.last_name = form.last_name
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^A-Za-zÑñ\s]/g, '')
    .toUpperCase();
};

const passwordLengthOk = computed(() => form.password.length >= 8);
const passwordHasUpper = computed(() => /[A-Z]/.test(form.password));
const passwordHasLower = computed(() => /[a-z]/.test(form.password));
const passwordHasNumber = computed(() => /[0-9]/.test(form.password));
const passwordHasSpecial = computed(() => /[^A-Za-z0-9]/.test(form.password));

const passwordsMatch = computed(
  () =>
    form.password.length > 0 &&
    form.password_confirmation.length > 0 &&
    form.password === form.password_confirmation
);

const ruleClass = (ok) => (ok ? 'text-green-600' : 'text-slate-500');

const onSubmit = () => {
  formError.value = '';
  form.clearErrors();

  if (
    !passwordLengthOk.value ||
    !passwordHasUpper.value ||
    !passwordHasLower.value ||
    !passwordHasNumber.value ||
    !passwordHasSpecial.value
  ) {
    formError.value =
      'La contraseña no cumple con los requisitos mínimos de seguridad.';
    return;
  }

  if (!passwordsMatch.value) {
    formError.value = 'Las contraseñas no coinciden.';
    return;
  }

  form.name = form.name.toUpperCase();
  form.last_name = form.last_name.toUpperCase();

  form.post('/register', {
    preserveScroll: true,
  });
};
</script>
