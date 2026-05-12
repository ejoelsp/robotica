<script setup>
import AdminLayout from "@/layouts/AdminLayout.vue";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";

import {
  ArrowDownTrayIcon,
  ChevronDownIcon,
  ChartBarIcon,
  PresentationChartLineIcon,
  BuildingLibraryIcon,
  TagIcon,
  ArrowTrendingUpIcon,
  ArrowTrendingDownIcon,
  InformationCircleIcon,
} from "@heroicons/vue/24/outline";

// ============================
//  UI STATE
// ============================
const selectedYear = ref("2025");
const activeTab = ref("participacion"); // participacion | institucion | categorias | proyecciones
const exportOpen = ref(false);

// Cerrar dropdown al click afuera
const onDocClick = (e) => {
  const target = e.target;
  const el = document.getElementById("export-menu");
  const btn = document.getElementById("export-btn");
  if (!exportOpen.value) return;
  if (el?.contains(target) || btn?.contains(target)) return;
  exportOpen.value = false;
};

onMounted(() => document.addEventListener("click", onDocClick));
onBeforeUnmount(() => document.removeEventListener("click", onDocClick));

// ============================
//  MOCK DATA (luego lo conectas al backend)
// ============================
const participationHistory = computed(() => [
  { label: "Ene 2025", participantes: 45, equipos: 12 },
  { label: "Feb 2025", participantes: 52, equipos: 15 },
  { label: "Mar 2025", participantes: 68, equipos: 18 },
  { label: "Abr 2025", participantes: 78, equipos: 21 },
  { label: "May 2025", participantes: 95, equipos: 25 },
  { label: "Jun 2025", participantes: 112, equipos: 28 },
  { label: "Jul 2025", participantes: 125, equipos: 32 },
  { label: "Ago 2025", participantes: 138, equipos: 35 },
  { label: "Sep 2025", participantes: 145, equipos: 38 },
  { label: "Oct 2025", participantes: 158, equipos: 42 },
]);

const institutionPerformance = computed(() => [
  { institucion: "ESPOCH", competencias: 28, primeros: 12, segundos: 8, terceros: 6, total_podios: 26 },
  { institucion: "EPN", competencias: 25, primeros: 10, segundos: 9, terceros: 4, total_podios: 23 },
  { institucion: "UCE", competencias: 22, primeros: 6,  segundos: 8, terceros: 7, total_podios: 21 },
  { institucion: "PUCE", competencias: 20, primeros: 5,  segundos: 6, terceros: 8, total_podios: 19 },
  { institucion: "UTA",  competencias: 18, primeros: 4,  segundos: 5, terceros: 6, total_podios: 15 },
]);


// ============================
//  TAB INSTITUCIÓN (SVG charts)
// ============================
const hoveredInst = ref(null); // índice de institución hover para tooltip (barras)

const instChart = computed(() => {
  const data = institutionPerformance.value;

  const w = 980;
  const h = 360;

  const padL = 48;
  const padR = 20;
  const padT = 20;
  const padB = 50;

  const maxY = Math.max(
    ...data.map((d) => d.primeros),
    ...data.map((d) => d.segundos),
    ...data.map((d) => d.terceros),
    1
  );

  const groups = data.length;
  const usableW = w - padL - padR;
  const groupW = usableW / groups;

  const barW = Math.min(46, Math.max(24, groupW * 0.22));
  const gap = Math.max(10, (groupW - barW * 3) / 4); // gap interno dentro de cada grupo

  const yScale = (v) => {
    const usableH = h - padT - padB;
    const ratio = v / maxY;
    return padT + (usableH - usableH * ratio);
  };

  const baselineY = h - padB;

  const bars = data.map((d, i) => {
    const gx = padL + i * groupW;

    const x1 = gx + gap;
    const x2 = gx + gap * 2 + barW;
    const x3 = gx + gap * 3 + barW * 2;

    const y1 = yScale(d.primeros);
    const y2 = yScale(d.segundos);
    const y3 = yScale(d.terceros);

    return {
      label: d.institucion,
      i,
      v1: d.primeros,
      v2: d.segundos,
      v3: d.terceros,
      rects: [
        { x: x1, y: y1, w: barW, h: baselineY - y1, key: "1" },
        { x: x2, y: y2, w: barW, h: baselineY - y2, key: "2" },
        { x: x3, y: y3, w: barW, h: baselineY - y3, key: "3" },
      ],
      centerX: gx + groupW / 2,
    };
  });

  // líneas de grilla (0, 3, 6, 9, 12 aprox)
  const ticks = 4;
  const grid = Array.from({ length: ticks + 1 }, (_, k) => {
    const v = Math.round((maxY * k) / ticks);
    const y = yScale(v);
    return { v, y };
  });

  return { w, h, padL, padR, padT, padB, baselineY, bars, grid, maxY };
});

const rankingInstituciones = computed(() => {
  // ranking por total podios (igual que tu card)
  return [...institutionPerformance.value]
    .sort((a, b) => (b.total_podios ?? 0) - (a.total_podios ?? 0));
});

const pieChart = computed(() => {
  const data = institutionPerformance.value.map((d) => ({
    label: d.institucion,
    value: d.competencias, // o total_podios si quieres
  }));

  const total = data.reduce((acc, x) => acc + x.value, 0) || 1;

  // Importante: padding para que NO se corten labels
  const size = 340;      // área visible del SVG
  const pad = 160;        // margen extra alrededor del pie
  const vbSize = size + pad * 2; // viewBox más grande

  const cx = vbSize / 2;
  const cy = vbSize / 2;

  const r = 180;         // radio del pie
  const labelR = r + 34; // radio para labels (afuera)

  const colors = ["#2563eb", "#10b981", "#f59e0b", "#8b5cf6", "#ef4444", "#06b6d4"];

  const polar = (radius, angle) => ({
    x: cx + radius * Math.cos(angle),
    y: cy + radius * Math.sin(angle),
  });

  let start = -Math.PI / 2;

  const slices = data.map((d, idx) => {
    const angle = (d.value / total) * Math.PI * 2;
    const end = start + angle;

    const p1 = polar(r, start);
    const p2 = polar(r, end);

    const largeArc = angle > Math.PI ? 1 : 0;

    const path = [
      `M ${cx} ${cy}`,
      `L ${p1.x} ${p1.y}`,
      `A ${r} ${r} 0 ${largeArc} 1 ${p2.x} ${p2.y}`,
      "Z",
    ].join(" ");

    // Label: calculado con radio labelR (afuera)
    const mid = (start + end) / 2;
    const lp = polar(labelR, mid);

    // Anchor según lado
    const anchor = Math.cos(mid) >= 0 ? "start" : "end";

    // Pequeño “empuje” horizontal para separarlo del borde
    const offsetX = Math.cos(mid) >= 0 ? 8 : -8;

    const slice = {
      label: d.label,
      value: d.value,
      color: colors[idx % colors.length],
      path,
      lx: lp.x + offsetX,
      ly: lp.y,
      anchor,
    };

    start = end;
    return slice;
  });

  return {
    // el svg real se dibuja con viewBox grande,
    // pero se muestra en un tamaño normal (size)
    size,
    viewBox: `0 0 ${vbSize} ${vbSize}`,
    slices,
  };
});



const categoryDistribution = computed(() => [
  { nombre: "Seguidor de Línea", porcentaje: 35, participantes: 245, tiempo_promedio: 13.5, mejor_tiempo: 12.1 },
  { nombre: "Sumo",             porcentaje: 28, participantes: 196, tiempo_promedio: 18.2, mejor_tiempo: 14.9 },
  { nombre: "Laberinto",        porcentaje: 22, participantes: 154, tiempo_promedio: 48.3, mejor_tiempo: 42.8 },
  { nombre: "Velocista",        porcentaje: 18, participantes: 126, tiempo_promedio: 8.7,  mejor_tiempo: 7.2  },
  { nombre: "Rescate",          porcentaje: 12, participantes: 84,  tiempo_promedio: 33.1, mejor_tiempo: 28.4 },
  { nombre: "Evasión",          porcentaje: 10, participantes: 70,  tiempo_promedio: 21.6, mejor_tiempo: 18.9 },
]);

const growthProjection = computed(() => [
  { anio: "2022", real: 450,  proyectado: null },
  { anio: "2023", real: 680,  proyectado: null },
  { anio: "2024", real: 920,  proyectado: null },
  { anio: "2025", real: 1248, proyectado: 1248 },
  { anio: "2026", real: null, proyectado: 1580 },
  { anio: "2027", real: null, proyectado: 1950 },
  { anio: "2028", real: null, proyectado: 2380 },
]);

// ============================
//  STATS (cards superiores)
// ============================
const totalHistorico = computed(() => 1248);
const promedioPorCompetencia = computed(() => 104);
const instituciones = computed(() => 28);
const tasaCrecimiento = computed(() => 42.8);

const stats = computed(() => ([
  {
    title: "Total Participantes Histórico",
    value: totalHistorico.value.toLocaleString("es-EC"),
    change: "+36.2%",
    trend: "up",
    iconBg: "bg-blue-50",
    iconColor: "text-blue-600",
    icon: PresentationChartLineIcon,
  },
  {
    title: "Promedio por Competencia",
    value: String(promedioPorCompetencia.value),
    change: "+18.5%",
    trend: "up",
    iconBg: "bg-blue-50",
    iconColor: "text-blue-600",
    icon: ChartBarIcon,
  },
  {
    title: "Instituciones Participantes",
    value: String(instituciones.value),
    change: "+12 nuevas",
    trend: "up",
    iconBg: "bg-blue-50",
    iconColor: "text-blue-600",
    icon: BuildingLibraryIcon,
  },
  {
    title: "Tasa de Crecimiento Anual",
    value: `${tasaCrecimiento.value.toFixed(1)}%`,
    change: "Proyectado",
    trend: "up",
    iconBg: "bg-blue-50",
    iconColor: "text-blue-600",
    icon: ArrowTrendingUpIcon,
  },
]));

// ============================
//  CHART (SVG, sin librerías)
//  - 2 áreas: participantes (azul) y equipos (verde)
// ============================
const hoveredIndex = ref(null);

const chart = computed(() => {
  const data = participationHistory.value;

  const w = 980;
  const h = 320;
  const padL = 46;
  const padR = 18;
  const padT = 16;
  const padB = 44;

  const maxY = Math.max(
    ...data.map((d) => d.participantes),
    ...data.map((d) => d.equipos)
  );

  const xStep = (w - padL - padR) / Math.max(data.length - 1, 1);
  const yScale = (val) => {
    const usableH = h - padT - padB;
    const ratio = maxY === 0 ? 0 : val / maxY;
    return padT + (usableH - usableH * ratio);
  };
  const xAt = (i) => padL + i * xStep;

  const points = data.map((d, i) => ({
    x: xAt(i),
    yP: yScale(d.participantes),
    yE: yScale(d.equipos),
    label: d.label,
    participantes: d.participantes,
    equipos: d.equipos,
  }));

  const linePath = (key) => points.map((p, i) => `${i === 0 ? "M" : "L"} ${p.x} ${key === "P" ? p.yP : p.yE}`).join(" ");
  const areaPath = (key) => {
    const top = linePath(key);
    const last = points[points.length - 1];
    const first = points[0];
    const y0 = h - padB;
    return `${top} L ${last.x} ${y0} L ${first.x} ${y0} Z`;
  };

  return { w, h, padL, padR, padT, padB, points, lineP: linePath("P"), lineE: linePath("E"), areaP: areaPath("P"), areaE: areaPath("E") };
});

// ============================
//  EXPORTS (frontend-only stubs)
// ============================
function downloadBlob(filename, mime, content) {
  const blob = new Blob([content], { type: mime });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

function exportCSV() {
  // Ejemplo: exportar participación (puedes cambiar el dataset luego)
  const rows = participationHistory.value.map((d) => ({
    periodo: d.label,
    participantes: d.participantes,
    equipos: d.equipos,
  }));

  const header = Object.keys(rows[0]).join(",");
  const body = rows.map((r) => Object.values(r).join(",")).join("\n");
  const csv = `${header}\n${body}\n`;

  downloadBlob(`informe_analisis_${selectedYear.value}.csv`, "text/csv;charset=utf-8", csv);
  exportOpen.value = false;
}

function exportJSON() {
  const payload = {
    year: selectedYear.value,
    stats: {
      total_historico: totalHistorico.value,
      promedio_por_competencia: promedioPorCompetencia.value,
      instituciones: instituciones.value,
      tasa_crecimiento: tasaCrecimiento.value,
    },
    participationHistory: participationHistory.value,
    institutionPerformance: institutionPerformance.value,
    categoryDistribution: categoryDistribution.value,
    growthProjection: growthProjection.value,
  };

  downloadBlob(`informe_analisis_${selectedYear.value}.json`, "application/json;charset=utf-8", JSON.stringify(payload, null, 2));
  exportOpen.value = false;
}

function exportExcelCSV() {
  // “Excel” sin librerías: CSV con BOM + nombre .xls (Excel lo abre OK en la práctica)
  const rows = participationHistory.value.map((d) => ({
    Periodo: d.label,
    Participantes: d.participantes,
    Equipos: d.equipos,
  }));

  const header = Object.keys(rows[0]).join(";");
  const body = rows.map((r) => Object.values(r).join(";")).join("\n");
  const bom = "\uFEFF";
  const csv = `${bom}${header}\n${body}\n`;

  downloadBlob(`informe_analisis_${selectedYear.value}.xls`, "application/vnd.ms-excel;charset=utf-8", csv);
  exportOpen.value = false;
}

function exportPDFPrint() {
  // Sin dependencias: imprime la vista (ideal: luego haces endpoint real para PDF)
  exportOpen.value = false;
  window.print();
}

defineOptions({ layout: AdminLayout });
</script>

<template>
  <div class="w-full">
    <!-- Contenedor como tu módulo de Categorías (max width + spacing) -->
    <div class="mx-auto w-full max-w-[1180px] px-4 sm:px-6 lg:px-4 py-6 space-y-6">
      <!-- Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">Análisis Histórico y Proyecciones</h1>
          <p class="text-sm text-slate-500">Estadísticas, tendencias y análisis predictivo de competencias</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
          <!-- Year select -->
          <div class="relative w-full sm:w-[140px]">
            <select
              v-model="selectedYear"
              class="w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="2025">2025</option>
              <option value="2024">2024</option>
              <option value="2023">2023</option>
              <option value="all">Todos</option>
            </select>
          </div>

          <!-- Export dropdown -->
          <div class="relative w-full sm:w-auto">
            <button
              id="export-btn"
              type="button"
              @click="exportOpen = !exportOpen"
              class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 transition w-full sm:w-auto"
            >
              <ArrowDownTrayIcon class="w-5 h-5 text-slate-700" />
              <span class="text-sm font-medium text-slate-900">Exportar informe</span>
              <ChevronDownIcon class="w-4 h-4 text-slate-600" />
            </button>

            <div
              v-if="exportOpen"
              id="export-menu"
              class="absolute right-0 mt-2 w-full sm:w-[240px] rounded-2xl border border-slate-200 bg-white shadow-xl overflow-hidden z-50"
            >
              <div class="p-2">
                <button
                  type="button"
                  @click="exportPDFPrint"
                  class="w-full flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-slate-50 transition text-left"
                >
                  <span class="h-8 w-8 rounded-xl bg-slate-100 flex items-center justify-center text-slate-700">PDF</span>
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-900">PDF</p>
                    <p class="text-xs text-slate-500">Imprimir / Guardar como PDF</p>
                  </div>
                </button>

                <button
                  type="button"
                  @click="exportExcelCSV"
                  class="w-full flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-slate-50 transition text-left"
                >
                  <span class="h-8 w-8 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-700">XLS</span>
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-900">Excel</p>
                    <p class="text-xs text-slate-500">CSV compatible para Excel</p>
                  </div>
                </button>

                <button
                  type="button"
                  @click="exportCSV"
                  class="w-full flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-slate-50 transition text-left"
                >
                  <span class="h-8 w-8 rounded-xl bg-blue-50 flex items-center justify-center text-blue-700">CSV</span>
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-900">CSV</p>
                    <p class="text-xs text-slate-500">Datos tabulares</p>
                  </div>
                </button>

                <button
                  type="button"
                  @click="exportJSON"
                  class="w-full flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-slate-50 transition text-left"
                >
                  <span class="h-8 w-8 rounded-xl bg-violet-50 flex items-center justify-center text-violet-700">JSON</span>
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-900">JSON</p>
                    <p class="text-xs text-slate-500">Ideal para integraciones</p>
                  </div>
                </button>
              </div>

              <div class="px-3 pb-3">
                <div class="flex items-start gap-2 rounded-xl bg-slate-50 border border-slate-200 p-3">
                  <InformationCircleIcon class="w-5 h-5 text-slate-600 mt-0.5" />
                  <p class="text-xs text-slate-600 leading-relaxed">
                    Por ahora es exportación <b>frontend-only</b> (mock). Luego conectas a tu backend para PDF/Excel reales.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Stats cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div
          v-for="(s, idx) in stats"
          :key="idx"
          class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="h-10 w-10 rounded-xl flex items-center justify-center" :class="s.iconBg">
              <component :is="s.icon" class="w-5 h-5" :class="s.iconColor" />
            </div>

            <component
              :is="s.trend === 'up' ? ArrowTrendingUpIcon : ArrowTrendingDownIcon"
              class="w-5 h-5"
              :class="s.trend === 'up' ? 'text-emerald-600' : 'text-red-600'"
            />
          </div>

          <p class="text-3xl font-semibold text-slate-900 mt-4">{{ s.value }}</p>
          <p class="text-sm text-slate-600 mt-1">{{ s.title }}</p>
          <p class="text-xs mt-2" :class="s.trend === 'up' ? 'text-emerald-600' : 'text-red-600'">
            {{ s.change }}
          </p>
        </div>
      </div>

      <!-- Tabs (pill style) -->
      <div class="flex flex-wrap gap-2">
        <button
          type="button"
          @click="activeTab = 'participacion'"
          class="px-4 py-2 rounded-full text-sm font-medium border transition"
          :class="activeTab === 'participacion'
            ? 'bg-white border-slate-200 text-slate-900 shadow-sm'
            : 'bg-slate-100 border-slate-200 text-slate-700 hover:bg-slate-50'"
        >
          Participación
        </button>

        <button
          type="button"
          @click="activeTab = 'institucion'"
          class="px-4 py-2 rounded-full text-sm font-medium border transition"
          :class="activeTab === 'institucion'
            ? 'bg-white border-slate-200 text-slate-900 shadow-sm'
            : 'bg-slate-100 border-slate-200 text-slate-700 hover:bg-slate-50'"
        >
          Por Institución
        </button>

        <button
          type="button"
          @click="activeTab = 'categorias'"
          class="px-4 py-2 rounded-full text-sm font-medium border transition"
          :class="activeTab === 'categorias'
            ? 'bg-white border-slate-200 text-slate-900 shadow-sm'
            : 'bg-slate-100 border-slate-200 text-slate-700 hover:bg-slate-50'"
        >
          Categorías
        </button>

        <button
          type="button"
          @click="activeTab = 'proyecciones'"
          class="px-4 py-2 rounded-full text-sm font-medium border transition"
          :class="activeTab === 'proyecciones'
            ? 'bg-white border-slate-200 text-slate-900 shadow-sm'
            : 'bg-slate-100 border-slate-200 text-slate-700 hover:bg-slate-50'"
        >
          Proyecciones
        </button>
      </div>

      <!-- ==========================
           TAB: Participación
      =========================== -->
      <div v-if="activeTab === 'participacion'" class="space-y-6">
        <!-- Chart Card -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <div class="p-5 sm:p-6 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-900">Evolución de Participación {{ selectedYear === 'all' ? '' : selectedYear }}</h2>
          </div>

          <div class="p-5 sm:p-6">
            <div class="w-full overflow-x-auto">
              <div class="min-w-[980px]">
                <svg :width="chart.w" :height="chart.h" class="block">
                  <defs>
                    <linearGradient id="gradP" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stop-color="#2563eb" stop-opacity="0.28" />
                      <stop offset="100%" stop-color="#2563eb" stop-opacity="0" />
                    </linearGradient>
                    <linearGradient id="gradE" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stop-color="#10b981" stop-opacity="0.25" />
                      <stop offset="100%" stop-color="#10b981" stop-opacity="0" />
                    </linearGradient>
                  </defs>

                  <!-- grid -->
                  <g>
                    <line
                      v-for="i in 4"
                      :key="i"
                      :x1="chart.padL"
                      :x2="chart.w - chart.padR"
                      :y1="(chart.padT + ((chart.h - chart.padT - chart.padB) * i) / 4)"
                      :y2="(chart.padT + ((chart.h - chart.padT - chart.padB) * i) / 4)"
                      stroke="#e2e8f0"
                      stroke-dasharray="3 4"
                    />
                  </g>

                  <!-- areas -->
                  <path :d="chart.areaP" fill="url(#gradP)" />
                  <path :d="chart.areaE" fill="url(#gradE)" />

                  <!-- lines -->
                  <path :d="chart.lineP" fill="none" stroke="#2563eb" stroke-width="2.5" />
                  <path :d="chart.lineE" fill="none" stroke="#10b981" stroke-width="2.5" />

                  <!-- points + tooltip -->
                  <g v-for="(p, i) in chart.points" :key="i">
                    <circle
                      :cx="p.x"
                      :cy="p.yP"
                      r="5"
                      fill="#2563eb"
                      stroke="white"
                      stroke-width="2"
                      class="cursor-pointer"
                      @mouseenter="hoveredIndex = i"
                      @mouseleave="hoveredIndex = null"
                    />
                    <circle
                      :cx="p.x"
                      :cy="p.yE"
                      r="5"
                      fill="#10b981"
                      stroke="white"
                      stroke-width="2"
                      class="cursor-pointer"
                      @mouseenter="hoveredIndex = i"
                      @mouseleave="hoveredIndex = null"
                    />
                  </g>

                  <!-- labels eje X -->
                  <g v-for="(p, i) in chart.points" :key="'lbl'+i">
                    <text
                      :x="p.x"
                      :y="chart.h - 18"
                      text-anchor="middle"
                      font-size="12"
                      fill="#64748b"
                    >
                      {{ p.label.split(" ")[0] }}
                    </text>
                  </g>

                  <!-- Tooltip box (simple, tipo screenshot) -->
                  <g v-if="hoveredIndex !== null">
                    <rect
                      :x="Math.min(chart.points[hoveredIndex].x + 12, chart.w - 210)"
                      :y="Math.max(chart.points[hoveredIndex].yP - 80, 20)"
                      width="190"
                      height="70"
                      rx="12"
                      fill="white"
                      stroke="#e2e8f0"
                    />
                    <text
                      :x="Math.min(chart.points[hoveredIndex].x + 24, chart.w - 198)"
                      :y="Math.max(chart.points[hoveredIndex].yP - 52, 44)"
                      font-size="13"
                      fill="#0f172a"
                      font-weight="600"
                    >
                      {{ chart.points[hoveredIndex].label }}
                    </text>

                    <text
                      :x="Math.min(chart.points[hoveredIndex].x + 24, chart.w - 198)"
                      :y="Math.max(chart.points[hoveredIndex].yP - 30, 66)"
                      font-size="12"
                      fill="#10b981"
                      font-weight="600"
                    >
                      Equipos: {{ chart.points[hoveredIndex].equipos }}
                    </text>

                    <text
                      :x="Math.min(chart.points[hoveredIndex].x + 24, chart.w - 198)"
                      :y="Math.max(chart.points[hoveredIndex].yP - 12, 84)"
                      font-size="12"
                      fill="#2563eb"
                      font-weight="600"
                    >
                      Participantes: {{ chart.points[hoveredIndex].participantes }}
                    </text>
                  </g>
                </svg>

                <!-- Legend -->
                <div class="flex items-center justify-center gap-6 mt-3 text-sm">
                  <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                    <span class="text-slate-600">Equipos</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>
                    <span class="text-slate-600">Participantes</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Cards inferiores tipo screenshot -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div
            v-for="(c, idx) in categoryDistribution.slice(0, 3)"
            :key="idx"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
          >
            <h3 class="text-base font-semibold text-slate-900">{{ c.nombre }}</h3>

            <div class="mt-4 space-y-3">
              <div>
                <p class="text-sm text-slate-600">Tiempo Promedio</p>
                <p class="text-slate-900 font-semibold">{{ c.tiempo_promedio }}s</p>
              </div>
              <div>
                <p class="text-sm text-slate-600">Mejor Tiempo</p>
                <p class="text-emerald-600 font-semibold">{{ c.mejor_tiempo }}s</p>
              </div>
              <div>
                <p class="text-sm text-slate-600">Total Participantes</p>
                <p class="text-blue-600 font-semibold">{{ c.participantes }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ==========================
        TAB: Por Institución
        =========================== -->
        <div v-if="activeTab === 'institucion'" class="space-y-6">
        <!-- Card superior: Barras -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-slate-900">Rendimiento Histórico por Institución</h2>
            </div>

            <div class="px-5 sm:px-6 pb-6">
            <div class="w-full overflow-x-auto">
                <div class="min-w-[980px] relative">
                <svg :width="instChart.w" :height="instChart.h" class="block">
                    <!-- grid -->
                    <g>
                    <line
                        v-for="(g, idx) in instChart.grid"
                        :key="'g'+idx"
                        :x1="instChart.padL"
                        :x2="instChart.w - instChart.padR"
                        :y1="g.y"
                        :y2="g.y"
                        stroke="#e2e8f0"
                        stroke-dasharray="3 4"
                    />
                    <!-- axis labels -->
                    <text
                        v-for="(g, idx) in instChart.grid"
                        :key="'t'+idx"
                        :x="instChart.padL - 8"
                        :y="g.y + 4"
                        text-anchor="end"
                        font-size="12"
                        fill="#64748b"
                    >
                        {{ g.v }}
                    </text>
                    </g>

                    <!-- bars -->
                    <g v-for="b in instChart.bars" :key="b.label">
                    <!-- 1° -->
                    <rect
                        :x="b.rects[0].x"
                        :y="b.rects[0].y"
                        :width="b.rects[0].w"
                        :height="b.rects[0].h"
                        rx="6"
                        fill="#f59e0b"
                        class="cursor-pointer"
                        @mouseenter="hoveredInst = b.i"
                        @mouseleave="hoveredInst = null"
                    />
                    <!-- 2° -->
                    <rect
                        :x="b.rects[1].x"
                        :y="b.rects[1].y"
                        :width="b.rects[1].w"
                        :height="b.rects[1].h"
                        rx="6"
                        fill="#94a3b8"
                        class="cursor-pointer"
                        @mouseenter="hoveredInst = b.i"
                        @mouseleave="hoveredInst = null"
                    />
                    <!-- 3° -->
                    <rect
                        :x="b.rects[2].x"
                        :y="b.rects[2].y"
                        :width="b.rects[2].w"
                        :height="b.rects[2].h"
                        rx="6"
                        fill="#c0843a"
                        class="cursor-pointer"
                        @mouseenter="hoveredInst = b.i"
                        @mouseleave="hoveredInst = null"
                    />

                    <!-- x labels -->
                    <text
                        :x="b.centerX"
                        :y="instChart.h - 18"
                        text-anchor="middle"
                        font-size="12"
                        fill="#64748b"
                    >
                        {{ b.label }}
                    </text>
                    </g>

                    <!-- tooltip tipo imagen -->
                    <g v-if="hoveredInst !== null">
                    <template v-for="b in instChart.bars" :key="'tip'+b.i">
                        <g v-if="b.i === hoveredInst">
                        <rect
                            :x="Math.min(b.centerX - 85, instChart.w - 210)"
                            :y="90"
                            width="190"
                            height="110"
                            rx="12"
                            fill="white"
                            stroke="#e2e8f0"
                        />
                        <text :x="Math.min(b.centerX - 65, instChart.w - 190)" y="118" font-size="13" fill="#0f172a" font-weight="700">
                            {{ b.label }}
                        </text>

                        <text :x="Math.min(b.centerX - 65, instChart.w - 190)" y="146" font-size="12" fill="#f59e0b" font-weight="700">
                            1° Lugar : {{ b.v1 }}
                        </text>
                        <text :x="Math.min(b.centerX - 65, instChart.w - 190)" y="170" font-size="12" fill="#94a3b8" font-weight="700">
                            2° Lugar : {{ b.v2 }}
                        </text>
                        <text :x="Math.min(b.centerX - 65, instChart.w - 190)" y="194" font-size="12" fill="#c0843a" font-weight="700">
                            3° Lugar : {{ b.v3 }}
                        </text>
                        </g>
                    </template>
                    </g>
                </svg>

                <!-- legend (igual a la imagen) -->
                <div class="flex items-center justify-center gap-6 mt-3 text-sm">
                    <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-sm" style="background:#f59e0b"></span>
                    <span class="text-slate-600">1° Lugar</span>
                    </div>
                    <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-sm" style="background:#94a3b8"></span>
                    <span class="text-slate-600">2° Lugar</span>
                    </div>
                    <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-sm" style="background:#c0843a"></span>
                    <span class="text-slate-600">3° Lugar</span>
                    </div>
                </div>
                </div>
            </div>
            </div>
        </div>

        <!-- Abajo: ranking + pie -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- Ranking (izq) -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-900">Ranking de Instituciones</h3>

            <div class="mt-5 space-y-3">
                <div
                v-for="(inst, idx) in rankingInstituciones"
                :key="inst.institucion"
                class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 border border-slate-200"
                >
                <div class="flex items-center gap-3">
                    <div
                    class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold"
                    :class="idx === 0 ? 'bg-yellow-100 text-yellow-700'
                        : idx === 1 ? 'bg-slate-100 text-slate-700'
                        : idx === 2 ? 'bg-orange-100 text-orange-700'
                        : 'bg-blue-100 text-blue-700'"
                    >
                    {{ idx + 1 }}
                    </div>

                    <div>
                    <p class="font-semibold text-slate-900">{{ inst.institucion }}</p>
                    <p class="text-xs text-slate-600">{{ inst.total_podios }} podios totales</p>
                    </div>
                </div>

                <div class="text-right">
                    <p class="font-semibold text-slate-900">{{ inst.primeros }}</p>
                    <p class="text-xs text-slate-600">victorias</p>
                </div>
                </div>
            </div>
            </div>

            <!-- Pie chart (der) -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-900">Participación por Institución</h3>

            <div class="mt-6 flex items-center justify-center">
                <svg
                    :width="pieChart.size"
                    :height="pieChart.size"
                    :viewBox="pieChart.viewBox"
                    class="block"
                >

                <g v-for="(s, idx) in pieChart.slices" :key="idx">
                    <path :d="s.path" :fill="s.color" stroke="white" stroke-width="2" />
                    <text
                    :x="s.lx"
                    :y="s.ly"
                    :text-anchor="s.anchor"
                    font-size="25"
                    :fill="s.color"
                    font-weight="700"
                    >
                    {{ s.label }}: {{ s.value }}
                    </text>
                </g>
                </svg>
            </div>
            </div>
        </div>
        </div>


      <!-- ==========================
           TAB: Categorías
      =========================== -->
      <div v-if="activeTab === 'categorias'" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
              <div>
                <h3 class="text-lg font-semibold text-slate-900">Distribución por Categorías</h3>
                <p class="text-sm text-slate-500 mt-1">Popularidad estimada (mock)</p>
              </div>
              <div class="h-10 w-10 rounded-xl bg-blue-50 flex items-center justify-center">
                <TagIcon class="w-5 h-5 text-blue-600" />
              </div>
            </div>

            <div class="mt-5 space-y-3">
              <div
                v-for="(c, idx) in categoryDistribution"
                :key="idx"
                class="rounded-xl border border-slate-200 p-4"
              >
                <div class="flex items-center justify-between gap-3">
                  <p class="font-semibold text-slate-900">{{ c.nombre }}</p>
                  <span class="text-sm font-semibold text-blue-600">{{ c.porcentaje }}%</span>
                </div>

                <div class="mt-3 w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                  <div class="h-2 rounded-full bg-blue-600" :style="{ width: `${c.porcentaje}%` }"></div>
                </div>

                <div class="mt-3 flex items-center justify-between text-sm">
                  <span class="text-slate-600">Participantes</span>
                  <span class="text-emerald-600 font-semibold">{{ c.participantes }}</span>
                </div>
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
              <div>
                <h3 class="text-lg font-semibold text-slate-900">Indicadores por Categoría</h3>
                <p class="text-sm text-slate-500 mt-1">Tiempos y participación (mock)</p>
              </div>
              <div class="h-10 w-10 rounded-xl bg-emerald-50 flex items-center justify-center">
                <ChartBarIcon class="w-5 h-5 text-emerald-600" />
              </div>
            </div>

            <div class="mt-5 space-y-3">
              <div
                v-for="(c, idx) in categoryDistribution.slice(0, 4)"
                :key="idx"
                class="flex items-center justify-between p-4 rounded-xl bg-slate-50 border border-slate-200"
              >
                <div>
                  <p class="font-semibold text-slate-900">{{ c.nombre }}</p>
                  <p class="text-xs text-slate-600 mt-1">
                    Prom: {{ c.tiempo_promedio }}s · Mejor: {{ c.mejor_tiempo }}s
                  </p>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ring-1 bg-blue-50 text-blue-700 ring-blue-200">
                  {{ c.participantes }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ==========================
           TAB: Proyecciones
      =========================== -->
      <div v-if="activeTab === 'proyecciones'" class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <div class="p-5 sm:p-6 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-900">Proyección de Crecimiento (2022–2028)</h2>
          </div>

          <div class="p-5 sm:p-6 overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-white">
                <tr class="text-left text-black border-b border-slate-200">
                  <th class="px-4 py-3 font-medium">Año</th>
                  <th class="px-4 py-3 font-medium">Real</th>
                  <th class="px-4 py-3 font-medium">Proyectado</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-slate-200">
                <tr v-for="(g, idx) in growthProjection" :key="idx" class="hover:bg-slate-50/60">
                  <td class="px-4 py-3 font-semibold text-slate-900">{{ g.anio }}</td>
                  <td class="px-4 py-3 text-slate-700">
                    <span v-if="g.real !== null">{{ g.real.toLocaleString("es-EC") }}</span>
                    <span v-else class="text-slate-400">—</span>
                  </td>
                  <td class="px-4 py-3 text-slate-700">
                    <span v-if="g.proyectado !== null" class="text-amber-700 font-semibold">{{ g.proyectado.toLocaleString("es-EC") }}</span>
                    <span v-else class="text-slate-400">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-blue-50 to-blue-100 p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Proyección 2026</h3>
            <p class="text-3xl font-semibold text-blue-700 mt-2">1,580</p>
            <p class="text-sm text-slate-600 mt-1">Participantes esperados</p>
            <p class="text-xs text-emerald-700 mt-2">+26.6% vs 2025</p>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-violet-50 to-violet-100 p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Proyección 2027</h3>
            <p class="text-3xl font-semibold text-violet-700 mt-2">1,950</p>
            <p class="text-sm text-slate-600 mt-1">Participantes esperados</p>
            <p class="text-xs text-emerald-700 mt-2">+23.4% vs 2026</p>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-amber-50 to-amber-100 p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Proyección 2028</h3>
            <p class="text-3xl font-semibold text-amber-700 mt-2">2,380</p>
            <p class="text-sm text-slate-600 mt-1">Participantes esperados</p>
            <p class="text-xs text-emerald-700 mt-2">+22.1% vs 2027</p>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h3 class="text-lg font-semibold text-slate-900">Factores y Recomendaciones</h3>

          <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
              <p class="font-semibold text-emerald-900 mb-2">Factores positivos</p>
              <ul class="text-sm text-emerald-800 space-y-2">
                <li>• Incremento de instituciones participantes</li>
                <li>• Mayor difusión (redes/medios)</li>
                <li>• Nuevas categorías y formatos</li>
                <li>• Mejora de infraestructura</li>
              </ul>
            </div>

            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
              <p class="font-semibold text-blue-900 mb-2">Recomendaciones</p>
              <ul class="text-sm text-blue-800 space-y-2">
                <li>• Expandir alcance a más provincias</li>
                <li>• Alianzas con empresas tecnológicas</li>
                <li>• Competencias híbridas/virtuales</li>
                <li>• Programas de capacitación continua</li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- Print styles (PDF print) -->
      <div class="hidden print:block">
        <p class="text-xs text-slate-500 mt-4">
          Informe generado desde el módulo “Análisis Histórico y Proyecciones”.
        </p>
      </div>
    </div>
  </div>
</template>

<style>
/* Export PDF (print) */
@media print {
  body {
    background: white !important;
  }
  #export-btn,
  #export-menu {
    display: none !important;
  }
}
</style>
