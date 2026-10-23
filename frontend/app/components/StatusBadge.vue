<script setup lang="ts">
import type { DealStatus } from '~/utils/types'
import { statusTone } from '~/utils/pipeline'

defineProps<{ status: DealStatus; label: string }>()
</script>

<template>
  <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset" :class="statusTone[status]">{{ label }}</span>
</template>
