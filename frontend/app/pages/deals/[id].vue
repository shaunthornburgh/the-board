<script setup lang="ts">
import type { Deal } from '~/utils/types'

definePageMeta({ middleware: 'member' })

const route = useRoute()
const api = useApi()
const { user } = useAuth()

const { data, error } = await useAsyncData(`deal-${route.params.id}`, () => api<{ deal: Deal }>(`/api/deals/${route.params.id}`))

if (error.value) {
  throw createError({ statusCode: error.value.statusCode ?? 404, statusMessage: 'This deal is not available.', fatal: true })
}

const deal = computed(() => data.value!.deal)
const isOperator = computed(() => user.value?.role === 'operator')

const download = useDownload()
const downloading = ref(false)
const downloadError = ref('')

async function downloadPack() {
  downloading.value = true
  downloadError.value = ''
  try {
    await download(`/api/deals/${deal.value.id}/pack`, `${(deal.value.reference || `deal-${deal.value.id}`).toLowerCase()}-pack.pdf`)
  } catch {
    downloadError.value = 'The pack could not be downloaded. Try again.'
  } finally {
    downloading.value = false
  }
}
const place = computed(() => [deal.value.town, deal.value.county, deal.value.postcode].filter(Boolean).join(', '))
const evidence = computed(() => Object.entries(deal.value.evidence ?? {}))

useHead({ title: () => deal.value.title })

const facts = computed(() => [
  ['Asking price', money(deal.value.asking_price)],
  ['Tenure', deal.value.tenure ?? '—'],
  ['Type', deal.value.type ?? '—'],
  ['Tenant', deal.value.tenant ?? '—'],
  ['Vacant area', area(deal.value.vacant_area_sq_ft)],
])
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <NuxtLink to="/board" class="text-sm font-medium text-gray-500 hover:text-gray-900">← Back to the board</NuxtLink>

    <div v-if="deal.status !== 'published'" class="mt-6 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
      Draft. Members cannot see this deal.
      <NuxtLink :to="`/operator/deals/${deal.id}`" class="ml-1 font-semibold underline">Edit</NuxtLink>
    </div>

    <div class="mt-6 grid gap-10 lg:grid-cols-5">
      <div class="lg:col-span-3">
        <img v-if="deal.photo_url" :src="deal.photo_url" :alt="`Photo of ${deal.address}`" class="aspect-[4/3] w-full rounded-2xl object-cover shadow-sm ring-1 ring-gray-200" />
        <div v-else class="grid aspect-[4/3] w-full place-items-center rounded-2xl bg-amber-50 text-sm font-medium text-amber-800 ring-1 ring-amber-200">No photo yet. Upload one before publishing.</div>
      </div>

      <div class="space-y-6 lg:col-span-2">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <SampleBadge v-if="deal.is_sample" />
            <span class="text-xs text-gray-400">{{ deal.reference }}</span>
          </div>
          <h1 class="mt-3 text-3xl font-bold tracking-tight text-gray-900">{{ deal.address || deal.title }}</h1>
          <p class="mt-1 text-base text-gray-600">{{ place }}</p>
          <p class="mt-4 text-3xl font-bold text-gray-900">{{ money(deal.asking_price) }}</p>
        </div>

        <dl class="divide-y divide-gray-200 rounded-xl bg-white text-sm ring-1 ring-gray-200">
          <div v-for="[label, value] in facts" :key="label" class="flex justify-between gap-4 px-4 py-3">
            <dt class="text-gray-500">{{ label }}</dt>
            <dd class="text-right font-medium text-gray-900">{{ value }}</dd>
          </div>
          <div class="flex justify-between gap-4 px-4 py-3">
            <dt class="text-gray-500">Source</dt>
            <dd class="text-right font-medium">
              <a v-if="deal.source_url" :href="deal.source_url" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:text-indigo-500">{{ deal.source_name || 'Listing' }}</a>
              <span v-else class="text-gray-900">{{ deal.source_name || 'Off-market' }}</span>
            </dd>
          </div>
        </dl>

        <div v-if="deal.has_pack" class="rounded-xl bg-indigo-50 p-4 ring-1 ring-indigo-100">
          <button type="button" :disabled="downloading" class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60" @click="downloadPack">
            <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z" /><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z" /></svg>
            {{ downloading ? 'Preparing…' : 'Download pack (PDF)' }}
          </button>
          <p v-if="downloadError" class="mt-2 text-sm text-red-600">{{ downloadError }}</p>
          <p class="mt-2 text-xs text-indigo-900/70">Stamped with your email. Screen of listing material, not a valuation, not advice.</p>
        </div>
      </div>
    </div>

    <div class="mt-12 grid gap-10 lg:grid-cols-5">
      <section class="lg:col-span-3">
        <h2 class="text-lg font-semibold text-gray-900">Appraisal note</h2>
        <p class="mt-3 text-base/7 whitespace-pre-line text-gray-700">{{ deal.bot_note || 'No note recorded.' }}</p>
        <p class="mt-3 text-xs text-gray-500">Appraised {{ day(deal.appraised_at) }}. A screen of listing material; not re-screened here.</p>

        <template v-if="deal.comps.length">
          <h2 class="mt-10 text-lg font-semibold text-gray-900">Comparables</h2>
          <div class="mt-3 overflow-x-auto rounded-xl ring-1 ring-gray-200">
            <table class="min-w-full divide-y divide-gray-200 bg-white text-sm">
              <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-600">
                <tr><th class="px-4 py-3">Address</th><th class="px-4 py-3">Price</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Note</th></tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="(comp, i) in deal.comps" :key="i">
                  <td class="px-4 py-3 text-gray-900">{{ comp.address }}</td>
                  <td class="px-4 py-3 font-medium text-gray-900">{{ money(comp.price) }}</td>
                  <td class="px-4 py-3 text-gray-500">{{ comp.date }}</td>
                  <td class="px-4 py-3 text-gray-500">{{ comp.note }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </section>

      <section v-if="evidence.length" class="lg:col-span-2">
        <h2 class="text-lg font-semibold text-gray-900">Evidence</h2>
        <dl class="mt-3 divide-y divide-gray-200 rounded-xl bg-white text-sm ring-1 ring-gray-200">
          <div v-for="[label, value] in evidence" :key="label" class="flex justify-between gap-4 px-4 py-3">
            <dt class="text-gray-500">{{ label }}</dt>
            <dd class="text-right font-medium text-gray-900">{{ value }}</dd>
          </div>
        </dl>
      </section>
    </div>

    <p v-if="isOperator" class="mt-12 text-sm">
      <NuxtLink :to="`/operator/deals/${deal.id}`" class="font-semibold text-indigo-600 hover:text-indigo-500">Edit this deal</NuxtLink>
    </p>
  </div>
</template>
