<script setup lang="ts">
import type { Teaser } from '~/utils/types'

defineProps<{ deal: Teaser; locked?: boolean }>()
</script>

<template>
  <NuxtLink
    :to="`/deals/${deal.id}`"
    class="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:shadow-lg"
  >
    <div class="relative aspect-[4/3] overflow-hidden">
      <img :src="deal.photo_url" :alt="`Building in ${deal.town}`" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-105" />
      <div class="absolute inset-x-0 top-0 flex items-center gap-2 p-3">
        <SampleBadge v-if="deal.is_sample" class="bg-white/90" />
      </div>
    </div>
    <div class="flex flex-1 flex-col gap-3 p-5">
      <div class="flex items-baseline justify-between gap-3">
        <h3 class="text-base font-semibold text-gray-900">{{ deal.town }}</h3>
        <p class="text-lg font-bold text-gray-900">{{ money(deal.asking_price) }}</p>
      </div>
      <p class="text-sm/6 text-gray-600">{{ deal.teaser }}</p>
      <p class="mt-auto flex items-center gap-1.5 pt-2 text-sm font-medium text-indigo-600 group-hover:text-indigo-500">
        <svg v-if="locked" class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
          <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" />
        </svg>
        {{ locked ? 'Members: log in for the full appraisal' : 'Open the appraisal' }}
        <span aria-hidden="true">→</span>
      </p>
    </div>
  </NuxtLink>
</template>
