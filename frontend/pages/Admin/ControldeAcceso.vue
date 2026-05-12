<script setup>
import AdminLayout from "@/layouts/AdminLayout.vue";
import { computed, ref } from "vue";

// Heroicons
import {
  MagnifyingGlassIcon,
  UserPlusIcon,
  ShieldCheckIcon,
  KeyIcon,
  CheckCircleIcon,
  XCircleIcon,
  LockClosedIcon,
  LockOpenIcon,
  PencilSquareIcon,
  TrashIcon,
  XMarkIcon,
  PlusIcon,
  ChevronDownIcon,
  CheckIcon,
} from "@heroicons/vue/24/outline";

defineOptions({ layout: AdminLayout });

// ============================
//  TABS
// ============================
const activeTab = ref("admins"); // admins | roles | security | audit
const searchTerm = ref("");
const auditSearch = ref("");
const auditAction = ref("Todas"); // Todas | Login | Create | Update | Delete
const isAuditFilterOpen = ref(false);

const auditActionOptions = ["Todas", "Login", "Create", "Update", "Delete"];

const toggleAuditFilter = () => {
  isAuditFilterOpen.value = !isAuditFilterOpen.value;
};

const setAuditAction = (val) => {
  auditAction.value = val;
  isAuditFilterOpen.value = false;
};

const badgeAuditStatus = (status) => {
  return status === "Success"
    ? "bg-emerald-50 text-emerald-700 ring-emerald-200"
    : "bg-rose-50 text-rose-700 ring-rose-200";
};

const filteredAuditLogs = computed(() => {
  const term = auditSearch.value.trim().toLowerCase();
  const action = auditAction.value;

  return auditLogs.value.filter((l) => {
    const matchesTerm =
      !term ||
      l.timestamp.toLowerCase().includes(term) ||
      l.usuario.toLowerCase().includes(term) ||
      l.accion.toLowerCase().includes(term) ||
      l.recurso.toLowerCase().includes(term) ||
      l.ip.toLowerCase().includes(term) ||
      l.estado.toLowerCase().includes(term);

    const matchesAction = action === "Todas" ? true : l.accion === action;

    return matchesTerm && matchesAction;
  });
});


// ============================
//  MOCK DATA (luego conectas a backend)
// ============================

// Usuarios admin
const adminUsers = ref([
  {
    id: 1,
    initials: "AU",
    nombre: "Admin Principal",
    correo: "admin@clubrobotica.espoch.edu.ec",
    rol: "Super Admin",
    estado: "Activo",
    ultimoLogin: "2025-12-15 10:30",
    twofa: "Habilitado",
    loginCount: 245,
    locked: false,
  },
  {
    id: 2,
    initials: "OM",
    nombre: "Coordinación Eventos",
    correo: "eventos@clubrobotica.espoch.edu.ec",
    rol: "Manager",
    estado: "Activo",
    ultimoLogin: "2025-12-15 09:15",
    twofa: "Habilitado",
    loginCount: 178,
    locked: false,
  },
  {
    id: 3,
    initials: "CS",
    nombre: "Soporte",
    correo: "soporte@clubrobotica.espoch.edu.ec",
    rol: "Soporte",
    estado: "Activo",
    ultimoLogin: "2025-12-14 17:45",
    twofa: "Deshabilitado",
    loginCount: 89,
    locked: false,
  },
  {
    id: 4,
    initials: "ML",
    nombre: "Comunicación",
    correo: "comunicacion@clubrobotica.espoch.edu.ec",
    rol: "Admin",
    estado: "Activo",
    ultimoLogin: "2025-12-13 14:20",
    twofa: "Habilitado",
    loginCount: 156,
    locked: false,
  },
  {
    id: 5,
    initials: "DA",
    nombre: "Analista",
    correo: "analitica@clubrobotica.espoch.edu.ec",
    rol: "Staff",
    estado: "Inactivo",
    ultimoLogin: "2025-12-10 11:30",
    twofa: "Deshabilitado",
    loginCount: 67,
    locked: true,
  },
]);

// Roles & Permisos (mock conocerás tu RBAC real luego)
const roles = ref([
  {
    id: 1,
    nombre: "Super Admin",
    descripcion: "Acceso total al sistema y configuración.",
    permisos: ["Usuarios", "Roles", "Seguridad", "Auditoría", "Competencias", "Inscripciones"],
  },
  {
    id: 2,
    nombre: "Manager",
    descripcion: "Gestiona eventos, revisa reportes y supervisa flujo.",
    permisos: ["Competencias", "Inscripciones", "Reportes", "Notificaciones"],
  },
  {
    id: 3,
    nombre: "Admin",
    descripcion: "Administra módulos operativos según permisos asignados.",
    permisos: ["Competencias", "Inscripciones", "Categorías"],
  },
  {
    id: 4,
    nombre: "Soporte",
    descripcion: "Atiende incidencias y soporte operativo.",
    permisos: ["Incidencias", "Usuarios (lectura)", "Auditoría (lectura)"],
  },
  {
    id: 5,
    nombre: "Staff",
    descripcion: "Acceso limitado para tareas específicas.",
    permisos: ["Lectura básica"],
  },
]);

// ============================
//  PERMISOS DISPONIBLES (mock)
// ============================
const permissionsCatalog = ref([
  {
    group: "Usuarios",
    items: [
      { key: "user_management", name: "Gestión de usuarios", desc: "Crear, editar y eliminar usuarios" },
      { key: "user_view", name: "Ver usuarios", desc: "Visualizar perfiles y datos" },
    ],
  },
  {
    group: "Competencias",
    items: [
      { key: "competition_management", name: "Gestión de competencias", desc: "Crear y administrar competencias" },
      { key: "competition_view", name: "Ver competencias", desc: "Acceso de solo lectura" },
    ],
  },
  {
    group: "Inscripciones",
    items: [
      { key: "registration_management", name: "Gestión de inscripciones", desc: "Aprobar / rechazar y controlar pagos" },
      { key: "registration_view", name: "Ver inscripciones", desc: "Acceso de solo lectura" },
    ],
  },
  {
    group: "Jueces",
    items: [
      { key: "judge_assignment", name: "Asignación de jueces", desc: "Asignar y administrar jueces por categoría" },
      { key: "judge_view", name: "Ver jueces", desc: "Acceso de solo lectura" },
    ],
  },
  {
    group: "Seguridad",
    items: [
      { key: "security_settings", name: "Configurar seguridad", desc: "Políticas de sesión, 2FA y contraseñas" },
      { key: "audit_view", name: "Ver auditoría", desc: "Consultar registros de auditoría" },
    ],
  },
]);

// Convierte permisos de texto (mock antiguo) a keys (si no hay keys aún)
const normalizeRolePermissions = (role) => {
  // Si tu rol ya usa keys, déjalo como está.
  if (role.permissionKeys) return role.permissionKeys;

  // Mapeo simple de tus etiquetas actuales a keys
  const map = {
    Usuarios: "user_management",
    Roles: "security_settings",
    Seguridad: "security_settings",
    Auditoría: "audit_view",
    Competencias: "competition_management",
    Inscripciones: "registration_management",
    Categorías: "competition_management",
    Reportes: "audit_view",
    Notificaciones: "audit_view",
    Incidencias: "user_view",
    "Usuarios (lectura)": "user_view",
    "Auditoría (lectura)": "audit_view",
    "Lectura básica": "user_view",
  };

  const keys = (role.permisos ?? [])
    .map((p) => map[p] || null)
    .filter(Boolean);

  return Array.from(new Set(keys));
};

// ============================
//  MODAL ROLES (Crear / Editar)
// ============================
const isRoleModalOpen = ref(false);
const isRoleEditing = ref(false);

const roleForm = ref({
  id: null,
  nombre: "",
  descripcion: "",
  permissionKeys: [], // keys seleccionadas
});

// helpers
const openCreateRole = () => {
  isRoleEditing.value = false;
  roleForm.value = {
    id: null,
    nombre: "",
    descripcion: "",
    permissionKeys: [],
  };
  isRoleModalOpen.value = true;
};

const openEditRole = (role) => {
  isRoleEditing.value = true;

  roleForm.value = {
    id: role.id,
    nombre: role.nombre,
    descripcion: role.descripcion,
    permissionKeys: normalizeRolePermissions(role),
  };

  isRoleModalOpen.value = true;
};

const closeRoleModal = () => {
  isRoleModalOpen.value = false;
};

const togglePermission = (key) => {
  const idx = roleForm.value.permissionKeys.indexOf(key);
  if (idx === -1) roleForm.value.permissionKeys.push(key);
  else roleForm.value.permissionKeys.splice(idx, 1);
};

const isPermissionChecked = (key) => {
  return roleForm.value.permissionKeys.includes(key);
};

// Para mostrar resumen tipo: "user_view, competition_management +3 más"
const permissionSummary = (keys, limit = 3) => {
  if (!keys || keys.length === 0) return "Sin permisos asignados";

  const shown = keys.slice(0, limit);
  const more = keys.length - shown.length;
  return more > 0 ? `${shown.join(", ")} +${more} más` : shown.join(", ");
};

const countRoleUsersMock = (roleName) => {
  // Mock, como la imagen “2 users / 5 users”
  const map = {
    "Super Admin": 2,
    Admin: 5,
    Manager: 8,
    Soporte: 3,
    Staff: 4,
  };
  return map[roleName] ?? 0;
};

const saveRole = () => {
  const payload = { ...roleForm.value };

  if (!payload.nombre.trim()) return;

  if (isRoleEditing.value) {
    const idx = roles.value.findIndex((r) => r.id === payload.id);
    if (idx !== -1) {
      roles.value[idx] = {
        ...roles.value[idx],
        nombre: payload.nombre.trim(),
        descripcion: payload.descripcion.trim(),
        permissionKeys: [...payload.permissionKeys],
      };
    }
  } else {
    const newId = Math.max(...roles.value.map((r) => r.id)) + 1;
    roles.value.unshift({
      id: newId,
      nombre: payload.nombre.trim(),
      descripcion: payload.descripcion.trim(),
      permisos: [], // ya no lo usamos, pero lo dejamos para no romper
      permissionKeys: [...payload.permissionKeys],
    });
  }

  closeRoleModal();
};



// Configuración de seguridad (mock)
const security = ref({
  passwordPolicy: {
    minLength: 10,
    uppercase: true,
    lowercase: true,
    number: true,
    special: true,
    history: 5,
  },
  sessionPolicy: {
    idleTimeoutMin: 20,
    absoluteTimeoutMin: 240,
    rememberMeAllowed: false,
    singleSession: true,
  },
  twofa: {
    requiredForAdmins: true,
    methods: ["App Authenticator (TOTP)", "Correo (OTP)"],
  },
});


// ============================
//  SECURITY SETTINGS (UI tipo switches como la imagen)
// ============================
const securitySettings = ref({
  passwordPolicy: {
    minLengthEnabled: true,
    minLength: 8,
    requireSpecial: true,
    requireNumbers: true,
    requireUppercase: true,
  },
  sessionManagement: {
    sessionTimeoutHours: "24",
    maxConcurrentSessions: "3",
    forceLogoutOnPasswordChange: true,
  },
  twoFactor: {
    enforce2FAForAdmins: true,
    smsVerification: false,
    authenticatorApps: true,
  },
  loginSecurity: {
    maxFailedAttempts: "5",
    lockoutDurationMin: "30",
    ipRestrictions: false,
  },
});

const sessionTimeoutOptions = ["1", "2", "4", "8", "12", "24", "48"];
const concurrentOptions = ["1", "2", "3", "5", "10"];
const failedAttemptsOptions = ["3", "5", "10"];
const lockoutOptions = ["5", "10", "15", "30", "60"];




// Auditoría (mock)
const auditLogs = ref([
  {
    id: 1,
    timestamp: "2024-01-15 10:30:25",
    usuario: "Admin User",
    accion: "Create",
    recurso: "Usuario ID: U006",
    ip: "192.168.1.100",
    estado: "Success",
  },
  {
    id: 2,
    timestamp: "2024-01-15 09:45:12",
    usuario: "Operations Manager",
    accion: "Update",
    recurso: "Orden ID: EST001",
    ip: "192.168.1.105",
    estado: "Success",
  },
  {
    id: 3,
    timestamp: "2024-01-15 08:20:45",
    usuario: "Marketing Lead",
    accion: "Create",
    recurso: "Campaña ID: CAM004",
    ip: "192.168.1.110",
    estado: "Success",
  },
  {
    id: 4,
    timestamp: "2024-01-14 06:15:30",
    usuario: "Usuario desconocido",
    accion: "Login",
    recurso: "admin@estampindia.com",
    ip: "203.192.45.67",
    estado: "Failed",
  },
]);


// ============================
//  UI HELPERS (BADGES)
// ============================
const badgeEstado = (estado) => {
  switch (estado) {
    case "Activo":
      return "bg-emerald-50 text-emerald-700 ring-emerald-200";
    case "Inactivo":
      return "bg-slate-100 text-slate-700 ring-slate-200";
    default:
      return "bg-slate-50 text-slate-700 ring-slate-200";
  }
};

const badge2FA = (twofa) => {
  switch (twofa) {
    case "Habilitado":
      return "bg-emerald-50 text-emerald-700 ring-emerald-200";
    case "Deshabilitado":
      return "bg-amber-50 text-amber-800 ring-amber-200";
    default:
      return "bg-slate-50 text-slate-700 ring-slate-200";
  }
};

const badgeRol = (rol) => {
  switch (rol) {
    case "Super Admin":
      return "bg-violet-50 text-violet-700 ring-violet-200";
    case "Manager":
      return "bg-emerald-50 text-emerald-800 ring-emerald-200";
    case "Soporte":
      return "bg-orange-50 text-orange-800 ring-orange-200";
    case "Admin":
      return "bg-blue-50 text-blue-800 ring-blue-200";
    case "Staff":
      return "bg-yellow-50 text-yellow-900 ring-yellow-200";
    default:
      return "bg-slate-50 text-slate-700 ring-slate-200";
  }
};

const badgeSeveridad = (sev) => {
  switch (sev) {
    case "Alta":
      return "bg-rose-50 text-rose-700 ring-rose-200";
    case "Media":
      return "bg-amber-50 text-amber-700 ring-amber-200";
    case "Baja":
      return "bg-emerald-50 text-emerald-700 ring-emerald-200";
    default:
      return "bg-slate-50 text-slate-700 ring-slate-200";
  }
};

// ============================
//  COMPUTEDS
// ============================
const filteredAdminUsers = computed(() => {
  const term = searchTerm.value.trim().toLowerCase();
  if (!term) return adminUsers.value;

  return adminUsers.value.filter((u) => {
    return (
      u.nombre.toLowerCase().includes(term) ||
      u.correo.toLowerCase().includes(term) ||
      u.rol.toLowerCase().includes(term)
    );
  });
});

const stats = computed(() => {
  const total = adminUsers.value.length;
  const activos = adminUsers.value.filter((u) => u.estado === "Activo").length;
  const twofa = adminUsers.value.filter((u) => u.twofa === "Habilitado").length;

  const uniqueRoles = new Set(adminUsers.value.map((u) => u.rol)).size;

  return [
    { label: "Total usuarios admin", value: total, icon: UserPlusIcon },
    { label: "Usuarios activos", value: activos, icon: CheckCircleIcon },
    { label: "2FA habilitado", value: twofa, icon: ShieldCheckIcon },
    { label: "Roles definidos", value: uniqueRoles, icon: KeyIcon },
  ];
});

// ============================
//  MODAL: CREAR / EDITAR USUARIO
// ============================
const isUserModalOpen = ref(false);
const isEditing = ref(false);
const formUser = ref({
  id: null,
  initials: "NA",
  nombre: "",
  correo: "",
  rol: "Admin",
  estado: "Activo",
  twofa: "Habilitado",
  locked: false,
});

const openCreateUser = () => {
  isEditing.value = false;
  formUser.value = {
    id: null,
    initials: "NA",
    nombre: "",
    correo: "",
    rol: "Admin",
    estado: "Activo",
    twofa: "Habilitado",
    locked: false,
  };
  isUserModalOpen.value = true;
};

const openEditUser = (row) => {
  isEditing.value = true;
  formUser.value = { ...row };
  isUserModalOpen.value = true;
};

const closeUserModal = () => {
  isUserModalOpen.value = false;
};

const saveUser = () => {
  // NOTE: aquí luego conectas Inertia form + POST/PATCH
  const payload = { ...formUser.value };

  // genera iniciales si no están
  if (!payload.initials || payload.initials === "NA") {
    const parts = payload.nombre.trim().split(" ").filter(Boolean);
    payload.initials = (parts[0]?.[0] ?? "N") + (parts[1]?.[0] ?? "A");
    payload.initials = payload.initials.toUpperCase();
  }

  if (isEditing.value) {
    const idx = adminUsers.value.findIndex((x) => x.id === payload.id);
    if (idx !== -1) adminUsers.value[idx] = payload;
  } else {
    payload.id = Math.max(...adminUsers.value.map((x) => x.id)) + 1;
    payload.ultimoLogin = "—";
    payload.loginCount = 0;
    adminUsers.value.unshift(payload);
  }

  closeUserModal();
};

// ============================
//  ACCIONES UI (mock)
// ============================
const toggleLock = (row) => {
  row.locked = !row.locked;
  row.estado = row.locked ? "Inactivo" : "Activo";
};

const toggle2FA = (row) => {
  row.twofa = row.twofa === "Habilitado" ? "Deshabilitado" : "Habilitado";
};

const deleteUser = (row) => {
  // en tu sistema real, mejor confirm modal
  adminUsers.value = adminUsers.value.filter((x) => x.id !== row.id);
};
</script>

<template>
  <div class="lg:px-4 py-6">
    <!-- Header -->
    <div class="mb-7">
      <h1 class="text-2xl font-bold text-slate-900">Control de Acceso</h1>
      <p class="text-sm text-slate-500">
        Gestiona permisos, roles y seguridad del sistema
      </p>
    </div>

    <!-- Tabs (pill) -->
    <div class="mb-6">
      <div class="inline-flex rounded-full bg-slate-200/70 p-1">
        <button
          class="px-4 py-2 text-sm rounded-full transition"
          :class="
            activeTab === 'admins'
              ? 'bg-white shadow-sm text-slate-900 font-semibold'
              : 'text-slate-600 hover:text-slate-900'
          "
          @click="activeTab = 'admins'"
        >
          Usuarios Admin
        </button>

        <button
          class="px-4 py-2 text-sm rounded-full transition"
          :class="
            activeTab === 'roles'
              ? 'bg-white shadow-sm text-slate-900 font-semibold'
              : 'text-slate-600 hover:text-slate-900'
          "
          @click="activeTab = 'roles'"
        >
          Roles y Permisos
        </button>

        <button
          class="px-4 py-2 text-sm rounded-full transition"
          :class="
            activeTab === 'security'
              ? 'bg-white shadow-sm text-slate-900 font-semibold'
              : 'text-slate-600 hover:text-slate-900'
          "
          @click="activeTab = 'security'"
        >
          Configuración de Seguridad
        </button>

        <button
          class="px-4 py-2 text-sm rounded-full transition"
          :class="
            activeTab === 'audit'
              ? 'bg-white shadow-sm text-slate-900 font-semibold'
              : 'text-slate-600 hover:text-slate-900'
          "
          @click="activeTab = 'audit'"
        >
          Auditoría
        </button>
      </div>
    </div>

    <!-- =========================
         TAB: USUARIOS ADMIN
         ========================= -->
    <div v-if="activeTab === 'admins'" class="space-y-5">
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
          <h2 class="text-xl font-bold text-slate-900">Usuarios Administrativos</h2>
          <p class="text-sm text-slate-500">
            Administra cuentas administrativas y sus niveles de acceso
          </p>
        </div>

        <button
          class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
          @click="openCreateUser"
        >
          <UserPlusIcon class="w-5 h-5 mr-2" />
          Agregar usuario admin
        </button>
      </div>

      <!-- Stats cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div
          v-for="(s, idx) in stats"
          :key="idx"
          class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        >
          <div class="flex items-start justify-between">
            <p class="text-sm font-semibold text-slate-700">{{ s.label }}</p>
            <component :is="s.icon" class="w-5 h-5 text-slate-400" />
          </div>
          <div class="mt-4 text-3xl font-bold text-slate-900">{{ s.value }}</div>
          <p class="mt-1 text-xs text-slate-500">Resumen del módulo</p>
        </div>
      </div>

      <!-- Table Card -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-200">
          <h3 class="text-lg font-semibold text-slate-900">Usuarios Admin</h3>
          <p class="text-sm text-slate-500">Gestiona cuentas y permisos</p>

          <div class="mt-4 relative">
            <MagnifyingGlassIcon
              class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"
            />
            <input
              v-model="searchTerm"
              type="text"
              placeholder="Buscar usuarios…"
              class="w-full rounded-xl border border-slate-200 bg-white px-10 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
            />
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full">
            <thead class="bg-slate-50">
              <tr class="text-left text-xs font-semibold text-slate-600">
                <th class="px-5 py-4">Usuario</th>
                <th class="px-5 py-4">Rol</th>
                <th class="px-5 py-4">Estado</th>
                <th class="px-5 py-4">Último inicio</th>
                <th class="px-5 py-4">2FA</th>
                <th class="px-5 py-4">Ingresos</th>
                <th class="px-5 py-4">Acciones</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="row in filteredAdminUsers"
                :key="row.id"
                class="hover:bg-slate-50/60"
              >
                <!-- Usuario -->
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <div
                      class="h-10 w-10 rounded-full bg-slate-100 flex items-center justify-center text-sm font-bold text-slate-700"
                    >
                      {{ row.initials }}
                    </div>
                    <div>
                      <p class="text-sm font-semibold text-slate-900">{{ row.nombre }}</p>
                      <p class="text-xs text-slate-500">{{ row.correo }}</p>
                    </div>
                  </div>
                </td>

                <!-- Rol -->
                <td class="px-5 py-4">
                  <span
                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1"
                    :class="badgeRol(row.rol)"
                  >
                    {{ row.rol }}
                  </span>
                </td>

                <!-- Estado -->
                <td class="px-5 py-4">
                  <span
                    class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold ring-1"
                    :class="badgeEstado(row.estado)"
                  >
                    <CheckCircleIcon v-if="row.estado === 'Activo'" class="w-4 h-4" />
                    <XCircleIcon v-else class="w-4 h-4" />
                    {{ row.estado }}
                  </span>
                </td>

                <!-- Último login -->
                <td class="px-5 py-4 text-sm text-slate-700 whitespace-nowrap">
                  {{ row.ultimoLogin }}
                </td>

                <!-- 2FA -->
                <td class="px-5 py-4">
                  <button
                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 whitespace-nowrap hover:opacity-90"
                    :class="badge2FA(row.twofa)"
                    type="button"
                    @click="toggle2FA(row)"
                    :title="row.twofa === 'Habilitado' ? 'Deshabilitar 2FA' : 'Habilitar 2FA'"
                  >
                    <ShieldCheckIcon class="w-4 h-4 mr-1" />
                    {{ row.twofa }}
                  </button>
                </td>

                <!-- Login count -->
                <td class="px-5 py-4 text-sm text-slate-700">
                  {{ row.loginCount }}
                </td>

                <!-- Acciones -->
                <td class="px-5 py-4">
                  <div class="flex items-center gap-2">
                    <button
                      class="h-9 w-9 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 flex items-center justify-center"
                      type="button"
                      @click="openEditUser(row)"
                      title="Editar"
                    >
                      <PencilSquareIcon class="w-5 h-5 text-slate-700" />
                    </button>

                    <button
                      class="h-9 w-9 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 flex items-center justify-center"
                      type="button"
                      @click="toggleLock(row)"
                      :title="row.locked ? 'Desbloquear' : 'Bloquear'"
                    >
                      <LockOpenIcon v-if="row.locked" class="w-5 h-5 text-slate-700" />
                      <LockClosedIcon v-else class="w-5 h-5 text-slate-700" />
                    </button>

                    <button
                      class="h-9 w-9 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 flex items-center justify-center"
                      type="button"
                      @click="deleteUser(row)"
                      title="Eliminar"
                    >
                      <TrashIcon class="w-5 h-5 text-rose-600" />
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="filteredAdminUsers.length === 0">
                <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-500">
                  No hay resultados con el filtro actual.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- =========================
     TAB: ROLES Y PERMISOS
     ========================= -->
    <div v-else-if="activeTab === 'roles'" class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
        <h2 class="text-xl font-bold text-slate-900">Roles y Permisos</h2>
        <p class="text-sm text-slate-500">
            Configura roles del sistema y sus permisos
        </p>
        </div>

        <button
        class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
        type="button"
        @click="openCreateRole"
        >
        <PlusIcon class="w-5 h-5 mr-2" />
        Crear rol
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div
        v-for="r in roles"
        :key="r.id"
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
        >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
            <div class="flex items-center gap-2">
                <p class="text-base font-semibold text-slate-900 truncate">
                {{ r.nombre }}
                </p>

                <!-- “System” badge opcional como la imagen -->
                <span
                v-if="r.nombre === 'Super Admin'"
                class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200"
                >
                Sistema
                </span>
            </div>

            <p class="text-sm text-slate-500 mt-2 line-clamp-2">
                {{ r.descripcion }}
            </p>
            </div>

            <p class="text-sm text-slate-500 whitespace-nowrap">
            {{ countRoleUsersMock(r.nombre) }} usuarios
            </p>
        </div>

        <div class="mt-6">
            <p class="text-sm font-semibold text-slate-800">
            Permisos ({{ (r.permissionKeys ?? normalizeRolePermissions(r)).length }})
            </p>

            <p class="text-sm text-slate-500 mt-2">
            {{ permissionSummary(r.permissionKeys ?? normalizeRolePermissions(r), 3) }}
            </p>
        </div>

        <div class="mt-6 flex gap-3">
            <!-- SOLO EDIT -->
            <button
            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50"
            type="button"
            @click="openEditRole(r)"
            >
            <PencilSquareIcon class="w-5 h-5" />
            Editar
            </button>
        </div>
        </div>
    </div>
    </div>


  <!-- =========================
     TAB: CONFIGURACIÓN SEGURIDAD (NUEVO UI)
     ========================= -->
    <div v-else-if="activeTab === 'security'" class="space-y-6">
      <div>
        <h2 class="text-xl font-bold text-slate-900">Configuración de Seguridad</h2>
        <p class="text-sm text-slate-500">Configura políticas de seguridad del sistema</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Password Policy -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div class="mb-5">
            <p class="text-base font-semibold text-slate-900">Política de contraseñas</p>
            <p class="text-sm text-slate-500">Configura requisitos de contraseña</p>
          </div>

          <div class="space-y-5">
            <!-- min length -->
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-slate-800">
                  Longitud mínima ({{ securitySettings.passwordPolicy.minLength }} caracteres)
                </p>
                <p class="text-xs text-slate-500">Requiere contraseñas de al menos {{ securitySettings.passwordPolicy.minLength }} caracteres</p>
              </div>

              <label class="relative inline-flex items-center cursor-pointer">
                <input v-model="securitySettings.passwordPolicy.minLengthEnabled" type="checkbox" class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:bg-blue-600 transition"></div>
                <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition peer-checked:translate-x-5"></div>
              </label>
            </div>

            <div class="pl-0">
              <input
                type="range"
                min="6"
                max="16"
                step="1"
                v-model="securitySettings.passwordPolicy.minLength"
                :disabled="!securitySettings.passwordPolicy.minLengthEnabled"
                class="w-full accent-blue-600 disabled:opacity-40"
              />
              <div class="mt-1 flex justify-between text-xs text-slate-400">
                <span>6</span><span>16</span>
              </div>
            </div>

            <!-- special -->
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-slate-800">Requiere caracteres especiales</p>
                <p class="text-xs text-slate-500">Incluye símbolos como !@#$%</p>
              </div>

              <label class="relative inline-flex items-center cursor-pointer">
                <input v-model="securitySettings.passwordPolicy.requireSpecial" type="checkbox" class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:bg-blue-600 transition"></div>
                <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition peer-checked:translate-x-5"></div>
              </label>
            </div>

            <!-- numbers -->
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-slate-800">Requiere números</p>
                <p class="text-xs text-slate-500">Incluye al menos un número</p>
              </div>

              <label class="relative inline-flex items-center cursor-pointer">
                <input v-model="securitySettings.passwordPolicy.requireNumbers" type="checkbox" class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:bg-blue-600 transition"></div>
                <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition peer-checked:translate-x-5"></div>
              </label>
            </div>

            <!-- uppercase -->
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-slate-800">Requiere mayúsculas</p>
                <p class="text-xs text-slate-500">Incluye al menos una letra mayúscula</p>
              </div>

              <label class="relative inline-flex items-center cursor-pointer">
                <input v-model="securitySettings.passwordPolicy.requireUppercase" type="checkbox" class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:bg-blue-600 transition"></div>
                <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition peer-checked:translate-x-5"></div>
              </label>
            </div>
          </div>
        </div>

        <!-- Session Management -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div class="mb-5">
            <p class="text-base font-semibold text-slate-900">Gestión de sesiones</p>
            <p class="text-sm text-slate-500">Configura ajustes de sesiones de usuario</p>
          </div>

          <div class="space-y-5">
            <div>
              <p class="text-sm font-semibold text-slate-800 mb-2">Tiempo de sesión (horas)</p>
              <select
                v-model="securitySettings.sessionManagement.sessionTimeoutHours"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
              >
                <option v-for="o in sessionTimeoutOptions" :key="o" :value="o">{{ o }} horas</option>
              </select>
            </div>

            <div>
              <p class="text-sm font-semibold text-slate-800 mb-2">Máx. sesiones concurrentes</p>
              <select
                v-model="securitySettings.sessionManagement.maxConcurrentSessions"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
              >
                <option v-for="o in concurrentOptions" :key="o" :value="o">{{ o }} sesiones</option>
              </select>
            </div>

            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-slate-800">Forzar cierre de sesión al cambiar contraseña</p>
                <p class="text-xs text-slate-500">Finaliza todas las sesiones cuando la contraseña cambia</p>
              </div>

              <label class="relative inline-flex items-center cursor-pointer">
                <input v-model="securitySettings.sessionManagement.forceLogoutOnPasswordChange" type="checkbox" class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:bg-blue-600 transition"></div>
                <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition peer-checked:translate-x-5"></div>
              </label>
            </div>
          </div>
        </div>

        <!-- Two Factor -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div class="mb-5">
            <p class="text-base font-semibold text-slate-900">Autenticación de dos factores</p>
            <p class="text-sm text-slate-500">Configura requisitos de 2FA</p>
          </div>

          <div class="space-y-5">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-slate-800">Exigir 2FA para admins</p>
                <p class="text-xs text-slate-500">Requiere 2FA para todos los usuarios admin</p>
              </div>

              <label class="relative inline-flex items-center cursor-pointer">
                <input v-model="securitySettings.twoFactor.enforce2FAForAdmins" type="checkbox" class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:bg-blue-600 transition"></div>
                <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition peer-checked:translate-x-5"></div>
              </label>
            </div>

            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-slate-800">Verificación por SMS</p>
                <p class="text-xs text-slate-500">Permite SMS como método 2FA</p>
              </div>

              <label class="relative inline-flex items-center cursor-pointer">
                <input v-model="securitySettings.twoFactor.smsVerification" type="checkbox" class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:bg-blue-600 transition"></div>
                <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition peer-checked:translate-x-5"></div>
              </label>
            </div>

            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-slate-800">Apps Authenticator</p>
                <p class="text-xs text-slate-500">Permite apps TOTP (Google Authenticator, Authy)</p>
              </div>

              <label class="relative inline-flex items-center cursor-pointer">
                <input v-model="securitySettings.twoFactor.authenticatorApps" type="checkbox" class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:bg-blue-600 transition"></div>
                <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition peer-checked:translate-x-5"></div>
              </label>
            </div>
          </div>
        </div>

        <!-- Login Security -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div class="mb-5">
            <p class="text-base font-semibold text-slate-900">Seguridad de inicio de sesión</p>
            <p class="text-sm text-slate-500">Configura protección en el login</p>
          </div>

          <div class="space-y-5">
            <div>
              <p class="text-sm font-semibold text-slate-800 mb-2">Máx. intentos fallidos</p>
              <select
                v-model="securitySettings.loginSecurity.maxFailedAttempts"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
              >
                <option v-for="o in failedAttemptsOptions" :key="o" :value="o">{{ o }} intentos</option>
              </select>
            </div>

            <div>
              <p class="text-sm font-semibold text-slate-800 mb-2">Duración de bloqueo</p>
              <select
                v-model="securitySettings.loginSecurity.lockoutDurationMin"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
              >
                <option v-for="o in lockoutOptions" :key="o" :value="o">{{ o }} minutos</option>
              </select>
            </div>

            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-slate-800">Restricciones por IP</p>
                <p class="text-xs text-slate-500">Restringe acceso por dirección IP</p>
              </div>

              <label class="relative inline-flex items-center cursor-pointer">
                <input v-model="securitySettings.loginSecurity.ipRestrictions" type="checkbox" class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:bg-blue-600 transition"></div>
                <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition peer-checked:translate-x-5"></div>
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- Nota -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-start gap-3">
          <ShieldCheckIcon class="w-5 h-5 text-slate-400 mt-0.5" />
          <div>
            <p class="text-sm font-semibold text-slate-900">Nota</p>
            <p class="text-sm text-slate-600 mt-1">
              Estas opciones representan políticas de seguridad (contraseña, sesiones, 2FA y login).
              En el backend se aplican con validaciones, expiración de sesiones/tokens, y controles de bloqueo.
            </p>
          </div>
        </div>
      </div>
    </div>


    <!-- =========================
     TAB: AUDITORÍA
     ========================= -->
    <div v-else-if="activeTab === 'audit'" class="space-y-5">
      <div>
        <h2 class="text-xl font-bold text-slate-900">Auditoría</h2>
        <p class="text-sm text-slate-500">
          Monitorea actividades del sistema y acciones de usuarios
        </p>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-visible">
        <div class="p-5 border-b border-slate-200">
          <h3 class="text-lg font-semibold text-slate-900">Actividad del sistema</h3>
          <p class="text-sm text-slate-500">Actividades recientes del sistema y de usuarios</p>

          <!-- Search + Filter row -->
          <div class="mt-4 flex flex-col lg:flex-row lg:items-center gap-3">
            <!-- Search -->
            <div class="relative flex-1">
              <MagnifyingGlassIcon
                class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"
              />
              <input
                v-model="auditSearch"
                type="text"
                placeholder="Buscar logs de auditoría…"
                class="w-full rounded-xl border border-slate-200 bg-white px-10 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
              />
            </div>

            <!-- Filter dropdown -->
            <div class="relative w-full lg:w-52">
              <button
                type="button"
                @click="toggleAuditFilter"
                class="w-full inline-flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
              >
                <span>{{ auditAction === "Todas" ? "Todas las acciones" : auditAction }}</span>
                <ChevronDownIcon class="w-4 h-4 text-slate-500" />
              </button>

              <div
                v-if="isAuditFilterOpen"
                class="absolute right-0 mt-2 w-full rounded-xl border border-slate-200 bg-white shadow-lg p-1 z-20"
              >
                <button
                  v-for="opt in auditActionOptions"
                  :key="opt"
                  type="button"
                  @click="setAuditAction(opt)"
                  class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm hover:bg-slate-50"
                  :class="opt === auditAction ? 'bg-slate-50 font-semibold text-slate-900' : 'text-slate-700'"
                >
                  <span>{{ opt === "Todas" ? "Todas las acciones" : opt }}</span>
                  <CheckIcon v-if="opt === auditAction" class="w-4 h-4 text-slate-700" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full">
            <thead class="bg-slate-50">
              <tr class="text-left text-xs font-semibold text-slate-600">
                <th class="px-5 py-4">Timestamp</th>
                <th class="px-5 py-4">Usuario</th>
                <th class="px-5 py-4">Acción</th>
                <th class="px-5 py-4">Recurso</th>
                <th class="px-5 py-4">Dirección IP</th>
                <th class="px-5 py-4">Estado</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="log in filteredAuditLogs"
                :key="log.id"
                class="hover:bg-slate-50/60"
              >
                <td class="px-5 py-4 text-sm text-slate-700 whitespace-nowrap">
                  {{ log.timestamp }}
                </td>

                <td class="px-5 py-4 text-sm text-slate-900 font-medium whitespace-nowrap">
                  {{ log.usuario }}
                </td>

                <td class="px-5 py-4 text-sm text-slate-700 whitespace-nowrap">
                  {{ log.accion }}
                </td>

                <td class="px-5 py-4 text-sm text-slate-700">
                  {{ log.recurso }}
                </td>

                <td class="px-5 py-4 text-sm text-slate-700 whitespace-nowrap">
                  {{ log.ip }}
                </td>

                <td class="px-5 py-4">
                  <span
                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1"
                    :class="badgeAuditStatus(log.estado)"
                  >
                    {{ log.estado === "Success" ? "Éxito" : "Fallido" }}
                  </span>
                </td>
              </tr>

              <tr v-if="filteredAuditLogs.length === 0">
                <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500">
                  No hay resultados con el filtro actual.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>


    <!-- =========================
         MODAL: CREAR / EDITAR USUARIO
         ========================= -->
    <Teleport to="body">
      <div v-if="isUserModalOpen" class="fixed inset-0 z-[9999]">
        <!-- overlay -->
        <div class="absolute inset-0 bg-black/40" @click="closeUserModal"></div>

        <!-- centered -->
        <div class="relative h-full w-full grid place-items-center p-4 sm:p-6">
          <div
            class="w-full max-w-lg rounded-2xl bg-white border border-slate-200 shadow-xl overflow-hidden"
            role="dialog"
            aria-modal="true"
          >
            <!-- header -->
            <div class="p-5 border-b border-slate-200 flex items-start justify-between gap-4">
              <div>
                <h2 class="text-lg font-semibold text-slate-900">
                  {{ isEditing ? "Editar usuario admin" : "Agregar usuario admin" }}
                </h2>
                <p class="text-sm text-slate-500 mt-1">
                  Completa los datos para gestionar el acceso al sistema
                </p>
              </div>

              <button
                class="h-9 w-9 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-center justify-center shrink-0"
                @click="closeUserModal"
                type="button"
              >
                <XMarkIcon class="w-5 h-5 text-slate-600" />
              </button>
            </div>

            <!-- body -->
            <div class="p-5 space-y-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nombre</label>
                <input
                  v-model="formUser.nombre"
                  type="text"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
                  placeholder="Ej: Coordinación Eventos"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Correo</label>
                <input
                  v-model="formUser.correo"
                  type="email"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
                  placeholder="correo@espoch.edu.ec"
                />
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Rol</label>
                  <select
                    v-model="formUser.rol"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
                  >
                    <option>Super Admin</option>
                    <option>Manager</option>
                    <option>Admin</option>
                    <option>Soporte</option>
                    <option>Staff</option>
                  </select>
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Estado</label>
                  <select
                    v-model="formUser.estado"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
                  >
                    <option>Activo</option>
                    <option>Inactivo</option>
                  </select>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">2FA</label>
                  <select
                    v-model="formUser.twofa"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
                  >
                    <option>Habilitado</option>
                    <option>Deshabilitado</option>
                  </select>
                </div>

                <div class="flex items-center gap-3 pt-6">
                  <input
                    id="locked"
                    type="checkbox"
                    v-model="formUser.locked"
                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-200"
                  />
                  <label for="locked" class="text-sm text-slate-700">
                    Usuario bloqueado
                  </label>
                </div>
              </div>

              <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                Consejo: para el informe puedes describir que el módulo implementa
                <span class="font-semibold">RBAC</span> + <span class="font-semibold">2FA</span> +
                <span class="font-semibold">gestión de sesiones</span> + <span class="font-semibold">auditoría</span>.
              </div>
            </div>

            <!-- footer -->
            <div class="p-5 border-t border-slate-200 flex justify-end gap-3">
              <button
                class="px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 transition"
                type="button"
                @click="closeUserModal"
              >
                Cancelar
              </button>

              <button
                class="px-4 py-2.5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition font-semibold"
                type="button"
                @click="saveUser"
              >
                <span class="inline-flex items-center">
                  <CheckCircleIcon class="w-5 h-5 mr-2" />
                  Guardar
                </span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>

    <!-- =========================
        MODAL: CREAR / EDITAR ROL
        ========================= -->
    <Teleport to="body">
    <div v-if="isRoleModalOpen" class="fixed inset-0 z-[9999]">
        <!-- overlay -->
        <div class="absolute inset-0 bg-black/40" @click="closeRoleModal"></div>

        <!-- centered -->
        <div class="relative h-full w-full grid place-items-center p-4 sm:p-6">
        <div
            class="w-full max-w-2xl rounded-2xl bg-white border border-slate-200 shadow-xl overflow-hidden flex flex-col max-h-[calc(100vh-3rem)]"
            role="dialog"
            aria-modal="true"
        >

            <!-- header -->
            <div class="p-5 border-b border-slate-200 flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">
                {{ isRoleEditing ? `Editar rol: ${roleForm.nombre || '—'}` : 'Crear nuevo rol' }}
                </h2>
                <p class="text-sm text-slate-500 mt-1">
                {{ isRoleEditing ? 'Modifica permisos y configuración del rol' : 'Define un rol con permisos específicos' }}
                </p>
            </div>

            <button
                class="h-9 w-9 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-center justify-center shrink-0"
                @click="closeRoleModal"
                type="button"
            >
                <XMarkIcon class="w-5 h-5 text-slate-600" />
            </button>
            </div>

            <!-- body -->
            <div class="p-5 space-y-5 overflow-y-auto">

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nombre del rol</label>
                <input
                v-model="roleForm.nombre"
                type="text"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
                placeholder="Ej: Coordinador, Juez Principal, Soporte..."
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Descripción</label>
                <textarea
                v-model="roleForm.descripcion"
                rows="3"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
                placeholder="Describe el alcance del rol..."
                ></textarea>
            </div>

            <div>
                <p class="text-sm font-semibold text-slate-900 mb-2">Permisos</p>

                <div class="pr-1">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                    v-for="group in permissionsCatalog"
                    :key="group.group"
                    class="rounded-2xl border border-slate-200 bg-white p-4"
                    >
                    <p class="text-sm font-semibold text-slate-900 mb-3">
                        {{ group.group }}
                    </p>

                    <div class="space-y-3">
                        <label
                        v-for="perm in group.items"
                        :key="perm.key"
                        class="flex items-start gap-3 cursor-pointer"
                        >
                        <input
                            type="checkbox"
                            class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-200"
                            :checked="isPermissionChecked(perm.key)"
                            @change="togglePermission(perm.key)"
                        />
                        <div>
                            <p class="text-sm font-semibold text-slate-800">
                            {{ perm.name }}
                            </p>
                            <p class="text-xs text-slate-500">
                            {{ perm.desc }}
                            </p>
                        </div>
                        </label>
                    </div>
                    </div>
                </div>
                </div>

                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                Seleccionados: <span class="font-semibold">{{ roleForm.permissionKeys.length }}</span>
                </div>
            </div>
            </div>

            <!-- footer -->
            <div class="p-5 border-t border-slate-200 flex justify-end gap-3">
            <button
                class="px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 transition"
                type="button"
                @click="closeRoleModal"
            >
                Cancelar
            </button>

            <button
                class="px-4 py-2.5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition font-semibold"
                type="button"
                @click="saveRole"
            >
                {{ isRoleEditing ? 'Guardar cambios' : 'Crear rol' }}
            </button>
            </div>
        </div>
        </div>
    </div>
    </Teleport>


</template>
