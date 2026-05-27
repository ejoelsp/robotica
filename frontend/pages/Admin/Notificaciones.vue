<script setup>
import AdminLayout from "@/layouts/AdminLayout.vue";
import { computed, ref, watch } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";

import {
  BellAlertIcon,
  CheckCircleIcon,
  ClockIcon,
  EnvelopeIcon,
  ExclamationTriangleIcon,
  InboxIcon,
  PaperAirplaneIcon,
  PlusIcon,
  TrophyIcon,
  UserGroupIcon,
  XMarkIcon,
} from "@heroicons/vue/24/outline";

defineOptions({ layout: AdminLayout });

const props = defineProps({
  notificacionesEnviadas: {
    type: Array,
    default: () => [],
  },
  notificacionesRecibidas: {
    type: Array,
    default: () => [],
  },
  categorias: {
    type: Array,
    default: () => [],
  },
  competenciaActualId: {
    type: Number,
    default: null,
  },
  stats: {
    type: Object,
    default: () => ({
      enviadas: 0,
      recibidas: 0,
      noLeidas: 0,
      destinatarios: 0,
    }),
  },
});

const page = usePage();
const activeTab = ref("enviadas");
const isModalOpen = ref(false);

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const enviadas = ref([...(props.notificacionesEnviadas ?? [])]);
const recibidas = ref([...(props.notificacionesRecibidas ?? [])]);
const dashboardStats = ref({ ...(props.stats ?? {}) });
const categoriasDisponibles = computed(() => props.categorias ?? []);

watch(
  () => props.notificacionesEnviadas,
  (value) => {
    enviadas.value = [...(value ?? [])];
  }
);

watch(
  () => props.notificacionesRecibidas,
  (value) => {
    recibidas.value = [...(value ?? [])];
  }
);

watch(
  () => props.stats,
  (value) => {
    dashboardStats.value = { ...(value ?? {}) };
  }
);

const form = useForm({
  tipo: "notificacion_admin",
  destinatarios: "competidor",
  canal: "app",
  competencia_id: props.competenciaActualId,
  categoria_ids: [],
  asunto: "",
  contenido: "",
});

const tipoOptions = [
  { value: "notificacion_admin", label: "Notificación del administrador" },
];

const destinatariosOptions = [
  { value: "competidor", label: "Competidores" },
];

const canalOptions = [
  { value: "app", label: "Solo aplicación" },
  { value: "email", label: "Solo correo" },
  { value: "app_email", label: "Aplicación y correo" },
];

const canSubmit = computed(() => {
  return (
    form.categoria_ids.length > 0 &&
    form.asunto.trim().length > 0 &&
    form.contenido.trim().length > 0
  );
});

function openCreate() {
  isModalOpen.value = true;
  form.reset();
  form.tipo = "notificacion_admin";
  form.destinatarios = "competidor";
  form.canal = "app";
  form.competencia_id = props.competenciaActualId;
  form.categoria_ids = [];
  form.clearErrors();
}

function closeModal() {
  isModalOpen.value = false;
  form.clearErrors();
}

function submit() {
  if (!canSubmit.value) return;

  form.post("/admin/notificaciones", {
    preserveScroll: true,
    onSuccess: () => {
      closeModal();
      activeTab.value = "enviadas";
      router.reload({
        only: ["notificacionesEnviadas", "stats"],
        preserveScroll: true,
      });
    },
  });
}

function markAsRead(id) {
  if (!id) return;

  const target = recibidas.value.find((item) => item.id === id);
  if (!target || target.leido) return;

  target.leido = true;
  dashboardStats.value.noLeidas = Math.max(0, Number(dashboardStats.value.noLeidas ?? 0) - 1);
  window.dispatchEvent(new CustomEvent("admin-notificaciones:actualizar-contador", {
    detail: { noLeidas: dashboardStats.value.noLeidas },
  }));

  fetch(`/notificaciones/${id}/leer`, {
    method: "PATCH",
    credentials: "same-origin",
    headers: {
      "Content-Type": "application/json",
      "X-Requested-With": "XMLHttpRequest",
      "X-XSRF-TOKEN": decodeURIComponent(
        document.cookie
          .split("; ")
          .find((row) => row.startsWith("XSRF-TOKEN="))
          ?.split("=")[1] ?? ""
      ),
    },
    body: JSON.stringify({}),
  }).then((response) => {
    if (!response.ok) {
      target.leido = false;
      dashboardStats.value.noLeidas = Number(dashboardStats.value.noLeidas ?? 0) + 1;
      window.dispatchEvent(new CustomEvent("admin-notificaciones:actualizar-contador", {
        detail: { noLeidas: dashboardStats.value.noLeidas },
      }));
    }
  });
}

function isCategoriaSelected(value) {
  return form.categoria_ids.includes(String(value));
}

function toggleTodasCategorias() {
  form.categoria_ids = ["all"];
}

function toggleCategoria(id) {
  const value = String(id);
  const current = form.categoria_ids.includes("all")
    ? []
    : [...form.categoria_ids];

  if (current.includes(value)) {
    const next = current.filter((item) => item !== value);
    form.categoria_ids = next.length > 0 ? next : ["all"];
    return;
  }

  form.categoria_ids = [...current, value];
}

function categoriasError() {
  if (form.errors.categoria_ids) return form.errors.categoria_ids;

  return null;
}

function typeMeta(type) {
  switch (type) {
    case "reclamo_competidor":
      return { icon: InboxIcon, iconColor: "text-rose-600", bgColor: "bg-rose-50" };
    case "resultados_publicados":
      return { icon: TrophyIcon, iconColor: "text-yellow-600", bgColor: "bg-yellow-50" };
    case "inscripcion_aprobada":
      return { icon: CheckCircleIcon, iconColor: "text-emerald-600", bgColor: "bg-emerald-50" };
    case "inscripcion_rechazada":
      return { icon: ExclamationTriangleIcon, iconColor: "text-rose-600", bgColor: "bg-rose-50" };
    default:
      return { icon: BellAlertIcon, iconColor: "text-blue-600", bgColor: "bg-blue-50" };
  }
}

function statusBadgeClass(status) {
  if (status === "enviado") return "bg-emerald-50 text-emerald-700 ring-emerald-200";
  if (status === "error") return "bg-red-50 text-red-700 ring-red-200";
  return "bg-amber-50 text-amber-700 ring-amber-200";
}

function channelLabel(canal) {
  return canalOptions.find((item) => item.value === canal)?.label ?? canal;
}
</script>

<template>
  <div class="w-full">
    <div class="mx-auto w-full max-w-[1180px] px-4 sm:px-6 lg:px-4 py-6 space-y-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">Sistema de Notificaciones</h1>
          <p class="text-sm text-slate-500">Envía avisos internos y correos a competidores</p>
        </div>

        <button
          @click="openCreate"
          class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition w-full sm:w-auto"
          type="button"
        >
          <PlusIcon class="w-5 h-5" />
          Nueva Notificación
        </button>
      </div>

      <div v-if="flashSuccess" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ flashSuccess }}
      </div>
      <div v-if="flashError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ flashError }}
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="h-10 w-10 rounded-xl bg-blue-600/10 flex items-center justify-center">
            <PaperAirplaneIcon class="w-5 h-5 text-blue-600" />
          </div>
          <p class="text-3xl font-semibold text-slate-900 mt-4">{{ dashboardStats.enviadas ?? 0 }}</p>
          <p class="text-sm text-slate-500 mt-1">Notificaciones enviadas</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="h-10 w-10 rounded-xl bg-emerald-600/10 flex items-center justify-center">
            <InboxIcon class="w-5 h-5 text-emerald-600" />
          </div>
          <p class="text-3xl font-semibold text-slate-900 mt-4">{{ dashboardStats.recibidas ?? 0 }}</p>
          <p class="text-sm text-slate-500 mt-1">Recibidas</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="h-10 w-10 rounded-xl bg-orange-600/10 flex items-center justify-center">
            <BellAlertIcon class="w-5 h-5 text-orange-600" />
          </div>
          <p class="text-3xl font-semibold text-slate-900 mt-4">{{ dashboardStats.noLeidas ?? 0 }}</p>
          <p class="text-sm text-slate-500 mt-1">No leídas</p>
        </div>

      </div>

      <div class="inline-flex items-center rounded-2xl bg-gray-200 p-1">
        <button
          class="px-4 py-2 rounded-2xl text-sm font-medium transition"
          :class="activeTab === 'enviadas' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'enviadas'"
          type="button"
        >
          Enviadas
        </button>
        <button
          class="px-4 py-2 rounded-2xl text-sm font-medium transition"
          :class="activeTab === 'recibidas' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'recibidas'"
          type="button"
        >
          Recibidas
        </button>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div v-if="activeTab === 'enviadas'" class="p-4 sm:p-6 space-y-4">
          <div v-for="n in enviadas" :key="n.id" class="rounded-2xl border border-slate-200 bg-white p-5 flex gap-4">
            <div class="h-12 w-12 rounded-xl flex items-center justify-center shrink-0" :class="typeMeta(n.tipo).bgColor">
              <component :is="typeMeta(n.tipo).icon" class="w-6 h-6" :class="typeMeta(n.tipo).iconColor" />
            </div>

            <div class="min-w-0 flex-1">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="font-semibold text-slate-900 leading-tight">{{ n.asunto }}</p>
                  <p class="text-sm text-slate-600 mt-1">{{ n.contenido }}</p>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ring-1 shrink-0" :class="statusBadgeClass(n.estado)">
                  {{ n.estado }}
                </span>
              </div>

              <div class="flex flex-wrap gap-4 text-xs text-slate-500 mt-3">
                <div class="inline-flex items-center gap-1">
                  <UserGroupIcon class="w-4 h-4" />
                  <span>{{ n.destinatario?.nombre || n.email_destino || "Sin destinatario" }}</span>
                </div>
                <div class="inline-flex items-center gap-1">
                  <EnvelopeIcon class="w-4 h-4" />
                  <span>{{ channelLabel(n.canal) }}</span>
                </div>
                <div class="inline-flex items-center gap-1">
                  <ClockIcon class="w-4 h-4" />
                  <span>{{ n.enviado_en || n.created_at }}</span>
                </div>
              </div>
            </div>
          </div>

          <div v-if="enviadas.length === 0" class="py-12 text-center text-slate-500">
            No hay notificaciones enviadas.
          </div>
        </div>

        <div v-else class="p-4 sm:p-6 space-y-4">
          <div
            v-for="n in recibidas"
            :key="n.id"
            class="rounded-2xl border border-slate-200 bg-white p-5 flex gap-4"
            :class="!n.leido ? 'ring-1 ring-blue-100' : ''"
          >
            <div class="h-12 w-12 rounded-xl flex items-center justify-center shrink-0" :class="typeMeta(n.tipo).bgColor">
              <component :is="typeMeta(n.tipo).icon" class="w-6 h-6" :class="typeMeta(n.tipo).iconColor" />
            </div>

            <div class="min-w-0 flex-1">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="font-semibold text-slate-900 leading-tight">{{ n.asunto }}</p>
                  <p class="text-sm text-slate-600 mt-1">{{ n.contenido }}</p>
                </div>
                <button
                  v-if="!n.leido"
                  @click.prevent.stop="markAsRead(n.id)"
                  class="px-3 py-1.5 rounded-xl bg-blue-50 text-blue-700 text-xs font-medium hover:bg-blue-100 transition shrink-0"
                  type="button"
                >
                  Marcar leída
                </button>
              </div>

              <div class="flex flex-wrap gap-4 text-xs text-slate-500 mt-3">
                <div class="inline-flex items-center gap-1">
                  <EnvelopeIcon class="w-4 h-4" />
                  <span>{{ channelLabel(n.canal) }}</span>
                </div>
                <div class="inline-flex items-center gap-1">
                  <ClockIcon class="w-4 h-4" />
                  <span>{{ n.created_at }}</span>
                </div>
              </div>

              <a
                v-if="n.tipo === 'reclamo_competidor' && n.datos?.formato_url"
                :href="n.datos.formato_url"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-4 inline-flex items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-100"
              >
                Ver formato del reclamo
              </a>
            </div>
          </div>

          <div v-if="recibidas.length === 0" class="py-12 text-center text-slate-500">
            No tienes notificaciones recibidas.
          </div>
        </div>
      </div>

      <Teleport to="body">
        <div v-if="isModalOpen" class="fixed inset-0 z-[9999]">
          <div class="absolute inset-0 bg-black/40" @click="closeModal"></div>

          <div class="relative h-full w-full grid place-items-center p-4 sm:p-6">
            <div class="w-full max-w-2xl rounded-2xl bg-white border border-slate-200 shadow-xl overflow-hidden max-h-[90vh] flex flex-col">
              <div class="p-5 border-b border-slate-200 flex items-start justify-between gap-4">
                <div>
                  <h2 class="text-lg font-semibold text-slate-900">Crear Nueva Notificación</h2>
                  <p class="text-sm text-slate-500 mt-1">Define categorías, canal y contenido</p>
                </div>

                <button class="h-9 w-9 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-center justify-center shrink-0" @click="closeModal" type="button">
                  <XMarkIcon class="w-5 h-5 text-slate-600" />
                </button>
              </div>

              <div class="p-5 space-y-4 overflow-y-auto">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Tipo</label>
                  <select v-model="form.tipo" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option v-for="t in tipoOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                  </select>
                  <p v-if="form.errors.tipo" class="text-xs text-red-600 mt-1">{{ form.errors.tipo }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div class="space-y-4">
                    <div>
                      <label class="block text-sm font-medium text-slate-700 mb-1">Destinatarios</label>
                      <select v-model="form.destinatarios" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option v-for="d in destinatariosOptions" :key="d.value" :value="d.value">{{ d.label }}</option>
                      </select>
                      <p v-if="form.errors.destinatarios" class="text-xs text-red-600 mt-1">{{ form.errors.destinatarios }}</p>
                    </div>

                    <div>
                      <label class="block text-sm font-medium text-slate-700 mb-1">Canal</label>
                      <select v-model="form.canal" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option v-for="c in canalOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
                      </select>
                      <p v-if="form.errors.canal" class="text-xs text-red-600 mt-1">{{ form.errors.canal }}</p>
                    </div>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Categorías</label>
                    <div class="max-h-44 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2">
                      <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm text-slate-800 hover:bg-slate-50">
                        <input
                          type="checkbox"
                          class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                          :checked="form.categoria_ids.includes('all')"
                          @change="toggleTodasCategorias"
                        />
                        <span class="font-medium">Todas las categorías</span>
                      </label>

                      <label
                        v-for="categoria in categoriasDisponibles"
                        :key="categoria.id"
                        class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm text-slate-800 hover:bg-slate-50"
                      >
                        <input
                          type="checkbox"
                          class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                          :checked="isCategoriaSelected(categoria.id)"
                          @change="toggleCategoria(categoria.id)"
                        />
                        <span>{{ categoria.nombre }}</span>
                      </label>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">Puedes seleccionar una o varias categorías.</p>
                    <p v-if="categoriasError()" class="text-xs text-red-600 mt-1">{{ categoriasError() }}</p>
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Asunto</label>
                  <input
                    v-model="form.asunto"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Ej: Información importante"
                  />
                  <p v-if="form.errors.asunto" class="text-xs text-red-600 mt-1">{{ form.errors.asunto }}</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Mensaje</label>
                  <textarea
                    v-model="form.contenido"
                    rows="5"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Escribe el contenido de la notificación..."
                  />
                  <p v-if="form.errors.contenido" class="text-xs text-red-600 mt-1">{{ form.errors.contenido }}</p>
                </div>

                <div v-if="form.canal !== 'app'" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                  Se enviará correo usando Brevo. Recuerda el límite del plan free: 300 correos por día.
                </div>
              </div>

              <div class="p-5 border-t border-slate-200 flex justify-end gap-3">
                <button @click="closeModal" class="px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 transition" type="button">
                  Cancelar
                </button>

                <button
                  @click="submit"
                  :disabled="form.processing || !canSubmit"
                  class="px-4 py-2.5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition inline-flex items-center gap-2 disabled:opacity-60"
                  type="button"
                >
                  <PaperAirplaneIcon class="w-5 h-5" />
                  {{ form.processing ? "Enviando..." : "Enviar notificación" }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </div>
</template>
