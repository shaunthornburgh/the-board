<script setup lang="ts">
import type { OperatorDeal } from '~/utils/types'

definePageMeta({ middleware: 'operator' })

const route = useRoute()
const api = useApi()
const flash = useFlash()

interface EditData {
  deal: OperatorDeal
  types: { slug: string; label: string }[]
  verdicts: Record<string, string>
  tenures: Record<string, string>
}

const { data, error } = await useAsyncData(`operator-deal-${route.params.id}`, () => api<EditData>(`/api/operator/deals/${route.params.id}`))

if (error.value) {
  throw createError({ statusCode: error.value.statusCode ?? 404, statusMessage: 'This deal does not exist.', fatal: true })
}

const deal = ref<OperatorDeal>(data.value!.deal)
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
  }
}

const form = reactive(formFrom(deal.value))
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const acting = ref(false)

const text: [keyof typeof form, string][] = [
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

function apply(result: { deal: OperatorDeal; message: string }) {
  deal.value = result.deal
  flash.show(result.message)
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    const body = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v]))
    const result = await api<{ deal: OperatorDeal; message: string }>(`/api/operator/deals/${deal.value.id}`, { method: 'PUT', body })
    apply(result)
    Object.assign(form, formFrom(result.deal))
  } catch (e) {
    errors.value = validationErrors(e)
    flash.show('Not saved. Check the highlighted fields.', 'error')
  } finally {
    saving.value = false
  }
}

async function post(path: 'publish' | 'unpublish' | 'pack', method: 'POST' | 'DELETE' = 'POST') {
  acting.value = true
  try {
    apply(await api(`/api/operator/deals/${deal.value.id}/${path}`, { method }))
  } catch (e) {
    flash.fail(e)
  } finally {
    acting.value = false
  }
}

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
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <NuxtLink to="/operator/deals" class="text-sm font-medium text-gray-500 hover:text-gray-900">← Deals</NuxtLink>

    <div class="mt-4 flex flex-wrap items-center gap-3">
      <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ deal.title }}</h1>
      <SampleBadge v-if="deal.is_sample" />
      <span
        class="rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
        :class="deal.status === 'published' ? 'bg-indigo-50 text-indigo-700 ring-indigo-600/20' : 'bg-gray-50 text-gray-600 ring-gray-500/20'"
      >{{ deal.status === 'published' ? 'Published' : 'Draft' }}</span>
      <span class="text-xs text-gray-500">{{ deal.origin === 'bot' ? 'From bot' : 'Entered by hand' }}</span>
      <div class="ml-auto flex gap-2">
        <NuxtLink :to="`/deals/${deal.id}`" :class="secondary">View</NuxtLink>
        <button v-if="deal.status === 'draft'" type="button" :disabled="acting" :class="primary" @click="post('publish')">Publish</button>
        <button v-else type="button" :disabled="acting" :class="secondary" @click="post('unpublish')">Unpublish</button>
      </div>
    </div>

    <FlashMessage class="mt-6" :message="flash.message.value" :tone="flash.tone.value" @close="flash.clear()" />

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
      </div>

      <div class="space-y-5 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <p class="text-sm text-gray-600">The appraisal, entered as screened. The app does not screen. The verdict is only shown to you.</p>
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
