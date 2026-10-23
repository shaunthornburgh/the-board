<script setup lang="ts">
import type { AnalystVerdict } from '~/utils/types'
import { verdictTone } from '~/utils/pipeline'

defineProps<{ verdict: AnalystVerdict; label?: string | null }>()
</script>

<template>
  <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset" :class="verdictTone[verdict]">{{ label || verdict }}</span>
</template>
