<script setup lang="ts">
import type { DealStatus, OperatorDeal, StatusCount } from '~/utils/types'
import { forwardAction, statusHelp, type PipelineAction } from '~/utils/pipeline'

definePageMeta({ middleware: 'operator' })
useHead({ title: 'Deals · Operator' })

const api = useApi()
const flash = useFlash()
const route = useRoute()
const router = useRouter()

interface ListData {
  deals: OperatorDeal[]
  pagination: { current_page: number; last_page: number; total: number }
  statuses: StatusCount[]
}

// The tab, search and page live in the URL so a refresh or a back button keeps them.
const status = computed<DealStatus>(() => (route.query.status as DealStatus) || 'new')
const page = computed(() => Number(route.query.page) || 1)
const search = ref((route.query.q as string) || '')

const { data, refresh } = await useAsyncData(
  'operator-deals',
  () => api<ListData>('/api/operator/deals', { query: { status: status.value, page: page.value, q: route.query.q || undefined } }),
  { watch: [() => route.query] },
)

const deals = computed(() => data.value?.deals ?? [])
const current = computed(() => data.value?.statuses.find((s) => s.value === status.value))

function go(query: Record<string, string | number | undefined>) {
  selected.value = []
  router.push({ query: { status: status.value, ...(route.query.q ? { q: route.query.q } : {}), ...query } })
}

function runSearch() {
  go({ q: search.value || undefined, page: undefined })
}

// Bulk selection: only on tabs where a bulk move makes sense.
const canBulkReject = computed(() => ['new', 'analyst_done', 'awaiting_review'].includes(status.value))
const canBulkAnalyst = computed(() => status.value === 'new')
const selected = ref<number[]>([])
const allSelected = computed(() => deals.value.length > 0 && selected.value.length === deals.value.length)
const rejectReason = ref('')

function toggleAll() {
  selected.value = allSelected.value ? [] : deals.value.map((d) => d.id)
}

const busy = ref<number | 'bulk' | null>(null)

async function act(deal: OperatorDeal, action: PipelineAction) {
  busy.value = deal.id
  try {
    const result = await api<{ message: string }>(`/api/operator/deals/${deal.id}/${action}`, { method: 'POST' })
    flash.show(result.message)
    await refresh()
  } catch (error) {
    flash.fail(error)
  } finally {
    busy.value = null
  }
}

async function bulk(action: 'reject' | 'send-to-analyst') {
  busy.value = 'bulk'
  try {
    const body = { ids: selected.value, ...(action === 'reject' && rejectReason.value ? { reason: rejectReason.value } : {}) }
    const result = await api<{ message: string; skipped: Record<string, string> }>(`/api/operator/deals/bulk/${action}`, { method: 'POST', body })
    flash.show(result.message, Object.keys(result.skipped).length ? 'error' : 'success')
    selected.value = []
    rejectReason.value = ''
    await refresh()
  } catch (error) {
    flash.fail(error)
  } finally {
    busy.value = null
  }
}

const sourceUrl = ref('')
const creating = ref(false)
const createError = ref('')

async function createDeal() {
  creating.value = true
  createError.value = ''
  try {
    const result = await api<{ deal: OperatorDeal }>('/api/operator/deals', { method: 'POST', body: { source_url: sourceUrl.value } })
    await navigateTo(`/operator/deals/${result.deal.id}`)
  } catch (error) {
    createError.value = validationErrors(error).source_url ?? validationErrors(error).form ?? ''
  } finally {
    creating.value = false
  }
}

function origin(deal: OperatorDeal) {
  if (deal.origin === 'scout') return deal.scout ? `Scout · ${deal.scout}` : 'Scout'
  return deal.origin === 'bot' ? 'From bot' : 'By hand'
}

const field = 'block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600'
const secondary = 'rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 ring-inset hover:bg-gray-50 disabled:opacity-60'
const primary = 'rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60'
const danger = 'rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 shadow-sm ring-1 ring-red-200 ring-inset hover:bg-red-50 disabled:opacity-60'
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <h1 class="text-3xl font-bold tracking-tight text-gray-900">Operator</h1>
    <OperatorNav class="mt-6" />

    <div class="mt-8 flex flex-wrap items-end justify-between gap-6">
      <form class="flex w-full max-w-sm items-end gap-2" @submit.prevent="runSearch">
        <div class="flex-1">
          <label for="q" class="block text-sm font-medium text-gray-900">Search this tab</label>
          <input id="q" v-model="search" type="search" placeholder="Address, town, postcode, reference" :class="[field, 'mt-2']" />
        </div>
        <button type="submit" :class="[secondary, 'py-2']">Search</button>
      </form>
      <form class="flex w-full max-w-md items-start gap-2" @submit.prevent="createDeal">
        <div class="flex-1">
          <label for="source_url" class="block text-sm font-medium text-gray-900">Paste a listing URL to add a deal by hand</label>
          <input id="source_url" v-model="sourceUrl" type="url" required placeholder="https://" :class="[field, 'mt-2']" />
          <p v-if="createError" class="mt-2 text-sm text-red-600">{{ createError }}</p>
        </div>
        <button type="submit" :disabled="creating" :class="[secondary, 'mt-7 py-2']">Add deal</button>
      </form>
    </div>

    <nav class="mt-8 flex gap-1 overflow-x-auto border-b border-gray-200" aria-label="Pipeline">
      <button
        v-for="s in data?.statuses"
        :key="s.value"
        type="button"
        class="-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap"
        :class="s.value === status ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
        @click="go({ status: s.value, page: undefined })"
      >
        {{ s.label }}
        <span class="rounded-full px-2 py-0.5 text-xs" :class="s.value === status ? 'bg-indigo-50 text-indigo-700' : 'bg-gray-100 text-gray-600'">{{ s.count }}</span>
      </button>
    </nav>

    <p class="mt-4 text-sm text-gray-600">{{ statusHelp[status] }}</p>

    <FlashMessage class="mt-4" :message="flash.message.value" :tone="flash.tone.value" @close="flash.clear()" />

    <div v-if="selected.length" class="mt-4 flex flex-wrap items-center gap-3 rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-200">
      <span class="text-sm font-medium text-gray-900">{{ selected.length }} selected</span>
      <button v-if="canBulkAnalyst" type="button" :disabled="busy === 'bulk'" :class="primary" @click="bulk('send-to-analyst')">Send to Analyst</button>
      <template v-if="canBulkReject">
        <input v-model="rejectReason" placeholder="Reason (optional)" maxlength="1000" :class="[field, 'max-w-xs py-1.5']" />
        <button type="button" :disabled="busy === 'bulk'" :class="danger" @click="bulk('reject')">Reject</button>
      </template>
      <button type="button" class="ml-auto text-sm text-gray-500 hover:text-gray-900" @click="selected = []">Clear</button>
    </div>

    <p v-if="!deals.length" class="mt-6 rounded-xl bg-gray-50 px-4 py-6 text-center text-sm text-gray-500 ring-1 ring-gray-200">
      {{ route.query.q ? 'Nothing matches that search.' : `Nothing in ${current?.label.toLowerCase() ?? 'this tab'}.` }}
    </p>

    <div v-else class="mt-4 overflow-x-auto rounded-xl ring-1 ring-gray-200">
      <table class="min-w-full divide-y divide-gray-200 bg-white text-sm">
        <thead v-if="canBulkReject" class="bg-gray-50">
          <tr>
            <th class="w-10 py-2 pl-4 text-left">
              <input type="checkbox" :checked="allSelected" aria-label="Select all" class="rounded border-gray-300" @change="toggleAll" />
            </th>
            <th colspan="6" class="py-2 text-left text-xs font-medium text-gray-500">Select all on this page</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="deal in deals" :key="deal.id" :class="selected.includes(deal.id) ? 'bg-indigo-50/40' : ''">
            <td v-if="canBulkReject" class="w-10 py-3 pl-4">
              <input v-model="selected" type="checkbox" :value="deal.id" :aria-label="`Select ${deal.title}`" class="rounded border-gray-300" />
            </td>
            <td class="w-20 py-3 pl-4">
              <img v-if="deal.has_photo" :src="deal.photo_url!" alt="" class="aspect-[4/3] w-16 rounded-md object-cover ring-1 ring-gray-200" />
              <span v-else class="grid aspect-[4/3] w-16 place-items-center rounded-md bg-gray-50 text-[10px] font-medium text-gray-500 ring-1 ring-gray-200">No photo</span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <NuxtLink :to="`/operator/deals/${deal.id}`" class="font-medium text-gray-900 hover:text-indigo-600">{{ deal.title }}</NuxtLink>
                <SampleBadge v-if="deal.is_sample" />
              </div>
              <p class="text-xs text-gray-500">{{ [deal.town, deal.county].filter(Boolean).join(', ') || deal.source_url }}</p>
              <div v-if="deal.strategies.length" class="mt-1 flex flex-wrap gap-1">
                <span v-for="s in deal.strategies" :key="s.slug" class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-medium text-gray-700">{{ s.label }}</span>
              </div>
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-gray-700">{{ money(deal.asking_price) }}</td>
            <td class="px-4 py-3 text-xs whitespace-nowrap text-gray-500">{{ origin(deal) }}</td>
            <td class="px-4 py-3">
              <VerdictBadge v-if="deal.analyst_verdict" :verdict="deal.analyst_verdict" :label="deal.analyst_verdict_label" />
              <p v-else-if="deal.analyst_error" class="max-w-48 truncate text-xs text-red-600" :title="deal.analyst_error">Failed: {{ deal.analyst_error }}</p>
              <p v-if="deal.status === 'rejected' && deal.rejection_reason" class="mt-1 max-w-48 truncate text-xs text-gray-500" :title="deal.rejection_reason">{{ deal.rejection_reason }}</p>
            </td>
            <td class="px-4 py-3 text-xs whitespace-nowrap text-gray-500">{{ day(deal.status_changed_at ?? deal.updated_at) }}</td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <div class="flex justify-end gap-2">
                <NuxtLink :to="`/operator/deals/${deal.id}`" :class="secondary">Open</NuxtLink>
                <button v-if="deal.can_reject" type="button" :disabled="busy === deal.id" :class="danger" @click="act(deal, 'reject')">Reject</button>
                <button
                  v-if="forwardAction(deal)"
                  type="button"
                  :disabled="busy === deal.id"
                  :class="forwardAction(deal)!.action === 'unpublish' ? secondary : primary"
                  @click="act(deal, forwardAction(deal)!.action)"
                >
                  {{ forwardAction(deal)!.label }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="data && data.pagination.last_page > 1" class="mt-4 flex items-center justify-between text-sm text-gray-600">
      <span>Page {{ data.pagination.current_page }} of {{ data.pagination.last_page }} · {{ data.pagination.total }} deals</span>
      <div class="flex gap-2">
        <button type="button" :disabled="page <= 1" :class="secondary" @click="go({ page: page - 1 })">Previous</button>
        <button type="button" :disabled="page >= data.pagination.last_page" :class="secondary" @click="go({ page: page + 1 })">Next</button>
      </div>
    </div>
  </div>
</template>
