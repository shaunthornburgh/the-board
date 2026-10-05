<script setup lang="ts">
const { user, logout } = useAuth()
const route = useRoute()

const open = ref(false)
const root = useTemplateRef<HTMLElement>('root')

const isOperator = computed(() => user.value?.role === 'operator')
const initials = computed(() =>
  (user.value?.name ?? '')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]!.toUpperCase())
    .join(''),
)

watch(() => route.fullPath, () => (open.value = false))

function onDocumentClick(event: MouseEvent) {
  if (open.value && root.value && !root.value.contains(event.target as Node)) open.value = false
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') open.value = false
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onKeydown)
})

async function signOut() {
  open.value = false
  await logout()
  await navigateTo('/')
}
</script>

<template>
  <NuxtLink v-if="!user" to="/login" class="rounded-md px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">Log in</NuxtLink>

  <div v-else ref="root" class="relative">
    <button
      type="button"
      class="flex items-center gap-2 rounded-full py-1 pr-2 pl-1 text-sm font-semibold text-gray-700 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
      :aria-expanded="open"
      aria-haspopup="menu"
      @click="open = !open"
    >
      <span class="grid size-8 place-items-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700">{{ initials }}</span>
      <span class="hidden max-w-40 truncate sm:inline">{{ user.name }}</span>
      <svg class="size-4 text-gray-400 transition" :class="open && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
      </svg>
    </button>

    <Transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="scale-95 opacity-0"
      leave-active-class="transition duration-75 ease-in"
      leave-to-class="scale-95 opacity-0"
    >
      <div v-if="open" role="menu" class="absolute right-0 z-40 mt-2 w-60 origin-top-right rounded-xl bg-white py-1 shadow-lg ring-1 ring-gray-900/5">
        <div class="border-b border-gray-100 px-4 py-3">
          <p class="truncate text-sm font-semibold text-gray-900">{{ user.name }}</p>
          <p class="truncate text-xs text-gray-500">{{ user.email }}</p>
        </div>
        <NuxtLink to="/settings" role="menuitem" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900">Settings</NuxtLink>
        <NuxtLink v-if="isOperator" to="/operator/deals" role="menuitem" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900">Operator screens</NuxtLink>
        <button type="button" role="menuitem" class="block w-full border-t border-gray-100 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900" @click="signOut">Sign out</button>
      </div>
    </Transition>
  </div>
</template>
