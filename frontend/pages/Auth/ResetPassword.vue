<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';

const page = usePage();
const email = computed(() => page.props.email || '');
const codeVerified = computed(() => Boolean(page.props.codeVerified));
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const codeForm = useForm({
  email: email.value,
  code: '',
});

const passwordForm = useForm({
  email: email.value,
  password: '',
  password_confirmation: '',
});

const passwordLengthOk = computed(() => passwordForm.password.length >= 8);
const passwordHasUpper = computed(() => /[A-Z]/.test(passwordForm.password));
const passwordHasLower = computed(() => /[a-z]/.test(passwordForm.password));
const passwordHasNumber = computed(() => /[0-9]/.test(passwordForm.password));
const passwordHasSpecial = computed(() => /[^A-Za-z0-9]/.test(passwordForm.password));
const passwordsMatch = computed(
  () =>
    passwordForm.password.length > 0 &&
    passwordForm.password_confirmation.length > 0 &&
    passwordForm.password === passwordForm.password_confirmation
);

const ruleClass = (ok) => (ok ? 'text-green-600' : 'text-slate-500');

const verifyCode = () => {
  codeForm.email = email.value;
  codeForm.clearErrors();
  codeForm.post('/restablecer-contrasena/verificar-codigo', {
    preserveScroll: true,
  });
};

const updatePassword = () => {
  passwordForm.email = email.value;
  passwordForm.clearErrors();
  passwordForm.post('/restablecer-contrasena', {
    preserveScroll: true,
  });
};
</script>

<template>
  <section class="min-h-[70vh] flex items-center justify-center px-4">
    <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
      <h1 class="mb-2 text-2xl font-semibold text-slate-900">
        Restablecer contraseña
      </h1>
      <p class="mb-6 text-sm text-slate-500">
        Verifica el código enviado a tu correo y define una nueva contraseña.
      </p>

      <div
        v-if="passwordForm.errors.general"
        class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700"
      >
        {{ passwordForm.errors.general }}
      </div>

      <form v-if="!codeVerified" class="space-y-4" @submit.prevent="verifyCode">
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700" for="email">
            Correo electrónico
          </label>
          <input
            id="email"
            :value="email"
            type="email"
            class="w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-600"
            readonly
          />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700" for="code">
            Código de recuperación
          </label>
          <input
            id="code"
            v-model="codeForm.code"
            type="text"
            inputmode="numeric"
            maxlength="6"
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-center text-lg font-semibold tracking-[0.3em] focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
            autocomplete="one-time-code"
            required
          />
          <p v-if="codeForm.errors.code" class="mt-1 text-xs text-red-500">
            {{ codeForm.errors.code }}
          </p>
          <p v-if="codeForm.errors.email" class="mt-1 text-xs text-red-500">
            {{ codeForm.errors.email }}
          </p>
        </div>

        <button
          type="submit"
          class="w-full rounded-md bg-black py-2.5 text-sm font-semibold text-white hover:bg-slate-900 disabled:opacity-60"
          :disabled="codeForm.processing"
        >
          {{ codeForm.processing ? 'Verificando...' : 'Verificar código' }}
        </button>
      </form>

      <form v-else class="space-y-4" @submit.prevent="updatePassword">
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700" for="password">
            Nueva contraseña
          </label>
          <div class="relative">
            <input
              id="password"
              v-model="passwordForm.password"
              :type="showPassword ? 'text' : 'password'"
              class="w-full rounded-md border border-slate-300 px-3 py-2 pr-11 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            />
            <button
              type="button"
              class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-500 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
              :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
              :title="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
              @click="showPassword = !showPassword"
            >
              <EyeSlashIcon v-if="showPassword" class="h-5 w-5" />
              <EyeIcon v-else class="h-5 w-5" />
            </button>
          </div>

          <ul class="mt-2 space-y-1 text-xs">
            <li :class="ruleClass(passwordLengthOk)">• Mínimo 8 caracteres</li>
            <li :class="ruleClass(passwordHasUpper)">• Al menos una letra mayúscula</li>
            <li :class="ruleClass(passwordHasLower)">• Al menos una letra minúscula</li>
            <li :class="ruleClass(passwordHasNumber)">• Al menos un número</li>
            <li :class="ruleClass(passwordHasSpecial)">• Al menos un carácter especial (!@#$%^&*, etc.)</li>
          </ul>
          <p class="mt-2 text-xs text-slate-500">
            La nueva contraseña debe ser diferente a tu contraseña anterior. Esta validación se realizará al actualizar.
          </p>

          <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-red-500">
            {{ passwordForm.errors.password }}
          </p>
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700" for="password_confirmation">
            Confirmar contraseña
          </label>
          <div class="relative">
            <input
              id="password_confirmation"
              v-model="passwordForm.password_confirmation"
              :type="showPasswordConfirmation ? 'text' : 'password'"
              class="w-full rounded-md border border-slate-300 px-3 py-2 pr-11 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            />
            <button
              type="button"
              class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-500 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
              :aria-label="showPasswordConfirmation ? 'Ocultar confirmación de contraseña' : 'Mostrar confirmación de contraseña'"
              :title="showPasswordConfirmation ? 'Ocultar confirmación de contraseña' : 'Mostrar confirmación de contraseña'"
              @click="showPasswordConfirmation = !showPasswordConfirmation"
            >
              <EyeSlashIcon v-if="showPasswordConfirmation" class="h-5 w-5" />
              <EyeIcon v-else class="h-5 w-5" />
            </button>
          </div>

          <p v-if="passwordForm.password_confirmation && !passwordsMatch" class="mt-1 text-xs text-red-500">
            Las contraseñas no coinciden.
          </p>
        </div>

        <button
          type="submit"
          class="w-full rounded-md bg-black py-2.5 text-sm font-semibold text-white hover:bg-slate-900 disabled:opacity-60"
          :disabled="passwordForm.processing"
        >
          {{ passwordForm.processing ? 'Guardando...' : 'Actualizar contraseña' }}
        </button>
      </form>

    </div>
  </section>
</template>
