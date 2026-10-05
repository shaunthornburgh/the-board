<script setup lang="ts">
useHead({ title: 'Request an invite' })

const api = useApi()
const { membership } = useAuth()

const form = reactive({ name: '', email: '', phone: '', message: '' })
const errors = ref<Record<string, string>>({})
const busy = ref(false)
const sent = ref(false)

const fields = 'mt-2 block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm'

async function submit() {
  busy.value = true
  errors.value = {}
  try {
    await api('/api/invite-requests', { method: 'POST', body: form })
    sent.value = true
  } catch (error) {
    errors.value = validationErrors(error)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="mx-auto grid max-w-7xl gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-24">
    <div>
      <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Request an invite</h1>
      <p class="mt-4 text-lg/8 text-gray-600">Membership is by invitation and capped at {{ membership?.room_cap ?? 20 }} places. Tell us who you are and John will be in touch.</p>
      <dl class="mt-10 space-y-4 text-base text-gray-700">
        <div class="flex gap-3"><dt class="font-semibold text-gray-900">{{ money(membership?.price_per_month ?? 500) }}</dt><dd>per month, ex VAT. Nothing else.</dd></div>
        <div class="flex gap-3"><dt class="font-semibold text-gray-900">{{ membership?.minimum_term_months ?? 3 }} months</dt><dd>minimum term.</dd></div>
        <div class="flex gap-3"><dt class="font-semibold text-gray-900">{{ membership?.room_cap ?? 20 }} places</dt><dd>in the room.</dd></div>
      </dl>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
      <div v-if="sent" class="py-8 text-center">
        <div class="mx-auto grid size-12 place-items-center rounded-full bg-emerald-100 text-emerald-600">
          <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
        </div>
        <h2 class="mt-4 text-xl font-semibold text-gray-900">Request received</h2>
        <p class="mt-2 text-sm text-gray-600">Thanks, {{ form.name }}. We will reply to {{ form.email }}.</p>
        <NuxtLink to="/" class="mt-6 inline-block text-sm font-semibold text-indigo-600 hover:text-indigo-500">Back to the home page</NuxtLink>
      </div>

      <form v-else class="space-y-5" @submit.prevent="submit">
        <div>
          <label for="name" class="block text-sm font-medium text-gray-900">Name</label>
          <input id="name" v-model="form.name" required autocomplete="name" :class="fields" />
          <p v-if="errors.name" class="mt-2 text-sm text-red-600">{{ errors.name }}</p>
        </div>
        <div>
          <label for="email" class="block text-sm font-medium text-gray-900">Email</label>
          <input id="email" v-model="form.email" type="email" required autocomplete="email" :class="fields" />
          <p v-if="errors.email" class="mt-2 text-sm text-red-600">{{ errors.email }}</p>
        </div>
        <div>
          <label for="phone" class="block text-sm font-medium text-gray-900">Phone <span class="font-normal text-gray-500">(optional)</span></label>
          <input id="phone" v-model="form.phone" type="tel" autocomplete="tel" :class="fields" />
        </div>
        <div>
          <label for="message" class="block text-sm font-medium text-gray-900">What are you looking for? <span class="font-normal text-gray-500">(optional)</span></label>
          <textarea id="message" v-model="form.message" rows="4" :class="fields" placeholder="Areas, budget, what you have done before" />
          <p v-if="errors.message" class="mt-2 text-sm text-red-600">{{ errors.message }}</p>
        </div>
        <p v-if="errors.form" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ errors.form }}</p>
        <button type="submit" :disabled="busy" class="w-full rounded-md bg-indigo-600 px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">
          {{ busy ? 'Sending…' : 'Request an invite' }}
        </button>
      </form>
    </div>
  </div>
</template>
