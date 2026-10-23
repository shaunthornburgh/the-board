<script setup lang="ts">
import type { AnalystVerdict, OperatorDeal, StatusEvent, Strategy } from '~/utils/types'
import { forwardAction, type PipelineAction } from '~/utils/pipeline'

definePageMeta({ middleware: 'operator' })

const route = useRoute()
const api = useApi()
const flash = useFlash()

interface EditData {
  deal: OperatorDeal
  types: { slug: string; label: string }[]
  verdicts: Record<string, string>
  analyst_verdicts: Record<AnalystVerdict, string>
  strategies: Strategy[]
  tenures: Record<string, string>
  history: StatusEvent[]
}

interface ActionResult {
  deal: OperatorDeal
  message: string
  history?: StatusEvent[]
}

const { data, error } = await useAsyncData(`operator-deal-${route.params.id}`, () => api<EditData>(`/api/operator/deals/${route.params.id}`))

if (error.value) {
  throw createError({ statusCode: error.value.statusCode ?? 404, statusMessage: 'This deal does not exist.', fatal: true })
}

const deal = ref<OperatorDeal>(data.value!.deal)
const history = ref<StatusEvent[]>(data.value!.history)
useHead({ title: () => `Edit ${deal.value.title} · Operator` })

const pretty = (value: unknown) => (value && Object.keys(value as object).length ? JSON.stringify(value, null, 2) : '')

function formFrom(d: OperatorDeal) {
  return {
    type: d.type ?? 'pd',
    address: d.address ?? '',
    town: d.town ?? '',
    county: d.county ?? '',
    postcode: d.postcode ?? '',
    latitude: d.latitude ?? '',
    longitude: d.longitude ?? '',
    asking_price: d.asking_price ?? '',
    tenure: d.tenure ?? '',
    source_name: d.source_name ?? '',
    source_url: d.source_url ?? '',
    verdict: d.verdict ?? '',
    bot_note: d.bot_note ?? '',
    teaser: d.teaser ?? '',
    tenant: d.tenant ?? '',
    vacant_area_sq_ft: d.vacant_area_sq_ft ?? '',
    appraised_at: d.appraised_at ?? '',
    comps_json: pretty(d.comps),
    evidence_json: pretty(d.evidence),
    strategies: d.strategies.map((s) => s.slug),
  }
}

const form = reactive(formFrom(deal.value))
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const acting = ref(false)

const text: [Exclude<keyof typeof form, 'strategies'>, string][] = [
  ['address', 'Address'],
  ['town', 'Town'],
  ['county', 'County'],
  ['postcode', 'Postcode'],
  ['latitude', 'Latitude'],
  ['longitude', 'Longitude'],
  ['asking_price', 'Asking price (£)'],
  ['vacant_area_sq_ft', 'Vacant area (sq ft)'],
  ['tenant', 'Tenant'],
  ['source_name', 'Source name'],
  ['source_url', 'Source URL'],
]

function apply(result: ActionResult) {
  deal.value = result.deal
  if (result.history) history.value = result.history
  flash.show(result.message, result.deal.analyst_error && result.deal.status === 'awaiting_analyst' ? 'error' : 'success')
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    const body = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v]))
    const result = await api<ActionResult>(`/api/operator/deals/${deal.value.id}`, { method: 'PUT', body })
    apply(result)
    Object.assign(form, formFrom(result.deal))
  } catch (e) {
    errors.value = validationErrors(e)
    flash.show('Not saved. Check the highlighted fields.', 'error')
  } finally {
    saving.value = false
  }
}

async function post(path: PipelineAction | 'pack' | 'analyst-result', method: 'POST' | 'DELETE' = 'POST', body?: Record<string, unknown>) {
  acting.value = true
  try {
    apply(await api<ActionResult>(`/api/operator/deals/${deal.value.id}/${path}`, { method, body }))
    return true
  } catch (e) {
    flash.fail(e)
    return false
  } finally {
    acting.value = false
  }
}

const forward = computed(() => forwardAction(deal.value))

// Reject, with an optional reason recorded on the deal and in its history.
const rejecting = ref(false)
const rejectReason = ref('')

async function reject() {
  if (await post('reject', 'POST', { reason: rejectReason.value || null })) {
    rejecting.value = false
    rejectReason.value = ''
  }
}

// Record the analyst's answer by hand, for a deal waiting on the analyst.
const manual = reactive({ open: false, verdict: 'send' as AnalystVerdict, notes: '' })

async function recordAnalyst() {
  if (await post('analyst-result', 'POST', { verdict: manual.verdict, notes: manual.notes || null })) {
    manual.open = false
    manual.notes = ''
  }
}

function toggleStrategy(slug: string) {
  form.strategies = form.strategies.includes(slug) ? form.strategies.filter((s) => s !== slug) : [...form.strategies, slug]
}

const when = (value: string | null) => (value ? new Date(value).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' }) : '—')

const download = useDownload()

async function downloadPack() {
  try {
    await download(`/api/deals/${deal.value.id}/pack`, `${(deal.value.reference || `deal-${deal.value.id}`).toLowerCase()}-pack.pdf`)
  } catch (e) {
    flash.fail(e)
  }
}

const photo = ref<File | null>(null)
const photoInput = useTemplateRef<HTMLInputElement>('photoInput')
const uploading = ref(false)
const photoError = ref('')

async function uploadPhoto() {
  if (!photo.value) return
  uploading.value = true
  photoError.value = ''
  try {
    const body = new FormData()
    body.append('photo', photo.value)
    apply(await api(`/api/operator/deals/${deal.value.id}/photo`, { method: 'POST', body }))
    photo.value = null
    if (photoInput.value) photoInput.value.value = ''
  } catch (e) {
    photoError.value = validationErrors(e).photo ?? validationErrors(e).form ?? ''
  } finally {
    uploading.value = false
  }
}

const field = 'mt-2 block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600'
const label = 'block text-sm font-medium text-gray-900'
const secondary = 'rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 ring-inset hover:bg-gray-50 disabled:opacity-60'
const primary = 'rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60'
const danger = 'rounded-md bg-white px-3 py-2 text-sm font-semibold text-red-700 shadow-sm ring-1 ring-red-200 ring-inset hover:bg-red-50 disabled:opacity-60'
const dangerSolid = 'rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 disabled:opacity-60'
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <NuxtLink to="/operator/deals" class="text-sm font-medium text-gray-500 hover:text-gray-900">← Deals</NuxtLink>

    <div class="mt-4 flex flex-wrap items-center gap-3">
      <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ deal.title }}</h1>
      <SampleBadge v-if="deal.is_sample" />
      <StatusBadge :status="deal.status" :label="deal.status_label" />
      <span class="text-xs text-gray-500">{{ deal.origin === 'scout' ? `From scout${deal.scout ? ` ${deal.scout}` : ''}` : deal.origin === 'bot' ? 'From bot' : 'Entered by hand' }}</span>
      <div class="ml-auto flex gap-2">
        <NuxtLink :to="`/deals/${deal.id}`" :class="secondary">View</NuxtLink>
        <button v-if="deal.can_reject" type="button" :disabled="acting" :class="danger" @click="rejecting = !rejecting">Reject…</button>
        <button v-if="forward" type="button" :disabled="acting" :class="forward.action === 'unpublish' ? secondary : primary" @click="post(forward.action)">{{ forward.label }}</button>
      </div>
    </div>

    <form v-if="rejecting" class="mt-4 flex flex-wrap items-end gap-3 rounded-xl bg-red-50/50 p-4 ring-1 ring-red-200" @submit.prevent="reject">
      <div class="min-w-64 flex-1">
        <label for="reject_reason" :class="label">Why reject it? <span class="font-normal text-gray-500">(optional, kept in the history)</span></label>
        <input id="reject_reason" v-model="rejectReason" maxlength="1000" :class="field" />
      </div>
      <button type="submit" :disabled="acting" :class="dangerSolid">Reject deal</button>
      <button type="button" :class="secondary" @click="rejecting = false">Cancel</button>
    </form>

    <FlashMessage class="mt-6" :message="flash.message.value" :tone="flash.tone.value" @close="flash.clear()" />

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
      <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 lg:col-span-2">
        <div class="flex flex-wrap items-center gap-3">
          <h2 class="text-base font-semibold text-gray-900">Analyst</h2>
          <VerdictBadge v-if="deal.analyst_verdict" :verdict="deal.analyst_verdict" :label="deal.analyst_verdict_label" />
          <span v-else-if="deal.status === 'awaiting_analyst' && !deal.analyst_error" class="text-sm text-violet-700">Waiting for the analyst…</span>
          <div class="ml-auto flex gap-2">
            <button v-if="deal.status === 'awaiting_analyst'" type="button" :class="secondary" @click="manual.open = !manual.open">Record result by hand</button>
          </div>
        </div>

        <p v-if="deal.analyst_error" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-200">
          The last call failed: {{ deal.analyst_error }}
        </p>
        <p v-if="deal.analyst_notes" class="mt-3 text-sm whitespace-pre-line text-gray-700">{{ deal.analyst_notes }}</p>
        <p v-else-if="deal.status === 'new'" class="mt-3 text-sm text-gray-500">Not sent to the analyst yet.</p>
        <p v-if="deal.analyst_requested_at" class="mt-3 text-xs text-gray-500">
          Requested {{ when(deal.analyst_requested_at) }}<template v-if="deal.analyst_completed_at"> · answered {{ when(deal.analyst_completed_at) }}</template>
        </p>
        <p class="mt-2 text-xs text-gray-500">The analyst's verdict and notes are for you only. Members see the appraisal note and teaser you write below.</p>

        <form v-if="manual.open" class="mt-4 grid gap-4 border-t border-gray-200 pt-4 sm:grid-cols-3" @submit.prevent="recordAnalyst">
          <div>
            <label for="manual_verdict" :class="label">Verdict</label>
            <select id="manual_verdict" v-model="manual.verdict" :class="field">
              <option v-for="(name, value) in data?.analyst_verdicts" :key="value" :value="value">{{ name }}</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label for="manual_notes" :class="label">Notes</label>
            <textarea id="manual_notes" v-model="manual.notes" rows="3" :class="field" />
          </div>
          <div class="sm:col-span-3"><button type="submit" :disabled="acting" :class="primary">Save analyst result</button></div>
        </form>
      </section>

      <section class="space-y-3 rounded-2xl bg-white p-6 text-sm shadow-sm ring-1 ring-gray-200">
        <h2 class="text-base font-semibold text-gray-900">Pipeline</h2>
        <p v-if="deal.status === 'rejected'" class="rounded-lg bg-gray-50 px-3 py-2 text-gray-700 ring-1 ring-gray-200">
          Rejected {{ when(deal.rejected_at) }}<template v-if="deal.rejection_reason">: {{ deal.rejection_reason }}</template>
        </p>
        <dl v-if="deal.source_key || deal.scout_notes" class="space-y-1 text-gray-600">
          <div v-if="deal.source_key"><dt class="inline font-medium text-gray-900">Listing</dt> <dd class="inline">{{ deal.source }} · {{ deal.source_key }}</dd></div>
          <div v-if="deal.scout_notes"><dt class="font-medium text-gray-900">Scout notes</dt> <dd class="whitespace-pre-line">{{ deal.scout_notes }}</dd></div>
        </dl>
        <ol class="space-y-2 border-t border-gray-100 pt-3">
          <li v-for="event in history" :key="event.id" class="text-gray-600">
            <span class="font-medium text-gray-900">{{ event.to_label }}</span>
            <span class="text-xs text-gray-500"> · {{ when(event.at) }}<template v-if="event.by"> · {{ event.by }}</template></span>
            <p v-if="event.note" class="text-xs text-gray-500">{{ event.note }}</p>
          </li>
          <li v-if="!history.length" class="text-xs text-gray-500">No status changes recorded yet.</li>
        </ol>
      </section>
    </div>

    <form class="mt-8 grid gap-8 lg:grid-cols-3" @submit.prevent="save">
      <div class="grid content-start gap-5 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:grid-cols-2 lg:col-span-2">
        <div class="sm:col-span-2">
          <span :class="label">Photo</span>
          <div class="mt-2 flex flex-wrap items-start gap-4">
            <img v-if="deal.has_photo" :src="deal.photo_url!" alt="" class="aspect-[4/3] w-48 rounded-lg object-cover ring-1 ring-gray-200" />
            <p v-else class="w-48 rounded-lg bg-amber-50 p-3 text-xs text-amber-800 ring-1 ring-amber-200">No photo yet. A deal needs its own photo before it can be published.</p>
            <div class="space-y-2">
              <input ref="photoInput" type="file" accept="image/jpeg,image/png,image/webp" class="block text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-200" @change="photo = ($event.target as HTMLInputElement).files?.[0] ?? null" />
              <button type="button" :disabled="!photo || uploading" :class="secondary" @click="uploadPhoto">{{ uploading ? 'Uploading…' : deal.has_photo ? 'Replace photo' : 'Upload photo' }}</button>
              <p v-if="photoError" class="text-sm text-red-600">{{ photoError }}</p>
            </div>
          </div>
        </div>

        <div class="sm:col-span-2">
          <label for="teaser" :class="label">Teaser line <span class="font-normal text-gray-500">(shown on the public homepage, up to 160 characters)</span></label>
          <input id="teaser" v-model="form.teaser" maxlength="160" :class="field" />
          <p v-if="errors.teaser" class="mt-2 text-sm text-red-600">{{ errors.teaser }}</p>
        </div>

        <div v-for="[key, text_] in text" :key="key" :class="['address', 'source_url'].includes(key) ? 'sm:col-span-2' : ''">
          <label :for="key" :class="label">{{ text_ }}</label>
          <input :id="key" v-model="form[key]" :class="field" />
          <p v-if="errors[key]" class="mt-2 text-sm text-red-600">{{ errors[key] }}</p>
        </div>

        <div>
          <label for="tenure" :class="label">Tenure</label>
          <select id="tenure" v-model="form.tenure" :class="field">
            <option value="">—</option>
            <option v-for="(name, value) in data?.tenures" :key="value" :value="value">{{ name }}</option>
          </select>
        </div>
        <div>
          <label for="type" :class="label">Type</label>
          <select id="type" v-model="form.type" :class="field">
            <option v-for="t in data?.types" :key="t.slug" :value="t.slug">{{ t.label }}</option>
          </select>
        </div>
        <fieldset class="sm:col-span-2">
          <legend :class="label">Strategies</legend>
          <div class="mt-2 flex flex-wrap gap-2">
            <button
              v-for="s in data?.strategies"
              :key="s.slug"
              type="button"
              :aria-pressed="form.strategies.includes(s.slug)"
              class="rounded-full px-3 py-1 text-sm font-medium ring-1 ring-inset"
              :class="form.strategies.includes(s.slug) ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50'"
              @click="toggleStrategy(s.slug)"
            >
              {{ s.label }}
            </button>
          </div>
          <p v-if="errors.strategies" class="mt-2 text-sm text-red-600">{{ errors.strategies }}</p>
        </fieldset>
      </div>

      <div class="space-y-5 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <p class="text-sm text-gray-600">The appraisal members see. The verdict is only shown to you; the analyst fills it in if it is empty.</p>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="verdict" :class="label">Verdict</label>
            <select id="verdict" v-model="form.verdict" :class="field">
              <option value="">—</option>
              <option v-for="(name, value) in data?.verdicts" :key="value" :value="value">{{ name }}</option>
            </select>
          </div>
          <div>
            <label for="appraised_at" :class="label">Appraised</label>
            <input id="appraised_at" v-model="form.appraised_at" type="date" :class="field" />
          </div>
        </div>
        <div>
          <label for="bot_note" :class="label">Appraisal note</label>
          <textarea id="bot_note" v-model="form.bot_note" rows="6" :class="field" />
        </div>
        <div>
          <label for="evidence_json" :class="label">Evidence <span class="font-normal text-gray-500">(JSON object of label: value)</span></label>
          <textarea id="evidence_json" v-model="form.evidence_json" rows="6" :class="[field, 'font-mono text-xs']" placeholder='{"Article 4 direction": "None found"}' />
          <p v-if="errors.evidence_json || errors.evidence" class="mt-2 text-sm text-red-600">{{ errors.evidence_json || errors.evidence }}</p>
        </div>
        <div>
          <label for="comps_json" :class="label">Comps <span class="font-normal text-gray-500">(JSON list of address, price, date, note)</span></label>
          <textarea id="comps_json" v-model="form.comps_json" rows="6" :class="[field, 'font-mono text-xs']" placeholder='[{"address": "", "price": 0, "date": "", "note": ""}]' />
          <p v-if="errors.comps_json || errors.comps" class="mt-2 text-sm text-red-600">{{ errors.comps_json || errors.comps }}</p>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 pt-5 lg:col-span-3">
        <button type="submit" :disabled="saving" :class="primary">{{ saving ? 'Saving…' : 'Save' }}</button>
        <span class="ml-auto text-sm text-gray-500">PDF pack: {{ deal.has_pack ? 'generated' : 'not generated' }}</span>
        <button type="button" :disabled="acting" :class="secondary" @click="post('pack')">{{ deal.has_pack ? 'Regenerate pack' : 'Generate pack' }}</button>
        <button v-if="deal.has_pack" type="button" :class="secondary" @click="downloadPack">Download</button>
        <button v-if="deal.has_pack" type="button" :disabled="acting" :class="secondary" @click="post('pack', 'DELETE')">Remove pack</button>
      </div>
    </form>
  </div>
</template>
