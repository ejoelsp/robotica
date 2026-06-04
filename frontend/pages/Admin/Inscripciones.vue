<script setup>
import AdminLayout from "@/layouts/AdminLayout.vue";
import { computed, ref, nextTick, watch } from "vue";
import { router, usePage, useForm } from "@inertiajs/vue3";

// Heroicons (vue)
import {
  MagnifyingGlassIcon,
  ArrowDownTrayIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClockIcon,
  UserIcon,
  PencilSquareIcon,
  ExclamationTriangleIcon,
  XMarkIcon,
  BanknotesIcon,
  SparklesIcon,
} from "@heroicons/vue/24/outline";

defineOptions({ layout: AdminLayout });

// ============================
//  PROPS (desde backend)
// ============================
const page = usePage();

const inscriptions = computed(() => page.props.inscriptions ?? []);
const categories = computed(() => page.props.categories ?? []);
const stats = computed(() => page.props.stats ?? []);
const pendingPayments = computed(() => page.props.pendingPayments ?? []);
const configuracionPago = computed(() => page.props.configuracionPago ?? null);

const configuracionPagoForm = useForm({
  informacion_pago: configuracionPago.value?.informacion_pago ?? "",
});

const informacionPagoGuardada = ref(configuracionPago.value?.informacion_pago ?? "");

watch(
  configuracionPago,
  (configuracion) => {
    const informacion = configuracion?.informacion_pago ?? "";
    configuracionPagoForm.informacion_pago = informacion;
    informacionPagoGuardada.value = informacion;
  },
  { immediate: true }
);

const hayCambiosConfiguracionPago = computed(() => {
  return configuracionPagoForm.informacion_pago.trim() !== informacionPagoGuardada.value.trim();
});

const puedeGuardarConfiguracionPago = computed(() => {
  return configuracionPagoForm.informacion_pago.trim().length > 0
    && hayCambiosConfiguracionPago.value
    && !configuracionPagoForm.processing;
});

// ============================
//  STATE
// ============================
const activeTab = ref("list"); // "list" | "validation"
const searchTerm = ref("");
const filterCategoryId = ref("all");

// ============================
//  MODAL RECHAZO (PAGO)
// ============================
const isRejectModalOpen = ref(false);
const rejectTargetId = ref(null);
const rejectReason = ref("");
const rejectOtherText = ref("");

const rejectReasons = [
  "Imagen borrosa o ilegible",
  "Comprobante incompleto (faltan datos)",
  "Archivo dañado o no abre",
  "Formato no permitido (solo PDF/JPG/PNG)",
  "No se observa el valor pagado",
  "No se observa la fecha",
  "No se observa la referencia / número de transacción",
  "Monto no coincide con el total a pagar",
  "Pago fuera del plazo permitido",
  "Otro (especificar)",
];

const isOtherReason = computed(() => rejectReason.value === "Otro (especificar)");

function openRejectModal(row) {
  rejectTargetId.value = row.id;
  rejectReason.value = "";
  rejectOtherText.value = "";
  isRejectModalOpen.value = true;
}

function closeRejectModal() {
  isRejectModalOpen.value = false;
  rejectTargetId.value = null;
  rejectReason.value = "";
  rejectOtherText.value = "";
}

// Form para rechazo (backend espera: motivo, observacion)
const rejectForm = useForm({
  motivo: "",
  observacion: "",
});

function confirmRejectPayment() {
  if (!rejectReason.value || !rejectTargetId.value) return;

  const motivo = rejectReason.value.trim();
  const observacion = isOtherReason.value ? rejectOtherText.value.trim() : "";

  if (isOtherReason.value && !observacion) return;

  rejectForm.motivo = motivo;
  rejectForm.observacion = observacion;

  rejectForm.post(`/admin/inscripciones/${rejectTargetId.value}/rechazar`, {
    preserveScroll: true,
    onSuccess: () => {
      closeRejectModal();
      router.reload({
        only: ["inscriptions", "stats", "pendingPayments"],
        preserveScroll: true,
      });
    },
  });
}

function guardarConfiguracionPago() {
  if (!puedeGuardarConfiguracionPago.value) return;

  configuracionPagoForm.put("/admin/inscripciones/configuracion-pago", {
    preserveScroll: true,
    onSuccess: () => {
      const informacionGuardada = configuracionPagoForm.informacion_pago.trim();
      configuracionPagoForm.informacion_pago = informacionGuardada;
      configuracionPagoForm.defaults("informacion_pago", informacionGuardada);
      configuracionPagoForm.clearErrors();
      informacionPagoGuardada.value = informacionGuardada;

      router.reload({
        only: ["configuracionPago"],
        preserveScroll: true,
        onSuccess: () => {
          const informacion = configuracionPago.value?.informacion_pago ?? informacionGuardada;
          configuracionPagoForm.informacion_pago = informacion;
          configuracionPagoForm.defaults("informacion_pago", informacion);
          informacionPagoGuardada.value = informacion;
        },
      });
    },
  });
}

// ============================
//  HELPERS (badges)
// ============================
const statusBadgeClass = (status) => {
  switch (status) {
    case "Aprobada":
      return "bg-emerald-50 text-emerald-700 ring-emerald-200";
    case "Pendiente":
      return "bg-amber-50 text-amber-700 ring-amber-200";
    case "Rechazada":
      return "bg-rose-50 text-rose-700 ring-rose-200";
    default:
      return "bg-slate-50 text-slate-700 ring-slate-200";
  }
};

const paymentBadgeClass = (status) => {
  switch (status) {
    case "Pagado":
      return "bg-emerald-50 text-emerald-700 ring-emerald-200";
    case "En revision":
    case "En revisión":
      return "bg-blue-50 text-blue-700 ring-blue-200";
    case "No subido":
      return "bg-slate-50 text-slate-700 ring-slate-200";
    case "Rechazado":
      return "bg-rose-50 text-rose-700 ring-rose-200";
    default:
      return "bg-slate-50 text-slate-700 ring-slate-200";
  }
};

// Badge “Motivo”
const observationBadgeClass = () => {
  return "bg-slate-100 text-slate-700 ring-slate-200";
};

const formatPrice = (value) => {
  const amount = Number(value ?? 0);
  if (!Number.isFinite(amount) || amount <= 0) return "Gratis";
  return `$ ${amount.toFixed(2)}`;
};

// ============================
//  COMPUTEDS
// ============================
const filteredInscriptions = computed(() => {
  const term = searchTerm.value.trim().toLowerCase();

  return inscriptions.value.filter((i) => {
    const matchesSearch =
      (i.team ?? "").toLowerCase().includes(term) ||
      (i.leader ?? "").toLowerCase().includes(term) ||
      (i.institution ?? "").toLowerCase().includes(term);

    const matchesCategory =
      filterCategoryId.value === "all" ||
      Number(i.categoryId) === Number(filterCategoryId.value);

    return matchesSearch && matchesCategory;
  });
});

// ============================
//  ACTIONS
// ============================

const validatePayment = (id) => {
  router.post(`/admin/inscripciones/${id}/aprobar`, {}, {
    preserveScroll: true,
    onSuccess: () => {
      router.reload({
        only: ["inscriptions", "stats", "pendingPayments"],
        preserveScroll: true,
      });
    },
  });
};

const rejectPayment = (row) => {
  openRejectModal(row);
};

const verComprobante = (row) => {
  if (!row?.comprobante_url) {
    alert("Esta inscripción no tiene comprobante subido.");
    return;
  }
  window.open(row.comprobante_url, "_blank");
};

const statsUI = computed(() => {
  const list = inscriptions.value ?? [];

  const total = list.length;
  const aprobadas = list.filter(i => i.status === "Aprobada").length;
  const pendientes = list.filter(i => ["No subido", "En revisión", "En revision"].includes(i.paymentStatus)).length;
  const rechazadas = list.filter(i => i.paymentStatus === "Rechazado").length;

  return [
    { label: "Total Inscripciones", value: total, chip: "bg-blue-600" },
    { label: "Aprobadas", value: aprobadas, chip: "bg-emerald-600" },
    { label: "Pendientes", value: pendientes, chip: "bg-amber-500" },
    { label: "Rechazadas", value: rechazadas, chip: "bg-rose-600" },
  ];
});

const isCorregirModalOpen = ref(false);
const corregirTargetId = ref(null);

function openCorregirModal(row) {
  corregirTargetId.value = row.id;
  isCorregirModalOpen.value = true;
}

function closeCorregirModal() {
  isCorregirModalOpen.value = false;
  corregirTargetId.value = null;
}

const focusKey = "admin_inscripciones_focus_id";

function confirmarCorregirDecision() {
  if (!corregirTargetId.value) return;
  
  localStorage.setItem(focusKey, String(corregirTargetId.value));

  router.post(`/admin/inscripciones/${corregirTargetId.value}/corregir-decision`, {}, {
    preserveScroll: true,
    onSuccess: () => {
      closeCorregirModal();
      activeTab.value = "validation"; 
      router.reload({ only: ["inscriptions", "pendingPayments"], preserveScroll: true });
    },
  });
}

const focusedRowId = ref(null);

function focusRowInValidation(id) {
  const el = document.querySelector(`[data-focus-id="${id}"]`);
  if (!el) return false;

  el.scrollIntoView({ behavior: "smooth", block: "center" });
  focusedRowId.value = Number(id);

  setTimeout(() => {
    focusedRowId.value = null;
  }, 1800);

  return true;
}

watch(
  () => [activeTab.value, pendingPayments.value?.length],
  async () => {
    if (activeTab.value !== "validation") return;

    const id = localStorage.getItem(focusKey);
    if (!id) return;

    await nextTick();

    // pequeño delay para asegurar que ya renderizó la lista
    setTimeout(() => {
      const ok = focusRowInValidation(id);
      if (ok) localStorage.removeItem(focusKey);
    }, 80);
  }
);

const isExportMenuOpen = ref(false);

function toggleExportMenu() {
  isExportMenuOpen.value = !isExportMenuOpen.value;
}

function closeExportMenu() {
  isExportMenuOpen.value = false;
}

function exportCSV() {
  const params = new URLSearchParams({
    format: "csv",
    category_id: filterCategoryId.value === "all" ? "" : String(filterCategoryId.value),
    q: searchTerm.value?.trim() || "",
  });
  window.location.href = `/admin/inscripciones/export?${params.toString()}`;
  closeExportMenu();
}

function exportExcel() {
  const params = new URLSearchParams({
    format: "xlsx",
    category_id: filterCategoryId.value === "all" ? "" : String(filterCategoryId.value),
    q: searchTerm.value?.trim() || "",
  });
  window.location.href = `/admin/inscripciones/export?${params.toString()}`;
  closeExportMenu();
}


</script>

<template>
  <div class="px-3 py-5 sm:px-6 sm:py-6 lg:px-4">
    <!-- Header -->
    <div class="mb-6 sm:mb-8">
      <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Gestión de Inscripciones</h1>
      <p class="text-sm text-slate-500">
        Administra las inscripciones de equipos y participantes
      </p>
    </div>

    <!-- Configuracion de pago -->
    <section
      class="mb-6 overflow-hidden rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 via-white to-emerald-50 shadow-sm"
    >
      <div class="p-4 sm:p-5 lg:p-6">
        <div class="mb-5 flex gap-4">
          <div
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-sm"
          >
            <BanknotesIcon class="h-6 w-6" />
          </div>

          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-lg font-bold text-slate-900">Datos para depósito</h2>
              <span
                class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-100"
              >
                <SparklesIcon class="h-3.5 w-3.5" />
                Visible para competidores
              </span>
            </div>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
              Configura aquí la información bancaria que verán todos los participantes antes de subir su comprobante.
            </p>
          </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-2 lg:items-start">
          <div class="space-y-3">
            <div>
              <label class="mb-2 block text-sm font-semibold text-slate-800">
                Información de pago
              </label>
              <textarea
                v-model="configuracionPagoForm.informacion_pago"
                rows="9"
                class="w-full resize-y rounded-2xl border border-blue-100 bg-white px-4 py-3 text-sm leading-6 text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-blue-300 focus:ring-4 focus:ring-blue-100"
                placeholder="Ej: Banco Pichincha&#10;Cuenta de ahorro transaccional&#10;Número: 2203247723&#10;Titular: Luis Heriberto Guano Arbito&#10;C.I.: 0504450305&#10;Correo: ejemplo@espoch.edu.ec&#10;Nota: En asunto colocar la categoría y el nombre del prototipo."
              />
              <p v-if="configuracionPagoForm.errors.informacion_pago" class="mt-2 text-sm text-rose-600">
                {{ configuracionPagoForm.errors.informacion_pago }}
              </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <p
                class="text-sm font-medium"
                :class="
                  configuracionPagoForm.recentlySuccessful
                    ? 'text-emerald-700'
                    : hayCambiosConfiguracionPago
                    ? 'text-amber-700'
                    : 'text-slate-500'
                "
              >
                {{
                  configuracionPagoForm.recentlySuccessful
                    ? "Configuración de pago guardada correctamente."
                    : hayCambiosConfiguracionPago
                    ? "Tienes cambios pendientes por guardar."
                    : "La configuración global está guardada."
                }}
              </p>

              <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-300"
                :disabled="!puedeGuardarConfiguracionPago"
                @click="guardarConfiguracionPago"
              >
                <BanknotesIcon class="h-4 w-4" />
                {{ configuracionPagoForm.processing ? "Guardando..." : "Guardar datos de pago" }}
              </button>
            </div>
          </div>

          <div class="rounded-2xl border border-white/80 bg-white/75 px-4 py-4 shadow-sm sm:px-5 sm:py-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Vista previa</p>
              <p
                v-if="configuracionPagoForm.informacion_pago.trim()"
              class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-800"
              >
                {{ configuracionPagoForm.informacion_pago }}
              </p>
            <p v-else class="mt-3 text-sm text-slate-500">
                Aún no hay datos configurados. Escribe la información en el cuadro de la derecha.
              </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Stats -->
    <div class="mb-6 grid grid-cols-1 gap-3 sm:gap-4 md:grid-cols-4">
      <div
        v-for="(s, idx) in statsUI"   
        :key="idx"
        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"
      >
        <div class="flex items-start justify-between">
          <div
            class="flex h-10 w-10 items-center justify-center rounded-xl text-white font-semibold"
            :class="s.chip || 'bg-slate-600'"
          >
            {{ s.value ?? 0 }}
          </div>
        </div>
        <p class="mt-4 text-sm text-slate-600">{{ s.label }}</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="space-y-4">
      <div class="inline-flex w-full rounded-2xl bg-gray-200 p-1 sm:w-auto sm:rounded-full">
        <button
          class="flex-1 px-4 py-2 text-sm rounded-xl transition sm:flex-none sm:rounded-full"
          :class="
            activeTab === 'list'
              ? 'bg-white shadow-sm text-slate-900 font-semibold'
              : 'text-slate-600 hover:text-slate-900'
          "
          @click="activeTab = 'list'"
        >
          Lista de Inscripciones
        </button>
        <button
          class="flex-1 px-4 py-2 text-sm rounded-xl transition sm:flex-none sm:rounded-full"
          :class="
            activeTab === 'validation'
              ? 'bg-white shadow-sm text-slate-900 font-semibold'
              : 'text-slate-600 hover:text-slate-900'
          "
          @click="activeTab = 'validation'"
        >
          Validación de Pagos
        </button>
      </div>

      <!-- TAB: LIST -->
      <div v-if="activeTab === 'list'" class="space-y-4">
        <!-- Filters -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex flex-col md:flex-row gap-4">
            <!-- Search -->
            <div class="relative flex-1">
              <MagnifyingGlassIcon
                class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"
              />
              <input
                v-model="searchTerm"
                type="text"
                placeholder="Buscar por equipo, líder o institución..."
                class="w-full rounded-xl border border-slate-200 bg-white px-10 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
              />
            </div>

            <!-- Filter -->
            <div class="w-full md:w-72">
              <select
                v-model="filterCategoryId"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
              >
                <option value="all">Todas las categorías</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                  {{ category.name }} · {{ category.count }} inscripciones
                </option>
              </select>
            </div>

            <!-- Export -->
            <div class="relative">
              <button
                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-900 hover:bg-slate-50"
                type="button"
                @click="toggleExportMenu"
              >
                <ArrowDownTrayIcon class="w-4 h-4 mr-2" />
                Exportar
              </button>

              <div
                v-if="isExportMenuOpen"
                class="absolute right-0 mt-2 w-48 rounded-xl border border-slate-200 bg-white shadow-lg overflow-hidden z-50"
              >
                <button
                  type="button"
                  class="w-full text-left px-4 py-2.5 text-sm hover:bg-slate-50"
                  @click="exportCSV"
                >
                  Exportar CSV
                </button>

                <button
                  type="button"
                  class="w-full text-left px-4 py-2.5 text-sm hover:bg-slate-50"
                  @click="exportExcel"
                >
                  Exportar Excel
                </button>
              </div>

              <div
                v-if="isExportMenuOpen"
                class="fixed inset-0 z-40"
                @click="closeExportMenu"
              ></div>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-[1080px] w-full">
              <thead class="bg-slate-50">
                <tr class="text-left text-xs font-semibold text-slate-600">
                  <th class="px-5 py-4">Equipo</th>
                  <th class="px-5 py-4">Líder</th>
                  <th class="px-5 py-4">Institución</th>
                  <th class="px-5 py-4">Categoría</th>
                  <th class="px-5 py-4">Prototipo</th>
                  <th class="px-5 py-4">Precio</th>
                  <th class="px-5 py-4">Estado</th>
                  <th class="px-5 py-4">Pago</th>
                  <th class="px-5 py-4">Observación</th>
                  <th class="px-5 py-4">Acciones</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-slate-100">
                <tr v-for="row in filteredInscriptions" :key="row.id" class="hover:bg-slate-50/60">
                  <!-- Team -->
                  <td class="px-5 py-4 text-sm text-slate-900">
                    <div class="flex items-center gap-2">
                      <UserIcon class="w-4 h-4 text-slate-400" />
                      <span class="font-medium">{{ row.team }}</span>
                    </div>
                  </td>

                  <!-- Leader -->
                  <td class="px-5 py-4 text-sm">
                    <div class="text-slate-900 font-medium">{{ row.leader }}</div>
                    <div class="text-xs text-slate-500">{{ row.email }}</div>
                  </td>

                  <td class="px-5 py-4 text-sm text-slate-700">{{ row.institution }}</td>

                  <!-- Category -->
                  <td class="px-5 py-4">
                    <span
                      class="inline-flex items-center rounded-full border border-slate-200 px-3 py-1 text-xs text-slate-700"
                    >
                      {{ row.category }}
                    </span>
                  </td>

                  <td class="px-5 py-4 text-sm text-slate-700">
                    {{ row.prototype }}
                  </td>

                  <td class="px-5 py-4">
                    <span
                      class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700 whitespace-nowrap"
                    >
                      {{ formatPrice(row.categoryPrice) }}
                    </span>
                  </td>

                  <!-- Status -->
                  <td class="px-5 py-4">
                    <span
                      class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium ring-1"
                      :class="statusBadgeClass(row.status)"
                    >
                      <CheckCircleIcon v-if="row.status === 'Aprobada'" class="w-4 h-4" />
                      <ClockIcon v-else-if="row.status === 'Pendiente'" class="w-4 h-4" />
                      <XCircleIcon v-else class="w-4 h-4" />
                      {{ row.status }}
                    </span>
                  </td>

                  <!-- Payment -->
                  <td class="px-5 py-4">
                    <span
                      class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ring-1 whitespace-nowrap"
                      :class="paymentBadgeClass(row.paymentStatus)"
                    >
                      {{ row.paymentStatus }}
                    </span>
                  </td>

                  <!-- Observación (badge + tooltip) -->
                  <td class="px-5 py-4">
                    <div v-if="row.paymentObservation" class="relative inline-block group">
                      <span
                        class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium ring-1 cursor-help"
                        :class="observationBadgeClass()"
                      >
                        <ExclamationTriangleIcon class="w-4 h-4" />
                        Motivo
                      </span>

                      <!-- Tooltip -->
                      <div
                        class="pointer-events-none absolute left-0 top-full z-20 mt-2 w-72 rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-700 shadow-lg opacity-0 translate-y-1
                               group-hover:opacity-100 group-hover:translate-y-0 transition"
                      >
                        <p class="font-semibold text-slate-900 mb-1">Observación</p>
                        <p class="leading-relaxed">{{ row.paymentObservation }}</p>
                      </div>
                    </div>

                    <span v-else class="text-xs text-slate-400">—</span>
                  </td>

                  <!-- Actions -->
                  <td class="px-5 py-4">
                    <div class="flex gap-2">
                      <button
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        type="button"
                        :disabled="!['Pagado', 'Rechazado'].includes(row.paymentStatus)"
                        @click="openCorregirModal(row)"
                      >
                        <PencilSquareIcon class="w-4 h-4 text-slate-700" />
                        Corregir decisión
                      </button>
                    </div>
                  </td>
                </tr>

                <tr v-if="filteredInscriptions.length === 0">
                  <td colspan="10" class="px-5 py-10 text-center text-sm text-slate-500">
                    No hay resultados con los filtros actuales.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- TAB: VALIDATION -->
      <div v-else class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
          <h3 class="text-lg font-semibold text-slate-900">Validación de Comprobantes de Pago</h3>

          <div class="mt-4 space-y-4">
            <div
              v-for="row in pendingPayments"
              :key="row.id"
              class="rounded-2xl border border-slate-200 p-4"
              :data-focus-id="row.id"
              :class="Number(row.id) === focusedRowId ? 'ring-2 ring-blue-400 bg-blue-50/30' : ''"
            >
              <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex-1">
                  <div class="mt-4 space-y-3">
                    <div
                      v-for="item in row.items"
                      :key="`${row.id}-${item.id}`"
                      class="flex items-center justify-between gap-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3"
                    >
                      <div class="min-w-0">
                        <p class="font-semibold text-slate-900">{{ item.category }}</p>
                        <p class="mt-1 text-sm text-slate-600">Prototipo: {{ item.prototype }}</p>
                      </div>

                      <div class="shrink-0 text-right">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                          Valor
                        </p>
                        <p class="mt-1 text-lg font-bold text-slate-900">
                          {{ formatPrice(item.amount) }}
                        </p>
                      </div>
                    </div>
                  </div>

                  <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4">
                    <p class="text-sm font-medium text-slate-600">Monto total de pago</p>
                    <p class="text-2xl font-bold text-slate-900">
                      {{ formatPrice(row.totalAmount) }}
                    </p>
                  </div>
                </div>

                <div class="flex flex-col justify-center gap-3 lg:ml-8 lg:min-w-[190px]">
                  <button
                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="!row.comprobante_url"
                    @click="verComprobante(row)"
                  >
                    Ver Comprobante
                  </button>

                  <button
                    class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="!row.puede_validar"
                    @click="validatePayment(row.id)"
                  >
                    Validar
                  </button>

                  <button
                    class="w-full rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-medium text-rose-600 transition hover:bg-rose-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="!row.puede_validar"
                    @click="rejectPayment(row)"
                  >
                    Rechazar
                  </button>
                </div>
              </div>
            </div>

            <div v-if="pendingPayments.length === 0" class="py-10 text-center text-slate-500">
              No hay pagos pendientes de validación
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- =========================
         MODAL: RECHAZAR PAGO
         ========================= -->
    <Teleport to="body">
      <div v-if="isRejectModalOpen" class="fixed inset-0 z-[9999]">
        <!-- overlay -->
        <div class="absolute inset-0 bg-black/40" @click="closeRejectModal"></div>

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
                <h2 class="text-lg font-semibold text-slate-900">Rechazar comprobante</h2>
                <p class="text-sm text-slate-500 mt-1">
                  Selecciona el motivo del rechazo. Si no aplica, usa “Otro”.
                </p>
              </div>

              <button
                class="h-9 w-9 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-center justify-center shrink-0"
                @click="closeRejectModal"
                type="button"
              >
                <XMarkIcon class="w-5 h-5 text-slate-600" />
              </button>
            </div>

            <!-- body -->
            <div class="p-5 space-y-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Motivo</label>
                <select
                  v-model="rejectReason"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
                >
                  <option value="" disabled>Selecciona un motivo…</option>
                  <option v-for="r in rejectReasons" :key="r" :value="r">{{ r }}</option>
                </select>
              </div>

              <div v-if="isOtherReason">
                <label class="block text-sm font-medium text-slate-700 mb-1">
                  Especifica el motivo
                </label>
                <textarea
                  v-model="rejectOtherText"
                  rows="4"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-200"
                  placeholder="Ej: El comprobante corresponde a otra inscripción…"
                />
                <p class="text-xs text-slate-500 mt-2">
                  Recomendación: escribe una frase corta y clara.
                </p>
              </div>

              <p v-if="rejectForm.errors.motivo" class="text-sm text-rose-600">
                {{ rejectForm.errors.motivo }}
              </p>
              <p v-if="rejectForm.errors.observacion" class="text-sm text-rose-600">
                {{ rejectForm.errors.observacion }}
              </p>
            </div>

            <!-- footer -->
            <div class="p-4 border-t border-slate-200 flex flex-col-reverse gap-2 sm:p-5 sm:flex-row sm:justify-end sm:gap-3">
              <button
                @click="closeRejectModal"
                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 transition sm:w-auto"
                type="button"
              >
                Cancelar
              </button>

              <button
                @click="confirmRejectPayment"
                class="w-full px-4 py-2.5 rounded-xl bg-rose-600 text-white hover:bg-rose-700 transition font-semibold disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                :disabled="!rejectReason || (isOtherReason && !rejectOtherText.trim()) || rejectForm.processing"
                type="button"
              >
                {{ rejectForm.processing ? "Procesando..." : "Confirmar rechazo" }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>

  <Teleport to="body">
    <div v-if="isCorregirModalOpen" class="fixed inset-0 z-[9999]">
      <div class="absolute inset-0 bg-black/40" @click="closeCorregirModal"></div>

      <div class="relative h-full w-full grid place-items-center p-4 sm:p-6">
        <div class="w-full max-w-md rounded-2xl bg-white border border-slate-200 shadow-xl overflow-hidden">
          <div class="p-5 border-b border-slate-200 flex items-start justify-between gap-4">
            <div>
              <h2 class="text-lg font-semibold text-slate-900">Corregir decisión</h2>
              <p class="text-sm text-slate-500 mt-1">
                Esta inscripción volverá a “En revisión”. Luego corrígela en “Validación de Pagos”.
              </p>
            </div>

            <button
              class="h-9 w-9 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-center justify-center shrink-0"
              @click="closeCorregirModal"
              type="button"
            >
              <XMarkIcon class="w-5 h-5 text-slate-600" />
            </button>
          </div>

          <div class="p-4 flex flex-col-reverse gap-2 sm:p-5 sm:flex-row sm:justify-end sm:gap-3">
            <button
              class="w-full px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 transition sm:w-auto"
              type="button"
              @click="closeCorregirModal"
            >
              Cancelar
            </button>

            <button
              class="w-full px-4 py-2.5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition font-semibold sm:w-auto"
              type="button"
              @click="confirmarCorregirDecision"
            >
              Enviar a validación
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
