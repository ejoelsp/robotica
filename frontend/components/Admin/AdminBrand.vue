<script setup>
defineProps({ titleId: { type: String, default: undefined } })
</script>

<template>
  <div class="flex min-w-0 items-center gap-3">
    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-slate-500">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6" aria-hidden="true">
        <rect x="5" y="7" width="14" height="13" rx="4" />
        <path d="M12 3v4M2 11v5m20-5v5M9 16h6" />
        <circle cx="9" cy="11.5" r="0.75" fill="currentColor" stroke="none" />
        <circle cx="15" cy="11.5" r="0.75" fill="currentColor" stroke="none" />
      </svg>
    </div>
    <div class="min-w-0">
      <p :id="titleId" class="truncate text-sm font-semibold leading-5 text-slate-900">Club Robótica</p>
      <p class="text-[11px] font-medium leading-4 text-blue-600">ESPOCH</p>
    </div>
  </div>
</template>
