<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  href: { type: String, default: null },
  soft: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  preserveScroll: { type: Boolean, default: false },
})

const base = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border px-4 py-2.5 text-center text-sm font-semibold leading-5 text-blue-600 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2'
</script>

<template>
  <Link v-if="href && !disabled" :href="href" :preserve-scroll="preserveScroll" :class="[base, soft ? 'border-transparent bg-blue-50 hover:bg-blue-100' : 'border-slate-200 bg-white hover:bg-blue-50']"><slot /></Link>
  <button v-else type="button" disabled :class="[base, 'cursor-not-allowed border-slate-200 bg-slate-50 opacity-50']"><slot /></button>
</template>
