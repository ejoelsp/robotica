<script setup>
import AdminLayout from "@/layouts/AdminLayout.vue";
import { computed, ref, watch } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import {
  ArrowPathIcon,
  CheckCircleIcon,
  ClockIcon,
  LockClosedIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  ShieldCheckIcon,
  UserGroupIcon,
  UserPlusIcon,
  XCircleIcon,
  XMarkIcon,
} from "@heroicons/vue/24/outline";
import { formatEcuadorMediumDateTime } from "@/lib/datetime";

defineOptions({ layout: AdminLayout });

const page = usePage();

const activeTab = ref("usuarios");
const searchTerm = ref("");
const auditSearch = ref("");
const auditStatus = ref("todos");
const showUserModal = ref(false);
const processingUserId = ref(null);

const usuarios = computed(() => page.props.usuarios ?? []);
const roles = computed(() => page.props.roles ?? []);
const auditorias = computed(() => page.props.auditorias ?? []);
const seguridad = computed(() => page.props.seguridad ?? {});
const flash = computed(() => page.props.flash ?? {});
const errors = computed(() => page.props.errors ?? {});

const userForm = useForm({
  name: "",
  last_name: "",
  email: "",
  telefono: "+593",
  rol: "admin",
});

const touchedFields = ref({
  name: false,
  last_name: false,
  email: false,
  telefono: false,
});

const namePattern = /^[\p{L}\s]+$/u;
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const phonePattern = /^\+\d{1,14}$/;

const roleOptions = computed(() =>
  roles.value.filter((role) => ["admin", "juez"].includes(role.nombre))
);

const filteredUsuarios = computed(() => {
  const term = searchTerm.value.trim().toLowerCase();
  if (!term) return usuarios.value;

  return usuarios.value.filter((user) => {
    return [
      user.nombre_completo,
      user.email,
      user.telefono,
      user.rol_label,
      user.rol,
    ]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(term));
  });
});

const filteredAuditorias = computed(() => {
  const term = auditSearch.value.trim().toLowerCase();

  return auditorias.value.filter((log) => {
    const matchesTerm =
      !term ||
      [
        log.ocurrio_en,
        log.usuario,
        log.email,
        log.accion,
        log.modulo,
        log.descripcion,
        log.ip_address,
        log.estado,
      ]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(term));

    const matchesStatus = auditStatus.value === "todos" || log.estado === auditStatus.value;

    return matchesTerm && matchesStatus;
  });
});

const stats = computed(() => {
  const admins = usuarios.value.filter((user) => user.rol === "admin").length;
  const jueces = usuarios.value.filter((user) => user.rol === "juez").length;
  const competidores = usuarios.value.filter((user) => user.rol === "competidor").length;
  const activos = usuarios.value.filter((user) => user.estado).length;

  return [
    { label: "Administradores", value: admins, icon: ShieldCheckIcon },
    { label: "Jueces", value: jueces, icon: UserGroupIcon },
    { label: "Competidores", value: competidores, icon: UserGroupIcon },
    { label: "Usuarios activos", value: activos, icon: CheckCircleIcon },
  ];
});

const securityCards = computed(() => [
  {
    label: "Intentos fallidos",
    value: seguridad.value.intentos_maximos ?? 5,
    helper: "Intentos máximos permitidos",
    icon: LockClosedIcon,
  },
  {
    label: "Bloqueo temporal",
    value: seguridad.value.bloqueo_minutos ?? 3,
    helper: "Minutos de bloqueo",
    icon: ClockIcon,
  },
  {
    label: "Sesión general",
    value: seguridad.value.session_lifetime_minutos ?? 60,
    helper: "Minutos de inactividad",
    icon: ShieldCheckIcon,
  },
  {
    label: "Sesiones activas",
    value: seguridad.value.sesiones_activas ?? 0,
    helper: "Sesiones vigentes",
    icon: UserGroupIcon,
  },
]);

const successMessage = ref("");

watch(
  () => flash.value.success,
  (message) => {
    successMessage.value = message || "";
  },
  { immediate: true }
);

function openCreateUser() {
  userForm.reset();
  userForm.clearErrors();
  userForm.rol = "admin";
  userForm.telefono = "+593";
  touchedFields.value = {
    name: false,
    last_name: false,
    email: false,
    telefono: false,
  };
  showUserModal.value = true;
}

function closeCreateUser() {
  showUserModal.value = false;
  userForm.clearErrors();
}

function submitUser() {
  touchedFields.value = {
    name: true,
    last_name: true,
    email: true,
    telefono: true,
  };

  if (!canSubmitUser.value) return;

  userForm.post("/admin/control-acceso/usuarios", {
    preserveScroll: true,
    onSuccess: () => closeCreateUser(),
  });
}

const fieldErrors = computed(() => {
  const errors = {};
  const name = userForm.name.trim();
  const lastName = userForm.last_name.trim();
  const email = userForm.email.trim();
  const phone = userForm.telefono.trim();

  if (!name) {
    errors.name = "El nombre es obligatorio.";
  } else if (!namePattern.test(name)) {
    errors.name = "El nombre solo puede contener letras y espacios.";
  }

  if (!lastName) {
    errors.last_name = "El apellido es obligatorio.";
  } else if (!namePattern.test(lastName)) {
    errors.last_name = "El apellido solo puede contener letras y espacios.";
  }

  if (!email) {
    errors.email = "El correo electrónico es obligatorio.";
  } else if (!emailPattern.test(email)) {
    errors.email = "Ingresa un correo electrónico válido.";
  }

  if (!phone || phone === "+") {
    errors.telefono = "El teléfono es obligatorio.";
  } else if (!phonePattern.test(phone)) {
    errors.telefono = "Usa el formato +593 seguido solo de números, sin espacios.";
  }

  return errors;
});

const canSubmitUser = computed(() => {
  return Object.keys(fieldErrors.value).length === 0 && !userForm.processing;
});

function visibleFieldError(field) {
  return touchedFields.value[field] ? fieldErrors.value[field] || userForm.errors[field] : userForm.errors[field];
}

function markTouched(field) {
  touchedFields.value[field] = true;
  if (userForm.errors[field]) userForm.clearErrors(field);
}

function sanitizeName(field) {
  userForm[field] = userForm[field].replace(/[^\p{L}\s]/gu, "");
  if (userForm.errors[field]) userForm.clearErrors(field);
}

function sanitizeEmail() {
  userForm.email = userForm.email.replace(/\s/g, "").toLowerCase();
  if (userForm.errors.email) userForm.clearErrors("email");
}

function sanitizePhone() {
  const digits = userForm.telefono.replace(/\D/g, "").slice(0, 14);
  userForm.telefono = `+${digits}`.slice(0, 15);

  if (userForm.errors.telefono) userForm.clearErrors("telefono");
}

function toggleEstado(user) {
  const nextState = !user.estado;
  processingUserId.value = user.id;

  router.patch(
    `/admin/control-acceso/usuarios/${user.id}/estado`,
    { estado: nextState },
    {
      preserveScroll: true,
      onFinish: () => {
        processingUserId.value = null;
      },
    }
  );
}

function initials(user) {
  const first = user.name?.charAt(0) || "";
  const last = user.last_name?.charAt(0) || "";
  return `${first}${last}`.toUpperCase() || "U";
}

function formatDate(value) {
  return formatEcuadorMediumDateTime(value, value || "Sin registro");
}

function estadoClass(active) {
  return active
    ? "bg-emerald-50 text-emerald-700 ring-emerald-200"
    : "bg-slate-100 text-slate-700 ring-slate-200";
}

function roleClass(role) {
  switch (role) {
    case "admin":
      return "bg-blue-50 text-blue-700 ring-blue-200";
    case "juez":
      return "bg-violet-50 text-violet-700 ring-violet-200";
    case "competidor":
      return "bg-amber-50 text-amber-800 ring-amber-200";
    default:
      return "bg-slate-50 text-slate-700 ring-slate-200";
  }
}

function auditStatusClass(status) {
  return status === "fallido"
    ? "bg-rose-50 text-rose-700 ring-rose-200"
    : "bg-emerald-50 text-emerald-700 ring-emerald-200";
}

function actionLabel(action) {
  const labels = {
    login_exitoso: "Inicio de sesión",
    login_fallido: "Login fallido",
    login_bloqueado: "Login bloqueado",
    login_usuario_inactivo: "Usuario inactivo",
    logout: "Cierre de sesión",
    crear_usuario: "Crear usuario",
    activar_usuario: "Activar usuario",
    desactivar_usuario: "Desactivar usuario",
  };

  return labels[action] || String(action || "Evento").replaceAll("_", " ");
}
</script>

<template>
  <div class="px-3 py-5 sm:px-6 sm:py-6 lg:px-4">
    <div class="mb-7">
      <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Control de Acceso</h1>
      <p class="text-sm text-slate-500">
        Administra usuarios y revisa la auditoría del sistema.
      </p>
    </div>

    <div v-if="successMessage" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
      {{ successMessage }}
    </div>

    <div v-if="errors.estado" class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
      {{ errors.estado }}
    </div>

    <div class="mb-6 overflow-x-auto">
      <div class="inline-flex w-full rounded-2xl bg-slate-200/70 p-1 sm:w-auto sm:rounded-full">
        <button
          class="flex-1 px-4 py-2 text-sm rounded-xl transition whitespace-nowrap sm:flex-none sm:rounded-full"
          :class="activeTab === 'usuarios' ? 'bg-white shadow-sm text-slate-900 font-semibold' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'usuarios'"
        >
          Usuarios
        </button>
        <button
          class="flex-1 px-4 py-2 text-sm rounded-xl transition whitespace-nowrap sm:flex-none sm:rounded-full"
          :class="activeTab === 'auditoria' ? 'bg-white shadow-sm text-slate-900 font-semibold' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'auditoria'"
        >
          Auditoría
        </button>
      </div>
    </div>

    <section v-if="activeTab === 'usuarios'" class="space-y-5">
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
          <h2 class="text-xl font-bold text-slate-900">Usuarios del sistema</h2>
          <p class="text-sm text-slate-500">
            Lista administradores, jueces y competidores registrados.
          </p>
        </div>
        <button
          type="button"
          class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 sm:w-auto"
          @click="openCreateUser"
        >
          <UserPlusIcon class="w-5 h-5 mr-2" />
          Crear usuario
        </button>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
        <div
          v-for="item in securityCards"
          :key="item.label"
          class="min-h-32 rounded-xl border border-slate-200 bg-white p-4 shadow-sm flex flex-col justify-between"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-sm font-semibold text-slate-900">{{ item.label }}</p>
              <p class="mt-1 text-xs leading-5 text-slate-500">{{ item.helper }}</p>
            </div>
            <component :is="item.icon" class="w-5 h-5 text-slate-400 shrink-0" />
          </div>
          <p class="text-3xl font-bold leading-none text-slate-900">{{ item.value }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 sm:gap-4">
        <div v-for="item in stats" :key="item.label" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div class="flex items-start justify-between">
            <p class="text-sm font-semibold text-slate-700">{{ item.label }}</p>
            <component :is="item.icon" class="w-5 h-5 text-slate-400" />
          </div>
          <p class="mt-4 text-3xl font-bold text-slate-900">{{ item.value }}</p>
        </div>
      </div>

      <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200">
          <h3 class="text-lg font-semibold text-slate-900">Listado de usuarios</h3>
          <div class="relative mt-4">
            <MagnifyingGlassIcon class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              v-model="searchTerm"
              type="text"
              placeholder="Buscar por nombre, correo, teléfono o rol..."
              class="w-full rounded-xl border border-slate-200 bg-white px-10 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
            />
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-[920px] w-full">
            <thead class="bg-slate-50">
              <tr class="text-left text-xs font-semibold text-slate-600">
                <th class="px-5 py-4">Usuario</th>
                <th class="px-5 py-4">Rol</th>
                <th class="px-5 py-4">Estado</th>
                <th class="px-5 py-4">Sesiones activas</th>
                <th class="px-5 py-4">Registro</th>
                <th class="px-5 py-4 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="user in filteredUsuarios" :key="user.id" class="hover:bg-slate-50/70">
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <div class="h-11 w-11 rounded-full bg-slate-100 flex items-center justify-center text-sm font-bold text-slate-700">
                      {{ initials(user) }}
                    </div>
                    <div>
                      <p class="text-sm font-semibold text-slate-900">{{ user.nombre_completo }}</p>
                      <p class="text-xs text-slate-500">{{ user.email }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4">
                  <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1" :class="roleClass(user.rol)">
                    {{ user.rol_label }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1" :class="estadoClass(user.estado)">
                    {{ user.estado ? "Activo" : "Inactivo" }}
                  </span>
                </td>
                <td class="px-5 py-4 text-sm text-slate-700">
                  {{ user.sesiones_activas }}
                </td>
                <td class="px-5 py-4 text-sm text-slate-700 whitespace-nowrap">
                  {{ formatDate(user.created_at) }}
                </td>
                <td class="px-5 py-4 text-right">
                  <button
                    type="button"
                    class="inline-flex items-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold hover:bg-slate-50 disabled:opacity-60"
                    :disabled="processingUserId === user.id"
                    @click="toggleEstado(user)"
                  >
                    <ArrowPathIcon v-if="processingUserId === user.id" class="w-4 h-4 mr-2 animate-spin" />
                    <XCircleIcon v-else-if="user.estado" class="w-4 h-4 mr-2 text-rose-600" />
                    <CheckCircleIcon v-else class="w-4 h-4 mr-2 text-emerald-600" />
                    {{ user.estado ? "Desactivar" : "Activar" }}
                  </button>
                </td>
              </tr>
              <tr v-if="filteredUsuarios.length === 0">
                <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500">
                  No se encontraron usuarios con el filtro actual.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section v-else-if="activeTab === 'auditoria'" class="space-y-5">
      <div>
        <h2 class="text-xl font-bold text-slate-900">Auditoría</h2>
        <p class="text-sm text-slate-500">
          Consulta los eventos registrados en el sistema.
        </p>
      </div>

      <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200">
          <div class="flex flex-col lg:flex-row gap-3">
            <div class="relative flex-1">
              <MagnifyingGlassIcon class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                v-model="auditSearch"
                type="text"
                placeholder="Buscar por usuario, acción, módulo, descripción o IP..."
                class="w-full rounded-xl border border-slate-200 bg-white px-10 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
              />
            </div>
            <select
              v-model="auditStatus"
              class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
            >
              <option value="todos">Todos los estados</option>
              <option value="exitoso">Exitosos</option>
              <option value="fallido">Fallidos</option>
            </select>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-[920px] w-full">
            <thead class="bg-slate-50">
              <tr class="text-left text-xs font-semibold text-slate-600">
                <th class="px-5 py-4">Fecha</th>
                <th class="px-5 py-4">Usuario</th>
                <th class="px-5 py-4">Acción</th>
                <th class="px-5 py-4">Descripción</th>
                <th class="px-5 py-4">IP</th>
                <th class="px-5 py-4">Estado</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="log in filteredAuditorias" :key="log.id" class="hover:bg-slate-50/70">
                <td class="px-5 py-4 text-sm text-slate-700 whitespace-nowrap">{{ formatDate(log.ocurrio_en) }}</td>
                <td class="px-5 py-4">
                  <p class="text-sm font-semibold text-slate-900">{{ log.usuario }}</p>
                  <p class="text-xs text-slate-500">{{ log.email || "Sin correo asociado" }}</p>
                </td>
                <td class="px-5 py-4 text-sm text-slate-700 whitespace-nowrap">{{ actionLabel(log.accion) }}</td>
                <td class="px-5 py-4 text-sm text-slate-700">{{ log.descripcion || log.modulo || "Sin descripción" }}</td>
                <td class="px-5 py-4 text-sm text-slate-700 whitespace-nowrap">{{ log.ip_address || "Sin IP" }}</td>
                <td class="px-5 py-4">
                  <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1" :class="auditStatusClass(log.estado)">
                    {{ log.estado === "fallido" ? "Fallido" : "Exitoso" }}
                  </span>
                </td>
              </tr>
              <tr v-if="filteredAuditorias.length === 0">
                <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500">
                  No hay eventos de auditoría para mostrar.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <Teleport to="body">
      <div v-if="showUserModal" class="fixed inset-0 z-[9999]">
        <div class="absolute inset-0 bg-black/40" @click="closeCreateUser"></div>
        <div class="relative h-full w-full grid place-items-center p-4">
          <div class="w-full max-w-lg rounded-xl bg-white border border-slate-200 shadow-xl overflow-hidden">
            <div class="p-5 border-b border-slate-200 flex items-start justify-between gap-4">
              <div>
                <h2 class="text-lg font-semibold text-slate-900">Crear usuario</h2>
                <p class="text-sm text-slate-500 mt-1">
                  Crea una cuenta de administrador o juez y envía su enlace de activación.
                </p>
              </div>
              <button type="button" class="h-9 w-9 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-center justify-center" @click="closeCreateUser">
                <XMarkIcon class="w-5 h-5 text-slate-600" />
              </button>
            </div>

            <form class="p-5 space-y-4" @submit.prevent="submitUser">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Nombre(s):</label>
                  <input
                    v-model="userForm.name"
                    type="text"
                    autocomplete="given-name"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
                    :class="visibleFieldError('name') ? 'border-rose-300 focus:ring-rose-100' : ''"
                    @input="sanitizeName('name')"
                    @blur="markTouched('name')"
                  />
                  <p v-if="visibleFieldError('name')" class="mt-1 text-xs text-rose-600">{{ visibleFieldError("name") }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Apellido(s):</label>
                  <input
                    v-model="userForm.last_name"
                    type="text"
                    autocomplete="family-name"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
                    :class="visibleFieldError('last_name') ? 'border-rose-300 focus:ring-rose-100' : ''"
                    @input="sanitizeName('last_name')"
                    @blur="markTouched('last_name')"
                  />
                  <p v-if="visibleFieldError('last_name')" class="mt-1 text-xs text-rose-600">{{ visibleFieldError("last_name") }}</p>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Correo electrónico:</label>
                <input
                  v-model="userForm.email"
                  type="email"
                  autocomplete="email"
                  class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
                  :class="visibleFieldError('email') ? 'border-rose-300 focus:ring-rose-100' : ''"
                  @input="sanitizeEmail"
                  @blur="markTouched('email')"
                />
                <p v-if="visibleFieldError('email')" class="mt-1 text-xs text-rose-600">{{ visibleFieldError("email") }}</p>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Teléfono:</label>
                  <input
                    v-model="userForm.telefono"
                    type="tel"
                    inputmode="numeric"
                    autocomplete="tel"
                    maxlength="15"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
                    :class="visibleFieldError('telefono') ? 'border-rose-300 focus:ring-rose-100' : ''"
                    @input="sanitizePhone"
                    @blur="markTouched('telefono')"
                  />
                  <p v-if="visibleFieldError('telefono')" class="mt-1 text-xs text-rose-600">{{ visibleFieldError("telefono") }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Rol</label>
                  <select v-model="userForm.rol" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200">
                    <option v-for="role in roleOptions" :key="role.id" :value="role.nombre">
                      {{ role.label }}
                    </option>
                  </select>
                  <p v-if="userForm.errors.rol" class="mt-1 text-xs text-rose-600">{{ userForm.errors.rol }}</p>
                </div>
              </div>

              <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                La cuenta se crea activa, sin contraseña definitiva y con enlace de activación por correo.
              </div>

              <div class="pt-2 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3">
                <button type="button" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-sm font-semibold sm:w-auto" @click="closeCreateUser">
                  Cancelar
                </button>
                <button type="submit" class="inline-flex w-full items-center justify-center px-4 py-2.5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-sm font-semibold disabled:opacity-60 sm:w-auto" :disabled="!canSubmitUser">
                  <PlusIcon class="w-5 h-5 mr-2" />
                  Crear usuario
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
