<script setup>
import {
  BellIcon, ChartBarIcon, ClipboardDocumentCheckIcon, DocumentTextIcon,
  ShieldCheckIcon, Square3Stack3DIcon, Squares2X2Icon, TrophyIcon, UsersIcon,
} from '@heroicons/vue/24/outline'
import PanelLayout from '@/components/Panel/PanelLayout.vue'
import CertificateRibbonIcon from '@/components/Admin/CertificateRibbonIcon.vue'
import HistoryIcon from '@/components/Admin/HistoryIcon.vue'

const navigation = [
  { href: '/admin/dashboard', label: 'Dashboard', icon: Squares2X2Icon },
  { href: '/admin/competencias', label: 'Competencias', icon: TrophyIcon },
  { href: '/admin/categorias', label: 'Categorías', icon: Square3Stack3DIcon },
  { href: '/admin/inscripciones', label: 'Inscripciones', icon: ClipboardDocumentCheckIcon },
  { href: '/admin/asignacion-jueces', label: 'Asignación de jueces y gestión de rondas', lines: ['Asignación de jueces', 'y gestión de rondas'], icon: UsersIcon },
  { href: '/admin/notificaciones', label: 'Notificaciones', icon: BellIcon },
  { href: '/admin/resultados', label: 'Resultados', icon: ChartBarIcon },
  { href: '/admin/reportes', label: 'Reportes', icon: DocumentTextIcon },
  { href: '/admin/certificados', label: 'Certificados', icon: CertificateRibbonIcon },
  { href: '/admin/analisis-historico', label: 'Análisis histórico', icon: HistoryIcon },
  { href: '/admin/control-acceso', label: 'Control de acceso', icon: ShieldCheckIcon },
]
</script>

<template>
  <PanelLayout role="admin" role-label="Administrador" section-label="Administración"
    :navigation="navigation" profile-href="/admin/profile"
    notifications-href="/admin/notificaciones?tab=recibidas"
    notifications-counter-url="/admin/notificaciones/contador">
    <slot />
  </PanelLayout>
</template>
