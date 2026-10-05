<script setup lang="ts">
useHead({ title: 'Log in' })

const route = useRoute()
const { user, login } = useAuth()

const form = reactive({ email: '', password: '', remember: false })
const errors = ref<Record<string, string>>({})
const busy = ref(false)

const returning = computed(() => typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/deals/'))

async function go() {
  await navigateTo(safeRedirect(route.query.redirect, user.value))
}

if (user.value) {
  await go()
}

async function submit() {
  busy.value = true
  errors.value = {}
  try {
    await login(form.email, form.password, form.remember)
    await go()
  } catch (error) {
    errors.value = validationErrors(error)
    form.password = ''
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="grid min-h-[calc(100vh-8rem)] lg:grid-cols-2">
    <div class="flex items-center justify-center px-4 py-16 sm:px-6">
      <div class="w-full max-w-sm">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">Log in to The Board</h1>
        <p class="mt-2 text-sm text-gray-600">
          {{ returning ? 'Deals are for members. Log in and we will take you straight back to it.' : 'Members only. Membership is by invitation.' }}
        </p>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
          <div>
            <label for="email" class="block text-sm font-medium text-gray-900">Email</label>
            <input id="email" v-model="form.email" type="email" autocomplete="username" required autofocus class="mt-2 block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm" />
            <p v-if="errors.email" class="mt-2 text-sm text-red-600">{{ errors.email }}</p>
          </div>
          <div>
            <label for="password" class="block text-sm font-medium text-gray-900">Password</label>
            <input id="password" v-model="form.password" type="password" autocomplete="current-password" required class="mt-2 block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm" />
            <p v-if="errors.password" class="mt-2 text-sm text-red-600">{{ errors.password }}</p>
          </div>
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.remember" type="checkbox" class="size-4 rounded border-gray-300 text-indigo-600" /> Keep me signed in
          </label>
          <p v-if="errors.form" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ errors.form }}</p>
          <button type="submit" :disabled="busy" class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">
            {{ busy ? 'Logging in…' : 'Log in' }}
          </button>
        </form>

        <p class="mt-8 text-sm text-gray-600">
          Not a member yet?
          <NuxtLink to="/request-invite" class="font-semibold text-indigo-600 hover:text-indigo-500">Request an invite</NuxtLink>
        </p>
      </div>
    </div>
    <div class="relative hidden lg:block">
      <img :src="'/images/john.jpg'" alt="" class="absolute inset-0 size-full object-cover object-[60%_30%]" />
      <div class="absolute inset-0 bg-gradient-to-t from-gray-900/70 to-transparent" />
      <p class="absolute inset-x-0 bottom-0 p-10 text-lg font-medium text-white">John edits what gets published.</p>
    </div>
  </div>
</template>
