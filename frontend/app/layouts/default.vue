<script setup lang="ts">
const { user } = useAuth()
const credits = `${useRuntimeConfig().public.apiBase}/images/deals/CREDITS.md`
const route = useRoute()
const open = ref(false)

watch(() => route.fullPath, () => (open.value = false))
</script>

<template>
  <div class="flex min-h-screen flex-col bg-white text-gray-900 antialiased">
    <header class="sticky top-0 z-30 border-b border-gray-200 bg-white/90 backdrop-blur">
      <nav class="mx-auto flex max-w-7xl items-center gap-6 px-4 py-3 sm:px-6 lg:px-8">
        <NuxtLink to="/" class="flex items-center gap-2 text-base font-bold tracking-tight text-gray-900">
          <span class="grid size-8 place-items-center rounded-lg bg-indigo-600 text-sm font-bold text-white">B</span>
          The Board
        </NuxtLink>

        <div class="hidden items-center gap-5 text-sm font-medium text-gray-600 md:flex">
          <NuxtLink v-if="user?.can_see_board" to="/board" class="hover:text-gray-900" active-class="text-gray-900">Board</NuxtLink>
          <template v-else>
            <NuxtLink to="/#deals" class="hover:text-gray-900">Deals</NuxtLink>
            <NuxtLink to="/#how" class="hover:text-gray-900">How it works</NuxtLink>
            <NuxtLink to="/#pricing" class="hover:text-gray-900">Pricing</NuxtLink>
          </template>
        </div>

        <div class="ml-auto flex items-center gap-2">
          <NuxtLink v-if="!user" to="/request-invite" class="hidden rounded-md bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:inline-block">Request an invite</NuxtLink>
          <UserMenu />
          <button type="button" class="rounded-md p-2 text-gray-700 hover:bg-gray-100 md:hidden" :aria-expanded="open" aria-label="Menu" @click="open = !open">
            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
          </button>
        </div>
      </nav>

      <div v-if="open" class="space-y-1 border-t border-gray-200 px-4 py-3 text-sm font-medium md:hidden">
        <NuxtLink v-if="user?.can_see_board" to="/board" class="block rounded-md px-3 py-2 hover:bg-gray-50">Board</NuxtLink>
        <template v-else>
          <NuxtLink to="/#deals" class="block rounded-md px-3 py-2 hover:bg-gray-50">Deals</NuxtLink>
          <NuxtLink to="/#how" class="block rounded-md px-3 py-2 hover:bg-gray-50">How it works</NuxtLink>
          <NuxtLink to="/#pricing" class="block rounded-md px-3 py-2 hover:bg-gray-50">Pricing</NuxtLink>
        </template>
        <NuxtLink v-if="!user" to="/request-invite" class="block rounded-md bg-indigo-600 px-3 py-2 text-white hover:bg-indigo-500">Request an invite</NuxtLink>
      </div>
    </header>

    <main class="flex-1">
      <slot />
    </main>

    <footer class="border-t border-gray-200 bg-gray-50">
      <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-8 text-sm text-gray-500 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
        <p>The Board. Appraisals are a screen of listing material, not a valuation, not advice.</p>
        <p>Sample deals are fictional. Photos are illustrative, from Wikimedia Commons (<a :href="credits" class="underline hover:text-gray-700">credits and licences</a>).</p>
      </div>
    </footer>
  </div>
</template>
