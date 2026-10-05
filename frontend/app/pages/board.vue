<script setup lang="ts">
import type { Deal } from '~/utils/types'

definePageMeta({ middleware: 'member' })
useHead({ title: 'Board' })

const route = useRoute()
const router = useRouter()
const api = useApi()

interface Board {
  deals: Deal[]
  counties: string[]
}

const filters = reactive({
  county: typeof route.query.county === 'string' ? route.query.county : '',
})

const query = computed(() => Object.fromEntries(Object.entries(filters).filter(([, v]) => v)))

const { data } = await useAsyncData('board', () => api<Board>('/api/board', { query: query.value }), { watch: [query] })

watch(query, (value) => router.replace({ query: value }))

const deals = computed(() => data.value?.deals ?? [])
const select = 'mt-2 block w-full rounded-md bg-white py-2 pr-8 pl-3 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600'
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-6">
      <div>
        <h1 class="text-3xl font-bold tracking-tight text-gray-900">Board</h1>
        <p class="mt-2 text-sm text-gray-600">Published deals, newest first. Each one has been appraised.</p>
      </div>
      <div class="flex gap-3">
        <div>
          <label for="county" class="block text-sm font-medium text-gray-900">County</label>
          <select id="county" v-model="filters.county" :class="select">
            <option value="">All counties</option>
            <option v-for="county in data?.counties" :key="county" :value="county">{{ county }}</option>
          </select>
        </div>
      </div>
    </div>

    <DealMap :deals="deals" class="mt-8" />

    <div v-if="!deals.length" class="mt-8 rounded-2xl bg-gray-50 px-6 py-12 text-center ring-1 ring-gray-200">
      <p class="text-lg font-semibold text-gray-900">Nothing matches those filters.</p>
      <button type="button" class="mt-4 text-sm font-semibold text-indigo-600 hover:text-indigo-500" @click="filters.county = ''">Clear filters</button>
    </div>

    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <NuxtLink v-for="deal in deals" :key="deal.id" :to="`/deals/${deal.id}`" class="group overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:shadow-lg">
        <div class="relative aspect-[4/3] overflow-hidden">
          <img :src="deal.photo_url!" :alt="`Photo of ${deal.title}`" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-105" />
          <div class="absolute inset-x-0 top-0 flex items-center gap-2 p-3">
            <SampleBadge v-if="deal.is_sample" class="bg-white/90" />
          </div>
        </div>
        <div class="space-y-3 p-5">
          <div>
            <h2 class="font-semibold text-gray-900 group-hover:text-indigo-600">{{ deal.title }}</h2>
            <p class="text-sm text-gray-500">{{ deal.town }}, {{ deal.county }}</p>
          </div>
          <div class="flex items-baseline justify-between">
            <span class="text-lg font-bold text-gray-900">{{ money(deal.asking_price) }}</span>
            <span class="text-sm text-gray-500">{{ area(deal.vacant_area_sq_ft) }}</span>
          </div>
          <p class="text-xs text-gray-500">{{ deal.type }} · {{ deal.tenure }} · Published {{ day(deal.published_at) }}</p>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>
