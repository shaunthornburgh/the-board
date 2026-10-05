<script setup lang="ts">
import type { OperatorDeal } from '~/utils/types'

definePageMeta({ middleware: 'operator' })
useHead({ title: 'Deals · Operator' })

const api = useApi()
const flash = useFlash()

const { data, refresh } = await useAsyncData('operator-deals', () => api<{ deals: OperatorDeal[] }>('/api/operator/deals'))

const deals = computed(() => data.value?.deals ?? [])
const groups = computed(() => [
  { title: 'Drafts', rows: deals.value.filter((d) => d.status === 'draft') },
  { title: 'Published', rows: deals.value.filter((d) => d.status === 'published') },
])

const busy = ref<number | null>(null)

async function act(deal: OperatorDeal, action: 'publish' | 'unpublish') {
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

const sourceUrl = ref('')
const creating = ref(false)
const createError = ref('')

async function createDraft() {
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

const field = 'block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600'
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <h1 class="text-3xl font-bold tracking-tight text-gray-900">Operator</h1>
    <OperatorNav class="mt-6" />

    <div class="mt-8 flex flex-wrap items-end justify-between gap-6">
      <p class="max-w-xl text-sm text-gray-600">New deals start as drafts. Members see published deals only. A deal needs an address and its own photo before it can be published.</p>
      <form class="flex w-full max-w-md items-start gap-2" @submit.prevent="createDraft">
        <div class="flex-1">
          <label for="source_url" class="block text-sm font-medium text-gray-900">Paste a listing URL to start an empty draft</label>
          <input id="source_url" v-model="sourceUrl" type="url" required placeholder="https://" :class="[field, 'mt-2']" />
          <p v-if="createError" class="mt-2 text-sm text-red-600">{{ createError }}</p>
        </div>
        <button type="submit" :disabled="creating" class="mt-7 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 ring-inset hover:bg-gray-50 disabled:opacity-60">Create draft</button>
      </form>
    </div>

    <FlashMessage class="mt-6" :message="flash.message.value" :tone="flash.tone.value" @close="flash.clear()" />

    <section v-for="group in groups" :key="group.title" class="mt-10">
      <h2 class="text-base font-semibold text-gray-900">{{ group.title }} <span class="font-normal text-gray-500">· {{ group.rows.length }}</span></h2>
      <p v-if="!group.rows.length" class="mt-3 rounded-xl bg-gray-50 px-4 py-6 text-center text-sm text-gray-500 ring-1 ring-gray-200">
        {{ group.title === 'Drafts' ? 'No drafts waiting. Paste a listing URL above to start one.' : 'Nothing published yet.' }}
      </p>
      <div v-else class="mt-3 overflow-x-auto rounded-xl ring-1 ring-gray-200">
        <table class="min-w-full divide-y divide-gray-200 bg-white text-sm">
          <tbody class="divide-y divide-gray-100">
            <tr v-for="deal in group.rows" :key="deal.id">
              <td class="w-20 py-3 pl-4">
                <img v-if="deal.has_photo" :src="deal.photo_url!" alt="" class="aspect-[4/3] w-16 rounded-md object-cover ring-1 ring-gray-200" />
                <span v-else class="grid aspect-[4/3] w-16 place-items-center rounded-md bg-amber-50 text-[10px] font-medium text-amber-700 ring-1 ring-amber-200">No photo</span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <NuxtLink :to="`/operator/deals/${deal.id}`" class="font-medium text-gray-900 hover:text-indigo-600">{{ deal.title }}</NuxtLink>
                  <SampleBadge v-if="deal.is_sample" />
                </div>
                <p class="text-xs text-gray-500">{{ [deal.town, deal.county].filter(Boolean).join(', ') || deal.source_url }}</p>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-gray-700">{{ money(deal.asking_price) }}</td>
              <td class="px-4 py-3 text-xs whitespace-nowrap text-gray-500">{{ deal.origin === 'bot' ? 'From bot' : 'By hand' }}</td>
              <td class="px-4 py-3 text-xs whitespace-nowrap text-gray-500">{{ deal.status === 'published' ? `Published ${day(deal.published_at)}` : `Updated ${day(deal.updated_at)}` }}</td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <NuxtLink :to="`/operator/deals/${deal.id}`" class="mr-2 rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 ring-inset hover:bg-gray-50">Edit</NuxtLink>
                <button v-if="deal.status === 'draft'" type="button" :disabled="busy === deal.id" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60" @click="act(deal, 'publish')">Publish</button>
                <button v-else type="button" :disabled="busy === deal.id" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 ring-inset hover:bg-gray-50 disabled:opacity-60" @click="act(deal, 'unpublish')">Unpublish</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
