<script setup lang="ts">
useHead({ title: 'Accept invitation' })

const route = useRoute()
const api = useApi()
const { signIn } = useAuth()

const { data, error } = await useAsyncData(`invite-${route.params.token}`, () => api<{ name: string; email: string }>(`/api/invite/${route.params.token}`))

const form = reactive({ password: '', password_confirmation: '' })
const errors = ref<Record<string, string>>({})
const busy = ref(false)
const fields = 'mt-2 block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm'

async function submit() {
  busy.value = true
  errors.value = {}
  try {
    const result = await api<{ token: string }>(`/api/invite/${route.params.token}`, { method: 'POST', body: form })
    await signIn(result.token)
    await navigateTo('/settings')
  } catch (e) {
    errors.value = validationErrors(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="flex min-h-[calc(100vh-8rem)] items-center justify-center px-4 py-16">
    <div v-if="error" class="max-w-sm text-center">
      <h1 class="text-2xl font-bold text-gray-900">This invitation is no longer valid</h1>
      <p class="mt-2 text-sm text-gray-600">It may have been used already. Try logging in instead.</p>
      <NuxtLink to="/login" class="mt-6 inline-block rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">Log in</NuxtLink>
    </div>
    <form v-else-if="data" class="w-full max-w-sm space-y-5" @submit.prevent="submit">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">Welcome, {{ data.name }}</h1>
        <p class="mt-2 text-sm text-gray-600">Set a password for {{ data.email }}.</p>
      </div>
      <div>
        <label for="password" class="block text-sm font-medium text-gray-900">Password</label>
        <input id="password" v-model="form.password" type="password" autocomplete="new-password" required :class="fields" />
        <p v-if="errors.password" class="mt-2 text-sm text-red-600">{{ errors.password }}</p>
      </div>
      <div>
        <label for="password_confirmation" class="block text-sm font-medium text-gray-900">Confirm password</label>
        <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required :class="fields" />
      </div>
      <p v-if="errors.form" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ errors.form }}</p>
      <button type="submit" :disabled="busy" class="w-full rounded-md bg-indigo-600 px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">Set password</button>
    </form>
  </div>
</template>
