<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  roleLabel: {
    type: String,
    default: 'Competidor',
  },
});

const page = usePage();
const authUser = page.props.juez ?? page.props.auth?.user ?? page.props.user ?? {};

const currentPath = window.location.pathname;
const profileEndpoint = currentPath.startsWith('/admin')
  ? '/admin/profile'
  : currentPath.startsWith('/juez')
    ? '/juez/profile'
    : '/profile';

const photoEndpoint = `${profileEndpoint}/photo`;
const passwordEndpoint = `${profileEndpoint}/password`;

const isEditingProfile = ref(false);
const isEditingPassword = ref(false);
const selectedPhotoPreview = ref(null);
const showSuccessModal = ref(false);
const successMessage = ref('');
const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showPasswordConfirmation = ref(false);
let successTimer = null;

const profileForm = useForm({
  name: authUser.name || '',
  last_name: authUser.last_name || '',
  telefono: authUser.telefono || '+593',
});

const photoForm = useForm({
  photo: null,
});

const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const fullName = computed(() => {
  return `${authUser.name || ''} ${authUser.last_name || ''}`.trim() || 'Usuario';
});

const currentPhotoUrl = computed(() => {
  if (selectedPhotoPreview.value) return selectedPhotoPreview.value;
  if (authUser.photo_url) return authUser.photo_url;
  if (authUser.photo_path) return `/storage/${authUser.photo_path}`;
  return null;
});
const initials = computed(() => (authUser.name || authUser.email || 'U').charAt(0).toUpperCase());
const emailIsValid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(authUser.email || ''));

const passwordChecks = computed(() => {
  const value = passwordForm.password || '';

  return {
    minLength: value.length >= 8,
    uppercase: /[A-Z]/.test(value),
    lowercase: /[a-z]/.test(value),
    number: /\d/.test(value),
    special: /[!@#$%^&*(),.?":{}|<>\_\-+=\[\]\\;~]/.test(value),
  };
});

const passwordIsValid = computed(() => Object.values(passwordChecks.value).every(Boolean));
const canTypeNewPassword = computed(() => Boolean(passwordForm.current_password));

const openModal = (message) => {
  successMessage.value = message || 'Operación realizada correctamente.';
  showSuccessModal.value = true;

  if (successTimer) {
    clearTimeout(successTimer);
  }

  successTimer = setTimeout(() => {
    closeModal();
  }, 1400);
};

const closeModal = () => {
  if (successTimer) {
    clearTimeout(successTimer);
    successTimer = null;
  }

  showSuccessModal.value = false;
};

const syncUserData = (data) => {
  Object.assign(authUser, data);

  if (page.props.auth?.user) {
    Object.assign(page.props.auth.user, data);
  }

  if (page.props.user) {
    Object.assign(page.props.user, data);
  }

  if (page.props.juez) {
    Object.assign(page.props.juez, data);
  }
};

const xsrfToken = () => {
  const cookie = document.cookie
    .split('; ')
    .find((item) => item.startsWith('XSRF-TOKEN='));

  return cookie ? decodeURIComponent(cookie.split('=').slice(1).join('=')) : '';
};

const applyJsonErrors = (form, errors = {}) => {
  Object.entries(errors).forEach(([field, messages]) => {
    const message = Array.isArray(messages) ? messages[0] : messages;
    form.setError(field, message || 'Revisa este campo.');
  });
};

const submitJson = async (url, payload) => {
  const response = await fetch(url, {
    method: 'PUT',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': xsrfToken(),
    },
    body: JSON.stringify(payload),
  });

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    const error = new Error(data.message || 'No se pudo guardar la información.');
    error.status = response.status;
    error.errors = data.errors || {};
    throw error;
  }

  return data;
};

const cleanSpaces = (value) => (value || '').replace(/\s+/g, ' ').trim();

const sanitizeName = () => {
  profileForm.name = (profileForm.name || '')
    .replace(/[^\p{L}\s]/gu, '')
    .replace(/\s{2,}/g, ' ')
    .slice(0, 30);
};

const sanitizeLastName = () => {
  profileForm.last_name = (profileForm.last_name || '')
    .replace(/[^\p{L}\s]/gu, '')
    .replace(/\s{2,}/g, ' ')
    .slice(0, 30);
};

const onPhoneInput = () => {
  const digits = (profileForm.telefono || '').replace(/\D/g, '').slice(0, 14);
  profileForm.telefono = `+${digits}`;
};

const startEditProfile = () => {
  isEditingProfile.value = true;
};

const cancelEditProfile = () => {
  isEditingProfile.value = false;
  profileForm.name = authUser.name || '';
  profileForm.last_name = authUser.last_name || '';
  profileForm.telefono = authUser.telefono || '+593';
  profileForm.clearErrors();
};

const submitProfile = () => {
  sanitizeName();
  sanitizeLastName();
  onPhoneInput();

  profileForm.name = cleanSpaces(profileForm.name);
  profileForm.last_name = cleanSpaces(profileForm.last_name);

  profileForm.clearErrors();

  if (!profileForm.name) {
    profileForm.setError('name', 'El nombre es obligatorio.');
  }

  if (!profileForm.last_name) {
    profileForm.setError('last_name', 'El apellido es obligatorio.');
  }

  if (!/^\+\d{1,14}$/.test(profileForm.telefono)) {
    profileForm.setError('telefono', 'El teléfono debe iniciar con + y contener solo números.');
  }

  if (Object.keys(profileForm.errors).length) {
    return;
  }

  profileForm.processing = true;

  submitJson(profileEndpoint, {
    name: profileForm.name,
    last_name: profileForm.last_name,
    telefono: profileForm.telefono,
  })
    .then((data) => {
      syncUserData({
        name: data.user?.name ?? profileForm.name,
        last_name: data.user?.last_name ?? profileForm.last_name,
        telefono: data.user?.telefono ?? profileForm.telefono,
      });
      isEditingProfile.value = false;
      openModal(data.message || 'Perfil actualizado correctamente.');
    })
    .catch((error) => {
      applyJsonErrors(profileForm, error.errors);
    })
    .finally(() => {
      profileForm.processing = false;
    });
};

const onPhotoSelected = (event) => {
  const file = event.target.files?.[0] || null;

  photoForm.clearErrors();
  photoForm.photo = file;

  if (selectedPhotoPreview.value) {
    URL.revokeObjectURL(selectedPhotoPreview.value);
    selectedPhotoPreview.value = null;
  }

  if (!file) {
    return;
  }

  const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

  if (!allowedTypes.includes(file.type)) {
    photoForm.setError('photo', 'La foto debe estar en formato JPG, PNG o WEBP.');
    photoForm.photo = null;
    return;
  }

  if (file.size > 4 * 1024 * 1024) {
    photoForm.setError('photo', 'La foto no puede superar los 4 MB.');
    photoForm.photo = null;
    return;
  }

  selectedPhotoPreview.value = URL.createObjectURL(file);
};

const submitPhoto = () => {
  photoForm.clearErrors();

  if (!photoForm.photo) {
    photoForm.setError('photo', 'Selecciona una foto de perfil.');
    return;
  }

  photoForm.post(photoEndpoint, {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      if (selectedPhotoPreview.value) {
        syncUserData({
          photo_url: selectedPhotoPreview.value,
        });
      }
      openModal('Foto de perfil actualizada correctamente.');
    },
  });
};

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

  if (!passwordForm.current_password) {
    passwordForm.setError('current_password', 'Ingresa tu contraseña actual.');
  }

  if (!passwordIsValid.value) {
    passwordForm.setError(
      'password',
      'La nueva contraseña debe cumplir todos los requisitos de seguridad.'
    );
  }

  if (passwordForm.current_password && passwordForm.current_password === passwordForm.password) {
    passwordForm.setError('password', 'La nueva contraseña debe ser diferente a la contraseña actual.');
  }

  if (passwordForm.password !== passwordForm.password_confirmation) {
    passwordForm.setError('password', 'La confirmación de la nueva contraseña no coincide.');
  }

  if (Object.keys(passwordForm.errors).length) {
    return;
  }

  passwordForm.processing = true;

  submitJson(passwordEndpoint, {
    current_password: passwordForm.current_password,
    password: passwordForm.password,
    password_confirmation: passwordForm.password_confirmation,
  })
    .then((data) => {
      isEditingPassword.value = false;
      showCurrentPassword.value = false;
      showNewPassword.value = false;
      showPasswordConfirmation.value = false;
      passwordForm.reset();
      openModal(data.message || 'Contraseña actualizada correctamente.');
    })
    .catch((error) => {
      applyJsonErrors(passwordForm, error.errors);
    })
    .finally(() => {
      passwordForm.processing = false;
    });
};
</script>

<template>
  <div class="mx-auto w-full max-w-6xl space-y-4 px-3 pb-4 pt-0 sm:space-y-5 sm:px-6 lg:px-0">
    <header>
      <h1 class="text-2xl font-bold text-slate-950 sm:text-3xl">Mi Perfil</h1>
      <p class="mt-1 text-sm text-slate-500">Gestiona tu información personal</p>
    </header>

    <section class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm sm:p-6">
      <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
          <div class="relative h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-blue-600 text-white shadow-sm sm:h-28 sm:w-28 sm:rounded-3xl">
            <img
              v-if="currentPhotoUrl"
              :src="currentPhotoUrl"
              :alt="`Foto de perfil de ${fullName}`"
              class="h-full w-full object-cover"
            />
            <div v-else class="flex h-full w-full items-center justify-center text-3xl font-bold sm:text-4xl">
              {{ initials }}
            </div>
          </div>

          <div class="min-w-0">
            <p class="break-words text-lg font-bold text-slate-950">{{ fullName }}</p>
            <p class="break-all text-sm text-slate-500">{{ authUser.email }}</p>
            <p v-if="!emailIsValid" class="mt-1 text-xs text-red-600">
              El correo electrónico registrado no tiene un formato válido.
            </p>

            <div class="mt-3 flex flex-wrap gap-2 text-xs">
              <span class="rounded-full bg-blue-50 px-3 py-1 font-semibold text-blue-700">
                {{ props.roleLabel }}
              </span>
              <span class="rounded-full bg-green-50 px-3 py-1 font-semibold text-green-700">
                Cuenta activa
              </span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm sm:p-6">
      <div class="grid gap-5 lg:grid-cols-[13rem_1fr] lg:items-start">
        <div>
          <h2 class="text-base font-bold text-slate-950">Foto de perfil</h2>
          <p class="mt-1 text-xs leading-5 text-slate-500">
            Usa una imagen clara en formato JPG, PNG o WEBP.
          </p>
        </div>

        <div class="space-y-3">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <input
              id="photo"
              type="file"
              accept="image/png,image/jpeg,image/webp"
              class="block w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-700"
              @change="onPhotoSelected"
            />
            <button
              type="button"
              :disabled="photoForm.processing"
              @click="submitPhoto"
              class="w-full rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
            >
              {{ photoForm.processing ? 'Subiendo...' : 'Guardar foto' }}
            </button>
          </div>
          <p v-if="photoForm.errors.photo" class="text-xs text-red-600">{{ photoForm.errors.photo }}</p>
        </div>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm sm:p-8">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-base font-bold text-slate-950">Información personal</h2>

        <button
          v-if="!isEditingProfile"
          type="button"
          @click="startEditProfile"
          class="w-full rounded-xl bg-slate-950 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 sm:w-auto sm:rounded-full"
        >
          Editar perfil
        </button>
      </div>

      <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <div class="space-y-1">
          <label for="name" class="text-xs font-semibold text-slate-600">Nombre(s)</label>
          <input
            id="name"
            v-model="profileForm.name"
            :disabled="!isEditingProfile"
            type="text"
            maxlength="30"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 outline-none transition disabled:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            @input="sanitizeName"
          />
          <p v-if="profileForm.errors.name" class="text-xs text-red-600">{{ profileForm.errors.name }}</p>
        </div>

        <div class="space-y-1">
          <label for="last_name" class="text-xs font-semibold text-slate-600">Apellido(s)</label>
          <input
            id="last_name"
            v-model="profileForm.last_name"
            :disabled="!isEditingProfile"
            type="text"
            maxlength="30"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 outline-none transition disabled:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            @input="sanitizeLastName"
          />
          <p v-if="profileForm.errors.last_name" class="text-xs text-red-600">{{ profileForm.errors.last_name }}</p>
        </div>
      </div>

      <div class="mt-4 space-y-1">
        <label for="email" class="text-xs font-semibold text-slate-600">Correo electrónico</label>
        <input
          id="email"
          :value="authUser.email"
          disabled
          type="email"
          class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-500"
        />
        <p class="text-xs text-slate-400">El correo no puede modificarse porque es tu identificador de inicio de sesión.</p>
      </div>

      <div class="mt-4 space-y-1">
        <label for="telefono" class="text-xs font-semibold text-slate-600">Teléfono</label>
        <input
          id="telefono"
          v-model="profileForm.telefono"
          :disabled="!isEditingProfile"
          type="tel"
          maxlength="15"
          class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 outline-none transition disabled:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          @input="onPhoneInput"
        />
        <p class="text-xs text-slate-400">El signo + permanece fijo. Puedes usar hasta 14 números después del signo.</p>
        <p v-if="profileForm.errors.telefono" class="text-xs text-red-600">{{ profileForm.errors.telefono }}</p>
      </div>

      <div v-if="isEditingProfile" class="mt-6 flex flex-col gap-3 sm:flex-row">
        <button
          type="button"
          :disabled="profileForm.processing"
          class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
          @click="submitProfile"
        >
          {{ profileForm.processing ? 'Guardando...' : 'Guardar cambios' }}
        </button>

        <button
          type="button"
          class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
          @click="cancelEditProfile"
        >
          Cancelar
        </button>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm sm:p-8">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 class="text-base font-bold text-slate-950">Seguridad</h2>
          <p class="mt-1 text-xs text-slate-500">Gestiona tu contraseña de acceso.</p>
        </div>

        <button
          v-if="!isEditingPassword"
          type="button"
          class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-50"
          @click="startEditPassword"
        >
          Cambiar contraseña
        </button>
      </div>

      <div v-if="isEditingPassword" class="mt-5 space-y-4">
        <div class="space-y-1">
          <label for="current_password" class="text-xs font-semibold text-slate-600">Contraseña actual</label>
          <div class="relative">
            <input
              id="current_password"
              v-model="passwordForm.current_password"
              :type="showCurrentPassword ? 'text' : 'password'"
              autocomplete="current-password"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-3 pr-12 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            />
            <button
              type="button"
              class="absolute inset-y-0 right-3 flex items-center text-slate-500 transition hover:text-slate-900"
              :aria-label="showCurrentPassword ? 'Ocultar contraseña actual' : 'Mostrar contraseña actual'"
              @click="showCurrentPassword = !showCurrentPassword"
            >
              <EyeSlashIcon v-if="showCurrentPassword" class="h-5 w-5" />
              <EyeIcon v-else class="h-5 w-5" />
            </button>
          </div>
          <p v-if="passwordForm.errors.current_password" class="text-xs text-red-600">{{ passwordForm.errors.current_password }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div class="space-y-1">
            <label for="password" class="text-xs font-semibold text-slate-600">Nueva contraseña</label>
            <div class="relative">
              <input
                id="password"
                v-model="passwordForm.password"
                :type="showNewPassword ? 'text' : 'password'"
                :disabled="!canTypeNewPassword"
                autocomplete="new-password"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-3 pr-12 text-sm text-slate-700 outline-none transition disabled:cursor-not-allowed disabled:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              />
              <button
                type="button"
                :disabled="!canTypeNewPassword"
                class="absolute inset-y-0 right-3 flex items-center text-slate-500 transition hover:text-slate-900"
                :aria-label="showNewPassword ? 'Ocultar nueva contraseña' : 'Mostrar nueva contraseña'"
                @click="showNewPassword = !showNewPassword"
              >
                <EyeSlashIcon v-if="showNewPassword" class="h-5 w-5" />
                <EyeIcon v-else class="h-5 w-5" />
              </button>
            </div>
            <p v-if="passwordForm.errors.password" class="text-xs text-red-600">{{ passwordForm.errors.password }}</p>
          </div>

          <div class="space-y-1">
            <label for="password_confirmation" class="text-xs font-semibold text-slate-600">Confirmación de nueva contraseña</label>
            <div class="relative">
              <input
                id="password_confirmation"
                v-model="passwordForm.password_confirmation"
                :type="showPasswordConfirmation ? 'text' : 'password'"
                :disabled="!canTypeNewPassword"
                autocomplete="new-password"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-3 pr-12 text-sm text-slate-700 outline-none transition disabled:cursor-not-allowed disabled:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              />
              <button
                type="button"
                :disabled="!canTypeNewPassword"
                class="absolute inset-y-0 right-3 flex items-center text-slate-500 transition hover:text-slate-900"
                :aria-label="showPasswordConfirmation ? 'Ocultar confirmación de contraseña' : 'Mostrar confirmación de contraseña'"
                @click="showPasswordConfirmation = !showPasswordConfirmation"
              >
                <EyeSlashIcon v-if="showPasswordConfirmation" class="h-5 w-5" />
                <EyeIcon v-else class="h-5 w-5" />
              </button>
            </div>
          </div>
        </div>

        <div class="rounded-xl bg-slate-50 p-4">
          <p class="text-xs font-semibold text-slate-700">La nueva contraseña debe incluir:</p>
          <div class="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-2">
            <span :class="passwordChecks.minLength ? 'text-green-700' : ''">Mínimo 8 caracteres</span>
            <span :class="passwordChecks.uppercase ? 'text-green-700' : ''">Al menos una letra mayúscula</span>
            <span :class="passwordChecks.lowercase ? 'text-green-700' : ''">Al menos una letra minúscula</span>
            <span :class="passwordChecks.number ? 'text-green-700' : ''">Al menos un número</span>
            <span :class="passwordChecks.special ? 'text-green-700' : ''">Al menos un carácter especial</span>
          </div>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
          <button
            type="button"
            :disabled="passwordForm.processing"
            class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
            @click="submitPassword"
          >
            {{ passwordForm.processing ? 'Actualizando...' : 'Actualizar contraseña' }}
          </button>

          <button
            type="button"
            class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            @click="cancelEditPassword"
          >
            Cancelar
          </button>
        </div>
      </div>

      <p v-else class="mt-4 text-xs text-slate-500">
        Puedes actualizar tu contraseña cuando lo consideres necesario.
      </p>
    </section>

    <Teleport to="body">
      <transition name="fade">
        <div
          v-if="showSuccessModal"
          class="fixed left-0 top-0 z-[9999] flex h-dvh w-dvw items-center justify-center bg-black/40 px-4 backdrop-blur-sm"
        >
          <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-xl">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-green-50 text-2xl font-bold text-green-600">
              ✓
            </div>
            <h3 class="text-lg font-bold text-slate-950">Operación exitosa</h3>
            <p class="mt-2 text-sm text-slate-600">{{ successMessage }}</p>
            <button
              type="button"
              class="mt-5 w-full rounded-xl bg-slate-950 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
              @click="closeModal"
            >
              Cerrar
            </button>
          </div>
        </div>
      </transition>
    </Teleport>
  </div>
</template>

<style>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
