<script setup>
import AdminLayout from "@/layouts/AdminLayout.vue";
import { ref, computed, watch } from "vue";

import {
  PlusIcon,
  XMarkIcon,
  PaperAirplaneIcon,
  ClockIcon,
  UsersIcon,
  BellAlertIcon,
  TrophyIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  CalendarDaysIcon,
  PencilSquareIcon,
  TrashIcon,
  DocumentTextIcon,
} from "@heroicons/vue/24/outline";

// =====================================================
// UI STATE
// =====================================================
const activeTab = ref("enviadas"); // enviadas | programadas | plantillas
const isModalOpen = ref(false);

// form (solo frontend)
const form = ref({
  tipo: "",
  destinatarios: "",
  titulo: "",
  mensaje: "",
  programar: false,
  fecha: "",
  hora: "",
});

// si tipo !== "otro", autogenera observación (si luego lo quieres para backend)
const tipoOptions = [
  { value: "resultados", label: "Resultados" },
  { value: "recordatorio", label: "Recordatorio" },
  { value: "actualizacion", label: "Actualización" },
  { value: "alerta", label: "Alerta" },
  { value: "info", label: "Información" },
];

const destinatariosOptions = [
  { value: "todos", label: "Todos los participantes" },
  { value: "competencia", label: "Por competencia" },
  { value: "categoria", label: "Por categoría" },
  { value: "institucion", label: "Por institución" },
  { value: "equipo", label: "Equipo específico" },
];

// =====================================================
// MOCK DATA (solo frontend)
// =====================================================
const notificationsSent = ref([
  {
    id: 1,
    type: "resultados",
    title: "Resultados Publicados - Competencia Regional 2025",
    message:
      "Los resultados de la categoría Seguidor de Línea han sido publicados. ¡Felicitaciones a ESPOCH Team A por el primer lugar!",
    timestamp: "2025-10-26 14:30",
    recipients: "Todos los participantes",
    status: "Enviado",
  },
  {
    id: 2,
    type: "recordatorio",
    title: "Recordatorio: Cierre de Inscripciones",
    message:
      "Las inscripciones para el Torneo Interuniversitario cierran en 3 días. Completa tu registro antes del 29 de octubre.",
    timestamp: "2025-10-26 10:00",
    recipients: "Equipos no inscritos",
    status: "Enviado",
  },
  {
    id: 3,
    type: "actualizacion",
    title: "Actualización de Horarios",
    message:
      "Se ha actualizado el horario de la categoría Sumo. Nueva hora: 15:00. Revisa el cronograma actualizado.",
    timestamp: "2025-10-25 18:45",
    recipients: "Categoría Sumo",
    status: "Enviado",
  },
  {
    id: 4,
    type: "success",
    title: "Inscripción Aprobada",
    message:
      "Tu inscripción para la Copa Nacional Robótica ha sido aprobada. Recuerda completar el pago antes del evento.",
    timestamp: "2025-10-25 12:20",
    recipients: "Equipo ESPOCH Team B",
    status: "Enviado",
  },
  {
    id: 5,
    type: "alerta",
    title: "Pago Pendiente",
    message:
      "Tu comprobante de pago está pendiente de validación. Por favor contacta con administración si tienes dudas.",
    timestamp: "2025-10-24 16:15",
    recipients: "EPN Robotics",
    status: "Enviado",
  },
]);

const notificationsScheduled = ref([
  {
    id: 1,
    title: "Inicio de Competencia - Copa Nacional",
    message: "La Copa Nacional Robótica inicia mañana a las 9:00 AM. ¡Los esperamos!",
    scheduledFor: "2025-12-19 08:00",
    recipients: "Todos los inscritos",
    status: "Programado",
  },
  {
    id: 2,
    title: "Recordatorio de Inspección Técnica",
    message: "Todos los robots deben pasar inspección técnica 1 hora antes de su categoría.",
    scheduledFor: "2025-11-14 08:00",
    recipients: "Competencia Regional",
    status: "Programado",
  },
]);

const templates = ref([
  {
    id: 1,
    name: "Bienvenida a Participantes",
    subject: "Bienvenido a la Competencia",
    message:
      "¡Hola [NOMBRE_EQUIPO]! Estamos emocionados de tenerte en [NOMBRE_COMPETENCIA]. Recuerda llegar 30 minutos antes de tu horario.",
  },
  {
    id: 2,
    name: "Publicación de Resultados",
    subject: "Resultados de [CATEGORIA]",
    message:
      "Los resultados de la categoría [CATEGORIA] ya están disponibles. Puedes consultarlos en la plataforma.",
  },
  {
    id: 3,
    name: "Recordatorio de Pago",
    subject: "Recordatorio de Pago Pendiente",
    message:
      "Hola [NOMBRE_EQUIPO], tu pago para [NOMBRE_COMPETENCIA] está pendiente. Por favor complétalo antes de [FECHA].",
  },
]);

// =====================================================
// STATS
// =====================================================
const stats = computed(() => {
  const sent = notificationsSent.value.length;
  const scheduled = notificationsScheduled.value.length;

  // mock: sumar “destinatarios totales” y “plantillas creadas”
  // (luego lo reemplazas por props del backend)
  const totalRecipients = 245;
  const templatesCount = 12;

  return {
    sent,
    scheduled,
    totalRecipients,
    templatesCount,
  };
});

// =====================================================
// HELPERS (iconos + colores por tipo)
// =====================================================
function typeMeta(type) {
  switch (type) {
    case "resultados":
      return {
        icon: TrophyIcon,
        iconColor: "text-yellow-600",
        bgColor: "bg-yellow-50",
      };
    case "recordatorio":
      return {
        icon: ClockIcon,
        iconColor: "text-blue-600",
        bgColor: "bg-blue-50",
      };
    case "actualizacion":
      return {
        icon: InformationCircleIcon,
        iconColor: "text-purple-600",
        bgColor: "bg-purple-50",
      };
    case "success":
      return {
        icon: CheckCircleIcon,
        iconColor: "text-emerald-600",
        bgColor: "bg-emerald-50",
      };
    case "alerta":
      return {
        icon: ExclamationTriangleIcon,
        iconColor: "text-orange-600",
        bgColor: "bg-orange-50",
      };
    case "info":
    default:
      return {
        icon: BellAlertIcon,
        iconColor: "text-slate-700",
        bgColor: "bg-slate-100",
      };
  }
}

function statusBadgeClass(status) {
  // En tu imagen es siempre “Enviado” en verde suave
  if (status === "Enviado") return "bg-emerald-50 text-emerald-700 ring-emerald-200";
  if (status === "Programado") return "bg-purple-50 text-purple-700 ring-purple-200";
  return "bg-slate-100 text-slate-700 ring-slate-200";
}

// =====================================================
// MODAL ACTIONS (solo UI)
// =====================================================
function openCreate() {
  isModalOpen.value = true;
  resetForm();
}

function closeModal() {
  isModalOpen.value = false;
  resetForm();
}

function resetForm() {
  form.value = {
    tipo: "",
    destinatarios: "",
    titulo: "",
    mensaje: "",
    programar: false,
    fecha: "",
    hora: "",
  };
}

function submitMock() {
  // Solo UI: simulación de “enviar”
  // (Luego reemplazamos por Inertia form + post al backend)
  const now = new Date();
  const pad = (n) => String(n).padStart(2, "0");
  const stamp = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(
    now.getHours()
  )}:${pad(now.getMinutes())}`;

  if (!form.value.titulo?.trim() || !form.value.mensaje?.trim()) {
    alert("Completa al menos Título y Mensaje.");
    return;
  }

  if (form.value.programar && (!form.value.fecha || !form.value.hora)) {
    alert("Si vas a programar, selecciona fecha y hora.");
    return;
  }

  if (form.value.programar) {
    notificationsScheduled.value.unshift({
      id: Date.now(),
      title: form.value.titulo,
      message: form.value.mensaje,
      scheduledFor: `${form.value.fecha} ${form.value.hora}`,
      recipients: labelFrom(destinatariosOptions, form.value.destinatarios) || "—",
      status: "Programado",
    });
    activeTab.value = "programadas";
  } else {
    notificationsSent.value.unshift({
      id: Date.now(),
      type: form.value.tipo || "info",
      title: form.value.titulo,
      message: form.value.mensaje,
      timestamp: stamp,
      recipients: labelFrom(destinatariosOptions, form.value.destinatarios) || "—",
      status: "Enviado",
    });
    activeTab.value = "enviadas";
  }

  closeModal();
}

function labelFrom(list, value) {
  const item = list.find((x) => x.value === value);
  return item?.label ?? "";
}

// acciones mock en programadas/plantillas
function editScheduledMock() {
  alert("Frontend listo. Luego conectamos esta acción al backend.");
}
function cancelScheduledMock() {
  alert("Frontend listo. Luego conectamos esta acción al backend.");
}
function useTemplateMock(tpl) {
  // copia plantilla al form
  isModalOpen.value = true;
  form.value.titulo = tpl.subject;
  form.value.mensaje = tpl.message;
  form.value.tipo = "info";
  form.value.destinatarios = "todos";
}
function editTemplateMock() {
  alert("Frontend listo. Luego conectamos edición al backend.");
}
function deleteTemplateMock() {
  alert("Frontend listo. Luego conectamos eliminación al backend.");
}

defineOptions({ layout: AdminLayout });
</script>

<template>
  <div class="w-full">
    <div class="mx-auto w-full max-w-[1180px] px-4 sm:px-6 lg:px-4 py-6 space-y-6">
      <!-- HEADER -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">Sistema de Notificaciones</h1>
          <p class="text-sm text-slate-500">Envía notificaciones automáticas a participantes y jueces</p>
        </div>

        <button
          @click="openCreate"
          class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition w-full sm:w-auto"
        >
          <PlusIcon class="w-5 h-5" />
          Nueva Notificación
        </button>
      </div>

      <!-- STATS (4 cards) -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Enviadas -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-start justify-between gap-4">
            <div class="h-10 w-10 rounded-xl bg-blue-600/10 flex items-center justify-center">
              <PaperAirplaneIcon class="w-5 h-5 text-blue-600" />
            </div>
          </div>
          <p class="text-3xl font-semibold text-slate-900 mt-4">{{ stats.sent }}</p>
          <p class="text-sm text-slate-500 mt-1">Notificaciones Enviadas</p>
        </div>

        <!-- Programadas -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-start justify-between gap-4">
            <div class="h-10 w-10 rounded-xl bg-purple-600/10 flex items-center justify-center">
              <ClockIcon class="w-5 h-5 text-purple-600" />
            </div>
          </div>
          <p class="text-3xl font-semibold text-slate-900 mt-4">{{ stats.scheduled }}</p>
          <p class="text-sm text-slate-500 mt-1">Notificaciones Programadas</p>
        </div>

        <!-- Destinatarios -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-start justify-between gap-4">
            <div class="h-10 w-10 rounded-xl bg-emerald-600/10 flex items-center justify-center">
              <UsersIcon class="w-5 h-5 text-emerald-600" />
            </div>
          </div>
          <p class="text-3xl font-semibold text-slate-900 mt-4">{{ stats.totalRecipients }}</p>
          <p class="text-sm text-slate-500 mt-1">Destinatarios Totales</p>
        </div>

        <!-- Plantillas -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-start justify-between gap-4">
            <div class="h-10 w-10 rounded-xl bg-orange-600/10 flex items-center justify-center">
              <BellAlertIcon class="w-5 h-5 text-orange-600" />
            </div>
          </div>
          <p class="text-3xl font-semibold text-slate-900 mt-4">{{ stats.templatesCount }}</p>
          <p class="text-sm text-slate-500 mt-1">Plantillas Creadas</p>
        </div>
      </div>
 
      <!-- TABS (pill) -->
      <div class="inline-flex items-center rounded-2xl bg-gray-200 p-1">
        <button
          class="px-4 py-2 rounded-2xl text-sm font-medium transition"
          :class="activeTab === 'enviadas' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'enviadas'"
        >
          Enviadas
        </button>
        <button
          class="px-4 py-2 rounded-2xl text-sm font-medium transition"
          :class="activeTab === 'programadas' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'programadas'"
        >
          Programadas
        </button>
        <button
          class="px-4 py-2 rounded-2xl text-sm font-medium transition"
          :class="activeTab === 'plantillas' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'plantillas'"
        >
          Plantillas
        </button>
      </div>

      <!-- CONTENT -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <!-- ENVIADAS -->
        <div v-if="activeTab === 'enviadas'" class="p-4 sm:p-6 space-y-4">
          <div
            v-for="n in notificationsSent"
            :key="n.id"
            class="rounded-2xl border border-slate-200 bg-white p-5 flex gap-4"
          >
            <div class="h-12 w-12 rounded-xl flex items-center justify-center shrink-0"
                 :class="typeMeta(n.type).bgColor">
              <component :is="typeMeta(n.type).icon" class="w-6 h-6" :class="typeMeta(n.type).iconColor" />
            </div>

            <div class="min-w-0 flex-1">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="font-semibold text-slate-900 leading-tight truncate">
                    {{ n.title }}
                  </p>
                  <p class="text-sm text-slate-600 mt-1">
                    {{ n.message }}
                  </p>
                </div>

                <span
                  class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ring-1 shrink-0"
                  :class="statusBadgeClass(n.status)"
                >
                  {{ n.status }}
                </span>
              </div>

              <div class="flex flex-wrap gap-4 text-xs text-slate-500 mt-3">
                <div class="inline-flex items-center gap-1">
                  <UsersIcon class="w-4 h-4" />
                  <span>{{ n.recipients }}</span>
                </div>
                <div class="inline-flex items-center gap-1">
                  <ClockIcon class="w-4 h-4" />
                  <span>{{ n.timestamp }}</span>
                </div>
              </div>
            </div>
          </div>

          <div v-if="notificationsSent.length === 0" class="py-12 text-center text-slate-500">
            No hay notificaciones enviadas.
          </div>
        </div>

        <!-- PROGRAMADAS -->
        <div v-else-if="activeTab === 'programadas'" class="p-4 sm:p-6 space-y-4">
          <div
            v-for="n in notificationsScheduled"
            :key="n.id"
            class="rounded-2xl border border-slate-200 bg-white p-5"
          >
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <p class="font-semibold text-slate-900 truncate">{{ n.title }}</p>
                  <span
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ring-1"
                    :class="statusBadgeClass(n.status)"
                  >
                    {{ n.status }}
                  </span>
                </div>

                <p class="text-sm text-slate-600 mt-2">
                  {{ n.message }}
                </p>

                <div class="flex flex-wrap gap-4 text-xs text-slate-500 mt-3">
                  <div class="inline-flex items-center gap-1">
                    <CalendarDaysIcon class="w-4 h-4" />
                    <span>Programado para: {{ n.scheduledFor }}</span>
                  </div>
                  <div class="inline-flex items-center gap-1">
                    <UsersIcon class="w-4 h-4" />
                    <span>{{ n.recipients }}</span>
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-2 shrink-0">
                <button
                  class="px-3 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 transition text-xs inline-flex items-center gap-1"
                  @click="editScheduledMock"
                  type="button"
                >
                  <PencilSquareIcon class="w-4 h-4 text-slate-700" />
                  Editar
                </button>
                <button
                  class="px-3 py-2 rounded-xl border border-slate-200 bg-white hover:bg-red-50 transition text-xs inline-flex items-center gap-1 text-red-600"
                  @click="cancelScheduledMock"
                  type="button"
                >
                  <TrashIcon class="w-4 h-4" />
                  Cancelar
                </button>
              </div>
            </div>
          </div>

          <div v-if="notificationsScheduled.length === 0" class="py-12 text-center text-slate-500">
            No hay notificaciones programadas.
          </div>
        </div>

        <!-- PLANTILLAS -->
        <div v-else class="p-4 sm:p-6 space-y-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div
              v-for="t in templates"
              :key="t.id"
              class="rounded-2xl border border-slate-200 bg-white p-5"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <p class="font-semibold text-slate-900">{{ t.name }}</p>
                  <p class="text-sm text-slate-600 mt-1">Asunto: {{ t.subject }}</p>
                </div>

                <button
                  class="px-3 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 transition text-xs"
                  type="button"
                  @click="useTemplateMock(t)"
                >
                  Usar Plantilla
                </button>
              </div>

              <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm text-slate-700">
                  {{ t.message }}
                </p>
              </div>

              <div class="flex items-center gap-2 mt-4">
                <button
                  class="px-3 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 transition text-xs inline-flex items-center gap-1"
                  type="button"
                  @click="editTemplateMock"
                >
                  <PencilSquareIcon class="w-4 h-4 text-slate-700" />
                  Editar
                </button>
                <button
                  class="px-3 py-2 rounded-xl border border-slate-200 bg-white hover:bg-red-50 transition text-xs inline-flex items-center gap-1 text-red-600"
                  type="button"
                  @click="deleteTemplateMock"
                >
                  <TrashIcon class="w-4 h-4" />
                  Eliminar
                </button>
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5">
            <p class="font-semibold text-blue-900 mb-3">Variables Disponibles</p>
            <div class="flex flex-wrap gap-2">
              <span class="px-3 py-1.5 rounded-full text-xs bg-white ring-1 ring-slate-200 text-slate-700">[NOMBRE_EQUIPO]</span>
              <span class="px-3 py-1.5 rounded-full text-xs bg-white ring-1 ring-slate-200 text-slate-700">[NOMBRE_COMPETENCIA]</span>
              <span class="px-3 py-1.5 rounded-full text-xs bg-white ring-1 ring-slate-200 text-slate-700">[CATEGORIA]</span>
              <span class="px-3 py-1.5 rounded-full text-xs bg-white ring-1 ring-slate-200 text-slate-700">[FECHA]</span>
              <span class="px-3 py-1.5 rounded-full text-xs bg-white ring-1 ring-slate-200 text-slate-700">[HORA]</span>
              <span class="px-3 py-1.5 rounded-full text-xs bg-white ring-1 ring-slate-200 text-slate-700">[UBICACION]</span>
              <span class="px-3 py-1.5 rounded-full text-xs bg-white ring-1 ring-slate-200 text-slate-700">[RESULTADO]</span>
              <span class="px-3 py-1.5 rounded-full text-xs bg-white ring-1 ring-slate-200 text-slate-700">[POSICION]</span>
            </div>
          </div>
        </div>
      </div>

      <!-- MODAL NUEVA NOTIFICACIÓN -->
      <Teleport to="body">
        <div v-if="isModalOpen" class="fixed inset-0 z-[9999]">
          <!-- overlay -->
          <div class="absolute inset-0 bg-black/40" @click="closeModal"></div>

          <!-- centered container -->
          <div class="relative h-full w-full grid place-items-center p-4 sm:p-6">
            <div
              class="w-full max-w-2xl rounded-2xl bg-white border border-slate-200 shadow-xl overflow-hidden
                    max-h-[90vh] flex flex-col"
              role="dialog"
              aria-modal="true"
            >
              <!-- header -->
              <div class="p-5 border-b border-slate-200 flex items-start justify-between gap-4">
                <div>
                  <h2 class="text-lg font-semibold text-slate-900">Crear Nueva Notificación</h2>
                  <p class="text-sm text-slate-500 mt-1">Define el tipo, destinatarios y contenido</p>
                </div>

                <button
                  class="h-9 w-9 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-center justify-center shrink-0"
                  @click="closeModal"
                  type="button"
                >
                  <XMarkIcon class="w-5 h-5 text-slate-600" />
                </button>
              </div>

              <!-- body -->
              <div class="p-5 space-y-4 overflow-y-auto">
                <!-- tipo -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Tipo de Notificación</label>
                  <select
                    v-model="form.tipo"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="" disabled>Seleccionar tipo</option>
                    <option v-for="t in tipoOptions" :key="t.value" :value="t.value">
                      {{ t.label }}
                    </option>
                  </select>
                </div>

                <!-- destinatarios -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Destinatarios</label>
                  <select
                    v-model="form.destinatarios"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="" disabled>Seleccionar destinatarios</option>
                    <option v-for="d in destinatariosOptions" :key="d.value" :value="d.value">
                      {{ d.label }}
                    </option>
                  </select>
                </div>

                <!-- título -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Título</label>
                  <input
                    v-model="form.titulo"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Ej: Resultados Publicados"
                  />
                </div>

                <!-- mensaje -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Mensaje</label>
                  <textarea
                    v-model="form.mensaje"
                    rows="5"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Escribe el contenido de la notificación..."
                  />
                </div>

                <!-- programar -->
                <div class="flex items-center justify-between rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
                  <div>
                    <p class="text-sm font-medium text-slate-800">Programar Envío</p>
                    <p class="text-xs text-slate-600">Enviar en fecha y hora específica</p>
                  </div>

                  <button
                    type="button"
                    @click="form.programar = !form.programar"
                    class="w-12 h-7 rounded-full transition relative"
                    :class="form.programar ? 'bg-blue-600' : 'bg-slate-300'"
                    aria-label="Programar envío"
                  >
                    <span
                      class="absolute top-0.5 h-6 w-6 rounded-full bg-white transition"
                      :class="form.programar ? 'left-6' : 'left-0.5'"
                    />
                  </button>
                </div>

                <div v-if="form.programar" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Fecha</label>
                    <input
                      v-model="form.fecha"
                      type="date"
                      class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Hora</label>
                    <input
                      v-model="form.hora"
                      type="time"
                      class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                </div>
              </div>

              <!-- footer -->
              <div class="p-5 border-t border-slate-200 flex justify-end gap-3">
                <button
                  @click="closeModal"
                  class="px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 transition"
                  type="button"
                >
                  Cancelar
                </button>

                <button
                  @click="submitMock"
                  class="px-4 py-2.5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition inline-flex items-center gap-2"
                  type="button"
                >
                  <PaperAirplaneIcon class="w-5 h-5" />
                  {{ form.programar ? "Programar" : "Enviar Notificación" }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </div>
</template>
