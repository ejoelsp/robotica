<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import {
  ArrowRightIcon, BellIcon, ChartBarIcon, CheckIcon, ClipboardDocumentCheckIcon,
  DocumentTextIcon, ShieldCheckIcon, Square3Stack3DIcon, TrophyIcon, UsersIcon,
} from '@heroicons/vue/24/outline'
import AdminLayout from '@/layouts/AdminLayout.vue'
import DashboardLink from '@/components/Admin/DashboardLink.vue'
import CertificateRibbonIcon from '@/components/Admin/CertificateRibbonIcon.vue'
import HistoryIcon from '@/components/Admin/HistoryIcon.vue'

defineOptions({ layout: AdminLayout })
const props = defineProps({
  competencias: { type: Array, default: () => [] },
  competencia: { type: Object, default: null },
  dashboard: { type: Object, required: true },
})
const loading = ref(false)
const selectionError = ref('')
const links = computed(() => props.dashboard.enlaces)
const counts = computed(() => props.dashboard.indicadores)
const inscriptions = computed(() => props.dashboard.inscripciones)
const pending = computed(() => props.dashboard.pendientes)
const analysis = computed(() => props.dashboard.analisis)
const rounds = computed(() => props.dashboard.rondas)
const number = (value) => new Intl.NumberFormat('es-EC', { maximumFractionDigits: 1 }).format(Number(value) || 0)
const countLabel = (value, one, many) => `${number(value)} ${Number(value) === 1 ? one : many}`
const cards = computed(() => [
  { label: 'Categorías activas', value: number(counts.value.categorias_activas), detail: 'Categorías en esta competencia', icon: Square3Stack3DIcon, tone: 'bg-blue-50' },
  { label: 'Inscripciones', value: number(counts.value.inscripciones), detail: 'Registros en esta competencia', icon: ClipboardDocumentCheckIcon, tone: 'bg-blue-50' },
  { label: 'Jueces activos', value: number(counts.value.jueces_activos), detail: 'Asignados a esta competencia', icon: UsersIcon, tone: 'bg-violet-50' },
  { label: 'Categorías cerradas', value: `${number(analysis.value.categorias_cerradas)} / ${number(analysis.value.categorias_evaluables)}`, detail: 'Con inscripciones aprobadas', icon: CheckIcon, tone: 'bg-emerald-50' },
])
const series = computed(() => {
  const total = inscriptions.value.total
  const circumference = 2 * Math.PI * 76
  let offset = 0
  return [
    { label: 'Aprobadas', count: inscriptions.value.aprobadas, color: '#16a075' },
    { label: 'Pendientes', count: inscriptions.value.pendientes, color: '#f7a33a' },
    { label: 'Rechazadas', count: inscriptions.value.rechazadas, color: '#eb5b6c' },
  ].map((item) => {
    const ratio = total > 0 ? item.count / total : 0
    const segment = { ...item, percentage: ratio * 100, length: ratio * circumference, offset, circumference }
    offset += segment.length
    return segment
  })
})
const tasks = computed(() => [
  { title: countLabel(pending.value.inscripciones, 'inscripción pendiente', 'inscripciones pendientes'), detail: 'Consulta los registros y sus comprobantes.', icon: ClipboardDocumentCheckIcon, href: links.value.inscripciones, action: 'Revisar' },
  { title: countLabel(pending.value.asignaciones, 'categoría con asignación incompleta', 'categorías con asignación incompleta'), detail: 'Completa el equipo de jueces.', icon: UsersIcon, href: links.value.asignaciones, action: 'Asignar jueces' },
  { title: countLabel(pending.value.notificaciones, 'notificación sin leer', 'notificaciones sin leer'), detail: 'Notificaciones recibidas en tu cuenta.', icon: BellIcon, href: links.value.notificaciones, action: 'Ver notificaciones' },
])
const hasPending = computed(() => pending.value.inscripciones + pending.value.asignaciones + pending.value.notificaciones > 0)
const analysisLabel = computed(() => ({ oficial: 'Cierre oficial', listo: 'Listo para cerrar', preliminar: 'Vista preliminar', sin_datos: 'Sin datos evaluables' })[analysis.value.estado] ?? 'Vista preliminar')
const statusLabel = (value) => ({ borrador: 'Borrador', activa: 'Activa', cerrada: 'Cerrada', visible: 'Visible', cerrado: 'Cerrado', mixto: 'Mixto', sin_rondas: 'Sin rondas', sin_consolidar: 'Sin consolidar' })[value] ?? 'Sin información'
const statusClass = (value) => {
  if (['cerrada', 'cerrado'].includes(value)) return 'bg-emerald-50 text-emerald-700'
  if (['activa', 'visible'].includes(value)) return 'bg-blue-50 text-blue-600'
  if (value === 'mixto') return 'bg-amber-50 text-amber-700'
  return 'bg-slate-100 text-slate-500'
}
function changeCompetition(event) {
  const id = Number(event.target.value)
  if (!Number.isInteger(id) || id <= 0 || id === props.competencia?.id) return
  selectionError.value = ''
  router.get('/admin/dashboard', { competencia_id: id }, {
    preserveState: true,
    onStart: () => { loading.value = true },
    onError: () => { selectionError.value = 'No se pudo cambiar de competencia. Inténtalo de nuevo.' },
    onFinish: () => {
      loading.value = false
      event.target.value = String(props.competencia?.id ?? '')
    },
  })
}
</script>

<template>
  <div class="admin-dashboard mx-auto w-full max-w-[1600px] space-y-6 py-2 sm:space-y-7 sm:py-3" :aria-busy="loading">
    <header>
      <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Panel general</h1>
      <p class="mt-2 text-sm text-slate-500 sm:text-base">Una vista integral de la gestión de competencias.</p>
    </header>
    <p v-if="selectionError" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ selectionError }}</p>

    <section v-if="!competencia" class="rounded-2xl border border-slate-200 bg-white px-5 py-12 text-center sm:px-8">
      <TrophyIcon class="mx-auto h-10 w-10 text-slate-400" aria-hidden="true" />
      <h2 class="mt-4 text-lg font-semibold text-slate-900">Todavía no hay competencias registradas</h2>
      <p class="mx-auto mt-2 max-w-lg text-sm text-slate-500">Registra una competencia para consultar sus indicadores, inscripciones y rondas desde este panel.</p>
      <div class="mt-6 flex flex-wrap justify-center gap-3">
        <DashboardLink :href="links.competencias" soft>Gestionar competencias</DashboardLink>
        <DashboardLink :href="links.notificaciones">Ver notificaciones</DashboardLink>
        <DashboardLink :href="links.usuarios">Administrar usuarios</DashboardLink>
      </div>
    </section>

    <template v-else>
      <section aria-label="Competencia seleccionada" class="flex flex-col gap-5 rounded-2xl bg-blue-50 p-5 md:flex-row md:items-center md:justify-between sm:px-6">
        <div class="flex min-w-0 flex-1 items-center gap-4">
          <TrophyIcon class="h-6 w-6 shrink-0 text-slate-500" aria-hidden="true" />
          <div class="min-w-0 flex-1">
            <label for="dashboard-competencia" class="block text-xs font-semibold uppercase tracking-wide text-blue-700">Competencia actual</label>
            <select id="dashboard-competencia" :value="competencia.id" :disabled="loading" class="mt-1 block min-h-10 w-full min-w-0 max-w-xl rounded-lg border border-transparent bg-transparent py-1 pr-3 text-base font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60 sm:text-lg" @change="changeCompetition">
              <option v-for="item in competencias" :key="item.id" :value="item.id">{{ item.nombre }}</option>
            </select>
          </div>
        </div>
        <p class="shrink-0 text-sm text-slate-600">{{ competencia.temporada_nombre || (competencia.anio ? `Temporada ${competencia.anio}` : 'Sin temporada asignada') }}</p>
        <Link :href="links.comite" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-blue-600 hover:bg-blue-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">Comité organizador<ArrowRightIcon class="h-5 w-5" aria-hidden="true" /></Link>
      </section>

      <section aria-label="Indicadores de la competencia" class="grid grid-cols-1 gap-4 min-[420px]:grid-cols-2 xl:grid-cols-4">
        <article v-for="card in cards" :key="card.label" class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-medium text-slate-500">{{ card.label }}</h2>
            <span :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-slate-500', card.tone]"><component :is="card.icon" class="h-6 w-6" aria-hidden="true" /></span>
          </div>
          <p class="mt-5 break-words text-3xl font-semibold tabular-nums text-slate-900 sm:text-4xl">{{ card.value }}</p>
          <p class="mt-4 text-xs leading-5 text-slate-500 sm:text-sm">{{ card.detail }}</p>
        </article>
      </section>

      <div class="grid items-start gap-6 xl:grid-cols-2">
        <section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
          <h2 class="text-lg font-semibold text-slate-900 sm:text-xl">Estado de las inscripciones</h2>
          <p class="mt-2 text-sm text-slate-500">Distribución de los registros de esta competencia.</p>
          <div class="mt-5 flex flex-wrap items-center justify-center gap-6">
            <div class="relative h-48 w-48 shrink-0">
              <svg viewBox="0 0 200 200" class="h-full w-full -rotate-90" role="img" :aria-label="`Estado de ${number(inscriptions.total)} inscripciones: ${number(inscriptions.aprobadas)} aprobadas, ${number(inscriptions.pendientes)} pendientes y ${number(inscriptions.rechazadas)} rechazadas.`">
                <circle class="dashboard-donut-track" cx="100" cy="100" r="76" fill="none" stroke-width="22" />
                <circle v-for="segment in series.filter(item => item.count > 0)" :key="segment.label" cx="100" cy="100" r="76" fill="none" stroke-width="22" :stroke="segment.color" :stroke-dasharray="`${segment.length} ${segment.circumference}`" :stroke-dashoffset="-segment.offset" />
              </svg>
              <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center" aria-hidden="true"><p class="text-3xl font-semibold tabular-nums text-slate-900">{{ number(inscriptions.total) }}</p><p class="mt-1 text-sm text-slate-500">Total</p></div>
            </div>
            <ul class="min-w-[170px] flex-1 space-y-5">
              <li v-for="segment in series" :key="segment.label" class="flex items-center gap-2 text-sm">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: segment.color }" aria-hidden="true" />
                <span class="flex-1 text-slate-600">{{ segment.label }}</span>
                <span class="font-semibold tabular-nums text-slate-900">{{ number(segment.count) }}</span>
                <span class="w-14 text-right text-xs tabular-nums text-slate-500">{{ number(segment.percentage) }} %</span>
              </li>
            </ul>
          </div>
          <p v-if="inscriptions.total === 0" class="mt-4 text-center text-sm text-slate-500">Esta competencia todavía no tiene inscripciones.</p>
          <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="max-w-xs text-sm leading-5 text-slate-500">Consulta los registros y revisa sus comprobantes de pago.</p>
            <DashboardLink :href="links.inscripciones" soft class="shrink-0">Ir a inscripciones</DashboardLink>
          </div>
        </section>

        <section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
          <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-semibold text-slate-900 sm:text-xl">Pendientes de atención</h2><span :class="['rounded-lg px-3 py-1.5 text-xs font-medium', hasPending ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700']">{{ hasPending ? 'Pendiente' : 'Al día' }}</span></div>
          <div class="mt-4 divide-y divide-slate-100">
            <article v-for="task in tasks" :key="task.action" class="flex flex-col gap-4 py-5 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex min-w-0 items-start gap-3"><component :is="task.icon" class="mt-0.5 h-6 w-6 shrink-0 text-slate-500" aria-hidden="true" /><div class="min-w-0"><h3 class="text-sm font-semibold leading-6 text-slate-900">{{ task.title }}</h3><p class="mt-1 text-xs leading-5 text-slate-500">{{ task.detail }}</p></div></div>
              <DashboardLink :href="task.href" class="shrink-0">{{ task.action }}</DashboardLink>
            </article>
          </div>
        </section>
      </div>

      <section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6"><div><h2 class="text-lg font-semibold text-slate-900 sm:text-xl">Seguimiento de categorías y rondas</h2><p class="mt-2 text-sm text-slate-500">La ronda y su publicación se gestionan de forma independiente.</p></div><DashboardLink :href="links.resultados" soft class="shrink-0">Ir a resultados</DashboardLink></div>
        <div class="overflow-x-auto px-5 sm:px-6">
          <table class="w-full min-w-[620px] text-left text-sm">
            <caption class="sr-only">Estado de las rondas y de sus publicaciones en la competencia seleccionada.</caption>
            <thead class="bg-slate-50 text-xs font-medium text-slate-500"><tr><th scope="col" class="rounded-l-xl px-4 py-4">Categoría</th><th scope="col" class="px-4 py-4">Ronda</th><th scope="col" class="px-4 py-4">Estado de ronda</th><th scope="col" class="rounded-r-xl px-4 py-4">Publicación</th></tr></thead>
            <tbody class="divide-y divide-slate-100"><tr v-for="row in rounds.data" :key="`${row.categoria_id}-${row.ronda_id ?? 'sin-rondas'}`"><th scope="row" class="max-w-xs px-4 py-5 font-medium text-slate-900">{{ row.categoria }}</th><td class="px-4 py-5 text-slate-500">{{ row.ronda }}</td><td class="px-4 py-5"><span :class="['inline-flex rounded-lg px-2.5 py-1.5 text-xs font-medium', statusClass(row.estado)]">{{ statusLabel(row.estado) }}</span></td><td class="px-4 py-5"><span :class="['inline-flex rounded-lg px-2.5 py-1.5 text-xs font-medium', statusClass(row.publicacion)]">{{ statusLabel(row.publicacion) }}</span></td></tr><tr v-if="!rounds.data.length"><td colspan="4" class="px-4 py-8 text-center text-slate-500">No hay categorías o rondas para mostrar en esta página.</td></tr></tbody>
          </table>
        </div>
        <div v-if="rounds.last_page > 1" class="flex flex-col gap-3 px-5 pt-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"><p class="text-xs text-slate-500">Página {{ number(rounds.current_page) }} de {{ number(rounds.last_page) }} · {{ number(rounds.total) }} registros</p><nav aria-label="Páginas de categorías y rondas" class="flex gap-2"><DashboardLink :href="rounds.previous" preserve-scroll>Anterior</DashboardLink><DashboardLink :href="rounds.next" preserve-scroll>Siguiente</DashboardLink></nav></div>
        <div class="m-5 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl bg-slate-50 px-4 py-4 text-xs leading-5 text-slate-500 sm:m-6 sm:text-sm"><ChartBarIcon class="h-6 w-6 shrink-0" aria-hidden="true" /><span class="font-semibold text-slate-900">En Resultados</span><span>Registros de evaluación · Clasificaciones · Publicación · En vivo · Auditoría</span></div>
      </section>

      <section aria-labelledby="dashboard-tools-title" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2"><h2 id="dashboard-tools-title" class="text-lg font-semibold text-slate-900 sm:text-xl">Documentación, análisis y seguridad</h2><p class="text-xs text-slate-500">Herramientas de administración</p></div>
        <div class="grid gap-5 md:grid-cols-2">
          <article class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <h3 class="flex items-center gap-3 text-lg font-semibold text-slate-900"><DocumentTextIcon class="h-6 w-6 shrink-0 text-slate-500" aria-hidden="true" />Reportes</h3>
            <p class="mt-4 text-xl font-semibold text-slate-900">{{ countLabel(pending.actas, 'acta por firmar', 'actas por firmar') }}</p>
            <p class="mt-3 text-sm leading-6 text-slate-500">Genera el PDF y carga después el acta firmada.</p>
            <ol aria-label="Proceso de firma del acta" class="my-4 flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 px-3 py-3 text-xs text-slate-700"><li>Generar PDF</li><li aria-hidden="true"><ArrowRightIcon class="h-4 w-4" /></li><li>Firmar acta</li><li aria-hidden="true"><ArrowRightIcon class="h-4 w-4" /></li><li>Cargar firmada</li></ol>
            <DashboardLink :href="links.reportes">Ver reportes</DashboardLink>
          </article>
          <article class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <h3 class="flex items-center gap-3 text-lg font-semibold text-slate-900"><CertificateRibbonIcon class="h-6 w-6 shrink-0 text-slate-500" />Certificados</h3>
            <p class="mt-4 text-xl font-semibold leading-7 text-slate-900">Plantillas de participación y podio</p>
            <p class="mt-3 text-sm leading-6 text-slate-500">Prepara los reconocimientos de la competencia con las plantillas del sistema.</p>
            <div class="mt-4 flex flex-wrap gap-3"><DashboardLink :href="links.plantillas">Gestionar plantillas</DashboardLink><DashboardLink :href="links.certificados_manuales">Generación manual</DashboardLink></div>
          </article>
          <article class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <div class="flex flex-wrap items-center gap-3"><h3 class="flex items-center gap-3 text-lg font-semibold text-slate-900"><HistoryIcon class="h-6 w-6 shrink-0 text-slate-500" />Análisis histórico</h3><span class="text-xs font-medium text-violet-600">{{ analysisLabel }}</span></div>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-2"><p class="text-sm font-semibold text-slate-900">{{ number(analysis.categorias_cerradas) }} de {{ number(analysis.categorias_evaluables) }} categorías evaluables cerradas</p><p class="font-semibold tabular-nums text-violet-600">{{ number(analysis.porcentaje) }} %</p></div>
            <div role="progressbar" aria-label="Avance hacia el cierre de la competencia" :aria-valuenow="analysis.porcentaje" aria-valuemin="0" aria-valuemax="100" class="mt-3 h-2 overflow-hidden rounded-full bg-violet-100"><div class="h-full rounded-full bg-violet-500" :style="{ width: `${Math.min(100, Math.max(0, analysis.porcentaje))}%` }" /></div>
            <p class="mt-4 text-sm leading-6 text-slate-500">El cierre oficial requiere finalizar las rondas de todas las categorías evaluables.</p>
            <p v-if="!analysis.categorias_evaluables" class="mt-2 text-xs text-slate-500">Todavía no hay categorías con inscripciones aprobadas.</p>
            <p class="mt-3 text-xs leading-5 text-violet-600">Por competencia y temporada · Instituciones · Comparativo anual</p>
            <DashboardLink :href="links.analisis" class="mt-4" :title="links.analisis ? undefined : 'Esta competencia no tiene una temporada asignada.'">Ver análisis</DashboardLink>
          </article>
          <article class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <h3 class="flex items-center gap-3 text-lg font-semibold text-slate-900"><ShieldCheckIcon class="h-6 w-6 shrink-0 text-slate-500" aria-hidden="true" />Control de acceso</h3>
            <p class="mt-4 text-xl font-semibold leading-7 text-slate-900">Usuarios, roles y auditoría</p>
            <p class="mt-3 text-sm leading-6 text-slate-500">Administra los usuarios y consulta su actividad en el sistema.</p>
            <p class="my-4 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-500">Administración general del sistema</p>
            <DashboardLink :href="links.usuarios">Administrar usuarios</DashboardLink>
          </article>
        </div>
      </section>
      <p class="text-xs leading-5 text-slate-500">La información corresponde a la competencia seleccionada.</p>
    </template>
  </div>
</template>

<style scoped>
.dashboard-donut-track { stroke: #e2e8f0; }
html[data-admin-theme="dark"] .dashboard-donut-track { stroke: #334155; }
</style>
