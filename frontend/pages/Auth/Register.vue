<template>
  <AppLayout>
    <section class="min-h-[70vh] flex items-center justify-center px-4">
      <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <h1 class="text-2xl font-semibold text-slate-900 mb-2">
          Crear cuenta
        </h1>
        <p class="text-sm text-slate-500 mb-6">
          Regístrate para gestionar tus competencias, inscripciones y resultados del evento.
        </p>

        <form @submit.prevent="onSubmit" class="space-y-4">
          <!-- Nombre completo -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1" for="name">
              Nombre completo
            </label>
            <input
              v-model="form.name"
              id="name"
              type="text"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm
                     focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              required
              @input="sanitizeName"
            />
            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">
              {{ form.errors.name }}
            </p>
          </div>

          <!-- Correo electrónico -->
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
            />
            <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">
              {{ form.errors.email }}
            </p>
          </div>

          <!-- Teléfono con combo país -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Teléfono
            </label>

            <div class="flex gap-2">
              <!-- País -->
              <select
                v-model="country"
                class="rounded-md border border-slate-300 px-2 py-2 text-sm bg-white
                       focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option
                  v-for="c in countries"
                  :key="c.code"
                  :value="c.code"
                >
                  {{ c.flag }} {{ c.name }} ({{ c.prefix }})
                </option>
              </select>

              <!-- Prefijo solo lectura -->
              <div
                class="px-3 py-2 rounded-md border border-slate-300 bg-slate-50 text-sm text-slate-700 flex items-center"
              >
                {{ currentPrefix }}
              </div>

              <!-- Número -->
              <input
                v-model="phoneNumber"
                type="text"
                class="flex-1 min-w-0 rounded-md border border-slate-300 py-2 pl-3 text-sm
                       focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                :placeholder="`Número (${currentMaxDigits} dígitos)`"
                @input="onPhoneInput"
                required
              />
            </div>

            <p v-if="phoneError" class="mt-1 text-xs text-red-500">
              {{ phoneError }}
            </p>
            <p v-else-if="form.errors.telefono" class="mt-1 text-xs text-red-500">
              {{ form.errors.telefono }}
            </p>
          </div>

          <!-- Institución -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1" for="institution">
              Institución
            </label>
            <input
              v-model="form.institucion"
              id="institution"
              type="text"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm
                     focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="escuela, colegio, universidad, etc."
              required
              @input="onInstitutionInput"
            />
            <p v-if="form.errors.institucion" class="mt-1 text-xs text-red-500">
              {{ form.errors.institucion }}
            </p>
          </div>

          <!-- Contraseña -->
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
              @input="passwordTouched = true"
            />
            <!-- Reglas de contraseña -->
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
                • Al menos un carácter especial (!@#$%^&amp;*, etc.)
              </li>
            </ul>
            <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">
              {{ form.errors.password }}
            </p>
          </div>

          <!-- Confirmar contraseña -->
          <div>
            <label
              class="block text-sm font-medium text-slate-700 mb-1"
              for="password_confirmation"
            >
              Confirmar contraseña
            </label>
            <input
              v-model="form.password_confirmation"
              id="password_confirmation"
              type="password"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm
                     focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              required
            />
            <p v-if="form.password_confirmation && !passwordsMatch" class="mt-1 text-xs text-red-500">
              Las contraseñas no coinciden.
            </p>
          </div>

          <!-- Error general frontend -->
          <div v-if="formError" class="text-xs text-red-500">
            {{ formError }}
          </div>

          <!-- Botón -->
          <button
            type="submit"
            class="w-full rounded-md bg-black text-white py-2.5 text-sm font-semibold hover:bg-slate-900 disabled:opacity-60"
            :disabled="form.processing"
          >
            <span v-if="!form.processing">Registrarme</span>
            <span v-else>Registrando...</span>
          </button>
        </form>

        <p class="mt-6 text-xs text-center text-slate-500">
          ¿Ya tienes cuenta?
          <Link href="/login" class="text-blue-600 hover:underline font-medium">
            Inicia sesión aquí
          </Link>
        </p>
      </div>
    </section>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';

// ==== FORMULARIO INERTIA ====
const form = useForm({
  name: '',
  email: '',
  institucion: '',
  telefono: '',
  password: '',
  password_confirmation: '',
});

// ==== CAMPOS AUXILIARES (NO VAN DIRECTO A BD) ====

// País seleccionado y configuración
const countries = [
  {
    code: 'EC',
    name: 'Ecuador',
    prefix: '+593',
    maxDigits: 9,
    flag: '🇪🇨',
  },
  // Aquí luego puedes agregar más países si lo necesitas
];

const country = ref('EC');          // Ecuador por defecto
const phoneNumber = ref('');        // Solo los dígitos del número
const phoneError = ref('');
const formError = ref('');
const passwordTouched = ref(false);

// País actual según selección
const currentCountry = computed(
  () => countries.find((c) => c.code === country.value) ?? countries[0]
);

const currentPrefix = computed(() => currentCountry.value.prefix);
const currentMaxDigits = computed(() => currentCountry.value.maxDigits);

// ==== NOMBRE Y INSTITUCIÓN ====

// Quita números y símbolos, deja solo letras y espacios, y lo pasa a mayúsculas
const sanitizeName = () => {
  form.name = form.name
    .replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñ\s]/g, '')
    .toUpperCase();
};

const onInstitutionInput = () => {
  form.institucion = form.institucion.toUpperCase();
};

// ==== CONTRASEÑA: REGLAS DE SEGURIDAD ====

// Reglas de contraseña basadas en form.password
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

// Clase CSS para cada regla de contraseña
const ruleClass = (ok) => {
  return ok ? 'text-green-600' : 'text-slate-500';
};

// ==== TELÉFONO: SOLO NÚMEROS Y LONGITUD CORRECTA ====
const onPhoneInput = () => {
  // Eliminar todo lo que no sea dígito
  phoneNumber.value = phoneNumber.value.replace(/\D/g, '');

  // Limitar la cantidad de dígitos
  if (phoneNumber.value.length > currentMaxDigits.value) {
    phoneNumber.value = phoneNumber.value.slice(0, currentMaxDigits.value);
  }

  // Mensaje de error local
  phoneError.value = '';
  if (
    phoneNumber.value.length > 0 &&
    phoneNumber.value.length < currentMaxDigits.value
  ) {
    phoneError.value = `El número debe tener exactamente ${currentMaxDigits.value} dígitos.`;
  }
};

// ==== ENVÍO DEL FORMULARIO (FRONTEND + BACKEND) ====
const onSubmit = () => {
  formError.value = '';
  phoneError.value = '';
  form.clearErrors();

  // 1) Validación de teléfono a nivel frontend
  if (phoneNumber.value.length !== currentMaxDigits.value) {
    phoneError.value = `El número debe tener exactamente ${currentMaxDigits.value} dígitos.`;
    return;
  }

  // 2) Validación de contraseña a nivel frontend
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

  // 3) Armar el teléfono completo para la BD: +593 + número
  const fullPhone = `${currentPrefix.value}${phoneNumber.value}`;
  form.telefono = fullPhone;

  // 4) Normalizar nombre e institución (por si acaso, aunque ya hay validaciones)
  form.name = form.name.toUpperCase();
  form.institucion = form.institucion.toUpperCase();

  // 5) Enviar al backend usando Inertia
  form.post('/register', {
    preserveScroll: true,
    onError: () => {
      // Los mensajes están en form.errors.<campo>
      // Ej: form.errors.email, form.errors.telefono, etc.
      // Aquí no hace falta hacer nada extra, ya los mostramos en el template.
    },
    onSuccess: () => {
      // Si el backend redirige al dashboard, aquí casi no se nota
      // Podrías limpiar campos si te quedaras en la misma página.
      // phoneNumber.value = '';
    },
  });
};
</script>
