<script setup lang="ts">
definePageMeta({ middleware: 'signed-in' })
useHead({ title: 'Settings' })

const api = useApi()
const { user, refresh } = useAuth()

interface Account {
  membership: { price_per_month: number; minimum_term_months: number; room_cap: number; places_taken: number }
  account: { role: string; status: string | null; activated_at: string | null; minimum_term_ends_at: string | null }
}

const { data } = await useAsyncData('account', () => api<Account>('/api/account'))

const form = reactive({
  name: user.value?.name ?? '',
  email: user.value?.email ?? '',
  current_password: '',
  password: '',
  password_confirmation: '',
})
const errors = ref<Record<string, string>>({})
const busy = ref(false)
const saved = ref(false)

const needsCurrentPassword = computed(() => form.password !== '' || form.email !== user.value?.email)

const statusText: Record<string, string> = {
  invited: 'Invited. Your place is held until your membership is activated.',
  active: 'Active',
  revoked: 'Revoked',
}

const field = 'mt-2 block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm'

watch(form, () => (saved.value = false))

async function save() {
  busy.value = true
  errors.value = {}
  try {
    await api('/api/settings', { method: 'PUT', body: form })
    await refresh()
    form.current_password = ''
    form.password = ''
    form.password_confirmation = ''
    await nextTick()
    saved.value = true
  } catch (error) {
    errors.value = validationErrors(error)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
    <h1 class="text-3xl font-bold tracking-tight text-gray-900">Settings</h1>
    <p class="mt-2 text-sm text-gray-600">Your name, email and password.</p>

    <form class="mt-8 rounded-2xl bg-white shadow-sm ring-1 ring-gray-200" @submit.prevent="save">
      <div class="space-y-8 p-6 sm:p-8">
        <section class="grid gap-6 sm:grid-cols-2">
          <h2 class="text-base font-semibold text-gray-900 sm:col-span-2">Profile</h2>
          <div>
            <label for="name" class="block text-sm font-medium text-gray-900">Name</label>
            <input id="name" v-model="form.name" required autocomplete="name" :class="field" />
            <p v-if="errors.name" class="mt-2 text-sm text-red-600">{{ errors.name }}</p>
          </div>
          <div>
            <label for="email" class="block text-sm font-medium text-gray-900">Email</label>
            <input id="email" v-model="form.email" type="email" required autocomplete="email" :class="field" />
            <p v-if="errors.email" class="mt-2 text-sm text-red-600">{{ errors.email }}</p>
          </div>
        </section>

        <section class="grid gap-6 border-t border-gray-200 pt-8 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <h2 class="text-base font-semibold text-gray-900">Password</h2>
            <p class="mt-1 text-sm text-gray-600">Leave the new password blank to keep your current one. At least 10 characters.</p>
          </div>
          <div>
            <label for="password" class="block text-sm font-medium text-gray-900">New password</label>
            <input id="password" v-model="form.password" type="password" autocomplete="new-password" :class="field" />
            <p v-if="errors.password" class="mt-2 text-sm text-red-600">{{ errors.password }}</p>
          </div>
          <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-900">Confirm new password</label>
            <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" :class="field" />
          </div>
          <div class="sm:col-span-2">
            <label for="current_password" class="block text-sm font-medium text-gray-900">
              Current password
              <span class="font-normal text-gray-500">{{ needsCurrentPassword ? '(required to change your password or email)' : '(only needed to change your password or email)' }}</span>
            </label>
            <input id="current_password" v-model="form.current_password" type="password" autocomplete="current-password" :required="needsCurrentPassword" :class="[field, 'sm:max-w-xs']" />
            <p v-if="errors.current_password" class="mt-2 text-sm text-red-600">{{ errors.current_password }}</p>
          </div>
        </section>

        <p v-if="errors.form" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ errors.form }}</p>
      </div>

      <div class="flex items-center justify-end gap-4 rounded-b-2xl border-t border-gray-200 bg-gray-50 px-6 py-4 sm:px-8">
        <p v-if="saved" role="status" class="flex items-center gap-1.5 text-sm font-medium text-emerald-700">
          <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" /></svg>
          Saved
        </p>
        <button type="submit" :disabled="busy" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">
          {{ busy ? 'Saving…' : 'Save' }}
        </button>
      </div>
    </form>

    <section v-if="data" class="mt-8 rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
      <div class="px-6 pt-6 sm:px-8">
        <h2 class="text-base font-semibold text-gray-900">Membership</h2>
        <p class="mt-1 text-sm text-gray-600">Billing is not connected in this demo.</p>
      </div>
      <dl class="mt-4 divide-y divide-gray-200 text-sm">
        <div class="flex justify-between gap-4 px-6 py-3 sm:px-8"><dt class="text-gray-500">Price</dt><dd class="text-right font-medium text-gray-900">{{ money(data.membership.price_per_month) }} per month, ex VAT</dd></div>
        <div class="flex justify-between gap-4 px-6 py-3 sm:px-8"><dt class="text-gray-500">Minimum term</dt><dd class="text-right font-medium text-gray-900">{{ data.membership.minimum_term_months }} months</dd></div>
        <template v-if="data.account.role === 'member'">
          <div class="flex justify-between gap-4 px-6 py-3 sm:px-8"><dt class="text-gray-500">Status</dt><dd class="text-right font-medium text-gray-900">{{ statusText[data.account.status ?? ''] ?? data.account.status }}</dd></div>
          <div class="flex justify-between gap-4 px-6 py-3 sm:px-8"><dt class="text-gray-500">Active since</dt><dd class="font-medium text-gray-900">{{ day(data.account.activated_at) }}</dd></div>
          <div class="flex justify-between gap-4 px-6 py-3 sm:px-8"><dt class="text-gray-500">Minimum term ends</dt><dd class="font-medium text-gray-900">{{ day(data.account.minimum_term_ends_at) }}</dd></div>
        </template>
      </dl>
    </section>
  </div>
</template>
