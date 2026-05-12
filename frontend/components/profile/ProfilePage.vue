<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

// Recibimos el texto del rol: 'Competidor' o 'Administrador'
const props = defineProps({
  roleLabel: {
    type: String,
    default: 'Competidor',
  },
});

// ==== Page & usuario autenticado ====
const page = usePage();
const authUser = page.props.juez ?? page.props.auth?.user ?? page.props.user ?? {};

// ==== Estados de edición ====
const isEditingProfile = ref(false);
const isEditingPassword = ref(false);

// ==== Modal de éxito ====
const showSuccessModal = ref(false);
const successMessage = ref('');

const openModal = (message) => {
  successMessage.value = message || 'Operación realizada correctamente.';
  showSuccessModal.value = true;

  setTimeout(() => {
    closeModal();
  }, 1800);
};

const closeModal = () => {
  showSuccessModal.value = false;
  window.location.reload();
};

// ==== Formularios ====
const profileForm = useForm({
  name: authUser.name || '',
  last_name: authUser.last_name || '',
  telefono: authUser.telefono || '',
  institucion: authUser.institucion || '',
});

const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

// ==== Sanitizadores ====
const sanitizeName = () => {
  profileForm.name = (profileForm.name || '')
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^A-Za-zÑñ\s]/g, '')
    .toUpperCase();

  if (profileForm.name.length > 30) {
    profileForm.name = profileForm.name.slice(0, 30);
  }
};

const sanitizeLastName = () => {
  profileForm.last_name = (profileForm.last_name || '')
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^A-Za-zÑñ\s]/g, '')
    .toUpperCase();

  if (profileForm.last_name.length > 30) {
    profileForm.last_name = profileForm.last_name.slice(0, 30);
  }
};

const sanitizeInstitution = () => {
  profileForm.institucion = (profileForm.institucion || '')
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^A-Za-zÑñ\s]/g, '')
    .toUpperCase();
};

// Teléfono
const onPhoneInput = () => {
  let raw = profileForm.telefono || '';

  raw = raw.replace(/[^0-9+]/g, '');

  const hasPlus = raw.includes('+');
  raw = raw.replace(/\+/g, '');
  if (hasPlus) {
    raw = '+' + raw;
  }

  raw = raw.slice(0, 20);
  profileForm.telefono = raw;
};

// ==== Acciones Perfil ====
const startEditProfile = () => {
  isEditingProfile.value = true;
};

const cancelEditProfile = () => {
  isEditingProfile.value = false;
  profileForm.reset({
    name: authUser.name || '',
    last_name: authUser.last_name || '',
    telefono: authUser.telefono || '',
    institucion: authUser.institucion || '',
  });
  profileForm.clearErrors();
};

const submitProfile = () => {
  sanitizeName();
  sanitizeLastName();
  sanitizeInstitution();
  onPhoneInput();

  profileForm.put('/profile', {
    preserveScroll: true,
    onSuccess: () => {
      authUser.name = profileForm.name;
      authUser.last_name = profileForm.last_name;
      authUser.telefono = profileForm.telefono;
      authUser.institucion = profileForm.institucion;

      isEditingProfile.value = false;
      openModal('Perfil actualizado correctamente.');
    },
  });
};

// ==== Acciones Contraseña ====
const startEditPassword = () => {
  isEditingPassword.value = true;
};

const cancelEditPassword = () => {
  isEditingPassword.value = false;
  passwordForm.reset();
  passwordForm.clearErrors();
};

const submitPassword = () => {
  passwordForm.clearErrors();

  if (
    passwordForm.current_password &&
    passwordForm.password &&
    passwordForm.current_password === passwordForm.password
  ) {
    passwordForm.setError(
      'password',
      'La nueva contraseña debe ser diferente a la contraseña actual.'
    );
    return;
  }

  passwordForm.put('/profile/password', {
    preserveScroll: true,
    onSuccess: () => {
      isEditingPassword.value = false;
      passwordForm.reset();
      openModal('Contraseña actualizada correctamente.');
    },
  });
};
</script>

<template>
  <div class="max-w-5xl mx-auto space-y-6">
    <!-- Título -->
    <header class="mb-4">
      <h1 class="text-2xl font-bold text-slate-900">Mi Perfil</h1>
      <p class="text-sm text-slate-500">
        Gestiona tu información personal
      </p>
    </header>

    <!--  Tarjeta 1: Resumen de perfil-->
    <section
      class="bg-white rounded-2xl shadow-sm border border-slate-100
             px-6 py-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4"
    >
      <div class="flex items-center gap-4">
        <!-- Avatar -->
        <div
          class="h-24 w-24 rounded-[24px] bg-blue-600 flex items-center justify-center
                 text-3xl font-semibold text-white"
        >
          {{ (authUser.name || 'U').charAt(0).toUpperCase() }}
        </div>

        <!-- Nombre + correo + chips -->
        <div>
          <p class="text-base font-semibold text-slate-900 flex gap-1">
            <span>{{ authUser.name }}</span>
            <span v-if="authUser.last_name"> {{ authUser.last_name }}</span>
          </p>
          <p class="text-sm text-slate-500">
            {{ authUser.email }}
          </p>

          <div class="mt-2 flex flex-wrap gap-2 text-xs">
            <span
              class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 font-medium text-blue-700"
            >
              {{ props.roleLabel }}
            </span>
            <span
              class="inline-flex items-center rounded-full bg-green-50 px-3 py-1 font-medium text-green-700"
            >
              Cuenta Activa
            </span>
          </div>
        </div>
      </div>

      <!-- Botón Editar Perfil -->
      <button
        v-if="!isEditingProfile"
        type="button"
        @click="startEditProfile"
        class="self-end md:self-auto rounded-full bg-slate-900 px-6 py-2
               text-sm font-semibold text-white hover:bg-black"
      >
        Editar Perfil
      </button>
    </section>
    <!--  Tarjeta 2: Información Personal -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-100 px-8 py-6 space-y-4">
      <h2 class="text-base font-semibold text-slate-900">
        Información Personal
      </h2>

      <!-- Nombre(s) y Apellido(s) -->
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <!-- Nombre(s) -->
        <div class="space-y-1">
          <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
            <span>Nombre(s)</span>
          </label>
          <input
            v-model="profileForm.name"
            :disabled="!isEditingProfile"
            @input="sanitizeName"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                   bg-slate-50 disabled:bg-slate-50 disabled:text-slate-500
                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            type="text"
          />
          <p v-if="profileForm.errors.name" class="text-[11px] text-red-600">
            {{ profileForm.errors.name }}
          </p>
        </div>

        <!-- Apellido(s) -->
        <div class="space-y-1">
          <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
            <span>Apellido(s)</span>
          </label>
          <input
            v-model="profileForm.last_name"
            :disabled="!isEditingProfile"
            @input="sanitizeLastName"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                   bg-slate-50 disabled:bg-slate-50 disabled:text-slate-500
                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            type="text"
          />
          <p v-if="profileForm.errors.last_name" class="text-[11px] text-red-600">
            {{ profileForm.errors.last_name }}
          </p>
        </div>
      </div>

      <!-- Correo (solo lectura) -->
      <div class="space-y-1">
        <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
          <span>Correo Electrónico</span>
        </label>
        <input
          :value="authUser.email"
          disabled
          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                 bg-slate-50 text-slate-500"
          type="email"
        />
        <p class="text-[11px] text-slate-400">
          El correo no puede modificarse ya que es tu identificador de inicio de sesión.
        </p>
      </div>

      <!-- Teléfono -->
      <div class="space-y-1">
        <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
          <span>Teléfono</span>
        </label>
        <input
          v-model="profileForm.telefono"
          :disabled="!isEditingProfile"
          @input="onPhoneInput"
          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                 bg-slate-50 disabled:bg-slate-50 disabled:text-slate-500
                 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          type="text"
          placeholder="+593991234567"
        />
        <p class="text-[11px] text-slate-400">
          Incluye el código de país (por ejemplo, +593 para Ecuador).
        </p>
        <p v-if="profileForm.errors.telefono" class="text-[11px] text-red-600">
          {{ profileForm.errors.telefono }}
        </p>
      </div>

      <!-- Institución -->
      <div class="space-y-1">
        <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
          <span>Institución</span>
        </label>
        <input
          v-model="profileForm.institucion"
          :disabled="!isEditingProfile"
          @input="sanitizeInstitution"
          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                 bg-slate-50 disabled:bg-slate-50 disabled:text-slate-500
                 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          type="text"
          placeholder="ESPOCH"
        />
        <p class="text-[11px] text-slate-400">
          La institución educativa a la que representas.
        </p>
        <p v-if="profileForm.errors.institucion" class="text-[11px] text-red-600">
          {{ profileForm.errors.institucion }}
        </p>
      </div>

      <!-- Botones Guardar / Cancelar SOLO cuando está editando -->
      <div
        v-if="isEditingProfile"
        class="pt-3 flex gap-3"
      >
        <button
          type="button"
          @click="submitProfile"
          :disabled="profileForm.processing"
          class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white
                 hover:bg-black disabled:opacity-60 disabled:cursor-not-allowed"
        >
          {{ profileForm.processing ? 'Guardando...' : 'Guardar Cambios' }}
        </button>

        <button
          type="button"
          @click="cancelEditProfile"
          class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
          Cancelar
        </button>
      </div>
    </section>

    <!--  Tarjeta 3: Seguridad -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-100 px-8 py-6 space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base font-semibold text-slate-900">Seguridad</h2>
          <p class="text-xs text-slate-500">
            Gestiona tu contraseña y seguridad de cuenta.
          </p>
        </div>

        <!-- Botón Cambiar contraseña cuando NO está editando -->
        <button
          v-if="!isEditingPassword"
          type="button"
          @click="startEditPassword"
          class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50"
        >
          Cambiar Contraseña
        </button>
      </div>

      <!-- Vista simple cuando NO edita -->
      <div v-if="!isEditingPassword">
        <p class="text-xs text-slate-500">
          Puedes actualizar tu contraseña cuando lo consideres necesario.
        </p>
      </div>

      <!-- Formulario de contraseña cuando SÍ edita -->
      <div v-else class="space-y-3">
        <!-- Contraseña actual -->
        <div class="space-y-1">
          <label class="text-xs font-semibold text-slate-600">
            Contraseña Actual
          </label>
          <input
            v-model="passwordForm.current_password"
            type="password"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                   bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            placeholder="Ingresa tu contraseña actual"
          />
          <p class="text-[11px] text-slate-400">
            Por seguridad, necesitas verificar tu contraseña actual.
          </p>
          <p v-if="passwordForm.errors.current_password" class="text-[11px] text-red-600">
            {{ passwordForm.errors.current_password }}
          </p>
        </div>

        <!-- Nueva contraseña -->
        <div class="space-y-1">
          <label class="text-xs font-semibold text-slate-600">
            Nueva Contraseña
          </label>
          <input
            v-model="passwordForm.password"
            type="password"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                   bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            placeholder="Ingresa tu nueva contraseña"
          />
          <p class="text-[11px] text-slate-400">
            Debe ser diferente a tu contraseña actual, mínimo 8 caracteres e incluir mayúsculas, minúsculas y números.
          </p>
          <p v-if="passwordForm.errors.password" class="text-[11px] text-red-600">
            {{ passwordForm.errors.password }}
          </p>
        </div>

        <!-- Confirmación -->
        <div class="space-y-1">
          <label class="text-xs font-semibold text-slate-600">
            Confirmar Nueva Contraseña
          </label>
          <input
            v-model="passwordForm.password_confirmation"
            type="password"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                   bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            placeholder="Confirma tu nueva contraseña"
          />
        </div>

        <!-- Botones -->
        <div class="pt-2 flex gap-3">
          <button
            type="button"
            @click="submitPassword"
            :disabled="passwordForm.processing"
            class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white
                   hover:bg-black disabled:opacity-60 disabled:cursor-not-allowed"
          >
            {{ passwordForm.processing ? 'Actualizando...' : 'Actualizar Contraseña' }}
          </button>

          <button
            type="button"
            @click="cancelEditPassword"
            class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
          >
            Cancelar
          </button>
        </div>
      </div>
    </section>

    <!--  Tarjeta 4: Campos editables -->
    <section class="bg-blue-50 border border-blue-100 rounded-2xl px-6 py-4 text-xs text-slate-700">
      <p class="font-semibold mb-2">Campos editables</p>
      <ul class="space-y-1">
        <li>✓ <strong>Nombre(s) y Apellido(s):</strong> Para certificados y registros oficiales.</li>
        <li>✓ <strong>Teléfono:</strong> Importante para notificaciones urgentes de competencias.</li>
        <li>✓ <strong>Institución:</strong> Define a qué institución representas en competencias.</li>
        <li>✗ <strong>Email:</strong> No editable (es tu identificador único de inicio de sesión).</li>
      </ul>
    </section>

    <!-- MODAL DE ÉXITO -->
    <transition name="fade">
      <div
        v-if="showSuccessModal"
        class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50"
      >
        <div
          class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm text-center animate-fade-in"
        >
          <div class="text-green-600 text-4xl mb-3">✔</div>

          <h3 class="text-lg font-semibold text-slate-900 mb-2">
            ¡Operación exitosa!
          </h3>

          <p class="text-sm text-slate-600 mb-4">
            {{ successMessage }}
          </p>

          <button
            @click="closeModal"
            class="w-full py-2 rounded-lg bg-slate-900 text-white font-medium hover:bg-black"
          >
            Cerrar
          </button>
        </div>
      </div>
    </transition>
  </div>
</template>

<style>
.fade-enter-active,
.fade-leave-active {
  transition: opacity .25s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
