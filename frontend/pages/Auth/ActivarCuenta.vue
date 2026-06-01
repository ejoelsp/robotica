<script setup>
import { computed, ref } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import { EyeIcon, EyeSlashIcon } from "@heroicons/vue/24/outline";

const page = usePage();
const tokenFromUrl = new URLSearchParams(window.location.search).get("token") || "";

const form = useForm({
  token: page.props.token ?? tokenFromUrl,
  password: "",
  password_confirmation: "",
});

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const passwordChecks = computed(() => {
  const value = form.password || "";

  return {
    minLength: value.length >= 8,
    uppercase: /[A-Z]/.test(value),
    lowercase: /[a-z]/.test(value),
    number: /[0-9]/.test(value),
    special: /[^A-Za-z0-9]/.test(value),
  };
});

const passwordsMatch = computed(() => {
  return form.password.length > 0 && form.password === form.password_confirmation;
});

const passwordIsValid = computed(() => {
  return Object.values(passwordChecks.value).every(Boolean);
});

const canSubmit = computed(() => {
  return Boolean(form.token)
    && passwordIsValid.value
    && passwordsMatch.value
    && !form.processing;
});

const submit = () => {
  form.clearErrors();
  form.token = form.token || tokenFromUrl;

  form.post("/activar-cuenta", {
    preserveScroll: true,
  });
};
</script>

<template>
  <div class="bg-slate-50">
    <div class="flex min-h-[calc(100vh-160px)] items-center justify-center px-4 py-8">
      <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-8 shadow-xl">
        <h1 class="text-2xl font-bold text-slate-900">Activación de cuenta</h1>
        <p class="mt-2 text-sm text-slate-500">
          Define tu contraseña para completar el acceso.
        </p>

        <form class="mt-6 space-y-5" @submit.prevent="submit">
          <div
            v-if="form.errors.general || form.errors.token"
            class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
          >
            {{ form.errors.general || form.errors.token }}
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">
              Contraseña
            </label>

            <div class="relative">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />

              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 transition hover:text-slate-700"
              >
                <EyeIcon v-if="!showPassword" class="h-5 w-5" />
                <EyeSlashIcon v-else class="h-5 w-5" />
              </button>
            </div>

            <ul class="mt-3 space-y-1 text-sm">
              <li :class="passwordChecks.minLength ? 'text-green-600' : 'text-slate-500'">
                • Mínimo 8 caracteres
              </li>
              <li :class="passwordChecks.uppercase ? 'text-green-600' : 'text-slate-500'">
                • Al menos una letra mayúscula
              </li>
              <li :class="passwordChecks.lowercase ? 'text-green-600' : 'text-slate-500'">
                • Al menos una letra minúscula
              </li>
              <li :class="passwordChecks.number ? 'text-green-600' : 'text-slate-500'">
                • Al menos un número
              </li>
              <li :class="passwordChecks.special ? 'text-green-600' : 'text-slate-500'">
                • Al menos un carácter especial (!@#$%^&*, etc.)
              </li>
            </ul>

            <p v-if="form.errors.password" class="mt-2 text-xs text-red-600">
              {{ form.errors.password }}
            </p>
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">
              Confirmar contraseña
            </label>

            <div class="relative">
              <input
                v-model="form.password_confirmation"
                :type="showPasswordConfirmation ? 'text' : 'password'"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />

              <button
                type="button"
                @click="showPasswordConfirmation = !showPasswordConfirmation"
                class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 transition hover:text-slate-700"
              >
                <EyeIcon v-if="!showPasswordConfirmation" class="h-5 w-5" />
                <EyeSlashIcon v-else class="h-5 w-5" />
              </button>
            </div>

            <p
              v-if="form.password_confirmation && !passwordsMatch"
              class="mt-2 text-xs text-red-600"
            >
              La confirmación de la contraseña no coincide.
            </p>

            <p v-if="form.errors.password_confirmation" class="mt-2 text-xs text-red-600">
              {{ form.errors.password_confirmation }}
            </p>
          </div>

          <button
            type="submit"
            :disabled="!canSubmit"
            class="w-full rounded-2xl bg-blue-600 px-5 py-3 font-medium text-white transition hover:bg-blue-700 disabled:opacity-60"
          >
            {{ form.processing ? "Guardando..." : "Activar cuenta" }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
