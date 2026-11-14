<script setup>
import { BellIcon, ChartBarIcon, ClipboardDocumentCheckIcon, DocumentTextIcon } from '@heroicons/vue/24/outline'
import PanelLayout from '@/components/Panel/PanelLayout.vue'
import CertificateRibbonIcon from '@/components/Admin/CertificateRibbonIcon.vue'

const navigation = [
  { href: '/competidor/mis-inscripciones', label: 'Mis inscripciones', icon: ClipboardDocumentCheckIcon },
  { href: '/competidor/notificaciones', label: 'Notificaciones', icon: BellIcon },
  { href: '/competidor/resultados', label: 'Resultados', icon: ChartBarIcon },
  { href: '/competidor/certificados', label: 'Certificados', icon: CertificateRibbonIcon },
  { href: '/competidor/reclamos', label: 'Reclamos', icon: DocumentTextIcon },
]
</script>

<template>
  <PanelLayout role="competidor" role-label="Competidor" section-label="Competidor"
    :navigation="navigation" profile-href="/profile"
    notifications-href="/competidor/notificaciones"
    notifications-counter-url="/competidor/notificaciones/contador">
    <slot />
  </PanelLayout>
</template>
