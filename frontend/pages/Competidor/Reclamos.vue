<script setup>
import { computed, ref, watch } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import CompetidorLayout from "@/layouts/CompetidorLayout.vue";
import {
  ArrowTopRightOnSquareIcon,
  CheckCircleIcon,
  DocumentTextIcon,
  PaperAirplaneIcon,
} from "@heroicons/vue/24/outline";

defineOptions({ layout: CompetidorLayout });

const props = defineProps({
  inscripcionesAprobadas: {
    type: Array,
    default: () => [],
  },
  reclamos: {
    type: Array,
    default: () => [],
  },
});

const page = usePage();
const feedback = ref("");
const selectedCategoriaId = ref(props.inscripcionesAprobadas[0]?.categoria?.id ?? "");

const form = useForm({
  inscripcion_id: props.inscripcionesAprobadas[0]?.id ?? "",
  descripcion: "",
});

const categoriasDisponibles = computed(() => {
  const categorias = new Map();

  props.inscripcionesAprobadas.forEach((item) => {
    const categoriaId = item.categoria?.id;
    if (!categoriaId || categorias.has(categoriaId)) return;

    categorias.set(categoriaId, {
      id: categoriaId,
      nombre: item.categoria?.nombre ?? "Categoría",
    });
  });

  return Array.from(categorias.values());
});

const prototiposDisponibles = computed(() =>
  props.inscripcionesAprobadas.filter(
    (item) => Number(item.categoria?.id) === Number(selectedCategoriaId.value)
  )
);

const selectedInscripcion = computed(() =>
  props.inscripcionesAprobadas.find((item) => Number(item.id) === Number(form.inscripcion_id))
);

const canSubmit = computed(() => form.inscripcion_id && form.descripcion.trim().length >= 10);

watch(
  () => page.props.flash?.success,
  (message) => {
    if (!message) return;
    feedback.value = message;
    form.reset("descripcion");
  },
  { immediate: true }
);

watch(selectedCategoriaId, () => {
  const primeraInscripcion = prototiposDisponibles.value[0];
  form.inscripcion_id = primeraInscripcion?.id ?? "";
});

function visualizarReclamo() {
  if (!canSubmit.value) return;

  const params = new URLSearchParams({
    inscripcion_id: String(form.inscripcion_id),
    descripcion: form.descripcion.trim(),
  });

  window.open(`/competidor/reclamos/preview?${params.toString()}`, "_blank", "noopener,noreferrer");
}

function enviarReclamo() {
  if (!canSubmit.value) return;

  form.post("/competidor/reclamos", {
    preserveScroll: true,
  });
}

function estadoLabel(estado) {
  if (estado === "recibido") return "recibido";
  if (estado === "pendiente") return "pendiente";

  return estado || "pendiente";
}

function estadoClasses(estado) {
  if (estado === "recibido") {
    return "bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200";
  }

  return "bg-amber-50 text-amber-700 ring-1 ring-amber-200";
}
</script>

<template>
  <div class="mx-auto w-full max-w-[1120px] space-y-5 px-3 py-5 sm:space-y-6 sm:px-6 sm:py-6 lg:px-4">
    <section>
      <h1 class="text-2xl font-bold text-slate-900">Reclamos</h1>
      <p class="mt-1 text-sm text-slate-500">
        Selecciona una categoría aprobada, el prototipo registrado y escribe tu reclamo de forma puntual.
      </p>
    </section>

    <div
      v-if="feedback"
      class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
    >
      <CheckCircleIcon class="mt-0.5 h-5 w-5 shrink-0" />
      <p>{{ feedback }}</p>
    </div>

    <section class="grid gap-5 sm:gap-6 lg:grid-cols-[1fr_360px]">
      <form class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6" @submit.prevent="enviarReclamo">
        <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
          <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50">
            <DocumentTextIcon class="h-6 w-6 text-blue-600" />
          </div>
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Nuevo reclamo</h2>
            <p class="text-sm text-slate-500">El sistema completará automáticamente los datos del equipo.</p>
          </div>
        </div>

        <div v-if="inscripcionesAprobadas.length" class="mt-5 space-y-5">
          <div class="grid gap-3 sm:gap-4 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-semibold text-slate-700">Categoría</label>
              <select
                v-model="selectedCategoriaId"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option v-for="categoria in categoriasDisponibles" :key="categoria.id" :value="categoria.id">
                  {{ categoria.nombre }}
                </option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-semibold text-slate-700">Prototipo</label>
              <select
                v-model="form.inscripcion_id"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option v-for="item in prototiposDisponibles" :key="item.id" :value="item.id">
                  {{ item.prototipo_nombre || "Prototipo sin nombre" }}
                </option>
              </select>
              <p v-if="form.errors.inscripcion_id" class="mt-1 text-xs text-red-600">
                {{ form.errors.inscripcion_id }}
              </p>
            </div>
          </div>

          <div v-if="selectedInscripcion" class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2 sm:p-4">
            <div>
              <p class="text-xs font-semibold uppercase text-slate-500">Evento</p>
              <p class="mt-1 text-sm font-semibold text-slate-900">{{ selectedInscripcion.evento.nombre }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase text-slate-500">Categoría</p>
              <p class="mt-1 text-sm font-semibold text-slate-900">{{ selectedInscripcion.categoria.nombre }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase text-slate-500">Equipo</p>
              <p class="mt-1 text-sm font-semibold text-slate-900">{{ selectedInscripcion.equipo.nombre }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase text-slate-500">Prototipo</p>
              <p class="mt-1 text-sm font-semibold text-slate-900">
                {{ selectedInscripcion.prototipo_nombre || "No registrado" }}
              </p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase text-slate-500">Institución</p>
              <p class="mt-1 text-sm font-semibold text-slate-900">
                {{ selectedInscripcion.institucion || "No registrada" }}
              </p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase text-slate-500">Jueces asignados</p>
              <p class="mt-1 text-sm font-semibold text-slate-900">
                {{ selectedInscripcion.jueces.length || 0 }}
              </p>
            </div>
          </div>

          <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">
              Escribe de forma puntual tu reclamo
            </label>
            <textarea
              v-model="form.descripcion"
              rows="8"
              maxlength="3000"
              class="w-full resize-y rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Describe el reclamo de manera clara, concreta y respetuosa."
            />
            <div class="mt-1 flex justify-between gap-3 text-xs">
              <p class="text-red-600">{{ form.errors.descripcion }}</p>
              <p class="ml-auto text-slate-400">{{ form.descripcion.length }}/3000</p>
            </div>
          </div>

          <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:flex-wrap sm:justify-end sm:pt-5">
            <button
              type="button"
              :disabled="!canSubmit"
              class="inline-flex w-full items-center justify-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold sm:w-auto"
              :class="canSubmit ? 'border-blue-200 text-blue-700 hover:bg-blue-50' : 'cursor-not-allowed border-slate-200 text-slate-400'"
              @click="visualizarReclamo"
            >
              <ArrowTopRightOnSquareIcon class="h-5 w-5" />
              Visualizar reclamo
            </button>
            <button
              type="submit"
              :disabled="!canSubmit || form.processing"
              class="inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold sm:w-auto"
              :class="canSubmit && !form.processing ? 'bg-blue-600 text-white hover:bg-blue-700' : 'cursor-not-allowed bg-slate-300 text-slate-500'"
            >
              <PaperAirplaneIcon class="h-5 w-5" />
              {{ form.processing ? "Enviando..." : "Enviar reclamo" }}
            </button>
          </div>
        </div>

        <div v-else class="mt-6 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center sm:p-8">
          <p class="text-sm font-semibold text-slate-700">No tienes categorías disponibles para reclamos.</p>
          <p class="mt-1 text-sm text-slate-500">
            Solo aparecen categorías con inscripción confirmada y comprobante de pago aprobado.
          </p>
        </div>
      </form>

      <aside class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <h2 class="text-lg font-semibold text-slate-900">Reclamos enviados</h2>
        <div class="mt-4 space-y-3">
          <article
            v-for="reclamo in reclamos"
            :key="reclamo.id"
            class="rounded-xl border border-slate-200 p-4"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-bold text-slate-900">{{ reclamo.codigo }}</p>
                <p class="mt-1 truncate text-sm text-slate-600">{{ reclamo.categoria }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ reclamo.fecha_envio }}</p>
              </div>
              <span
                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                :class="estadoClasses(reclamo.estado)"
              >
                {{ estadoLabel(reclamo.estado) }}
              </span>
            </div>
            <a
              :href="reclamo.formato_url"
              target="_blank"
              rel="noopener noreferrer"
              class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-blue-700 hover:text-blue-800"
            >
              Ver formato
              <ArrowTopRightOnSquareIcon class="h-4 w-4" />
            </a>
          </article>

          <p v-if="!reclamos.length" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">
            Aún no has enviado reclamos.
          </p>
        </div>
      </aside>
    </section>
  </div>
</template>
