<script setup lang="ts">
import type { NuxtError } from '#app'

const props = defineProps<{ error: NuxtError }>()
const notFound = computed(() => props.error.statusCode === 404)
</script>

<template>
  <NuxtLayout>
    <div class="mx-auto max-w-xl px-4 py-24 text-center">
      <p class="text-base font-semibold text-indigo-600">{{ error.statusCode }}</p>
      <h1 class="mt-4 text-3xl font-bold tracking-tight text-gray-900">{{ notFound ? 'Not found' : 'Something went wrong' }}</h1>
      <p class="mt-4 text-base text-gray-600">{{ notFound ? 'That page or deal is not available.' : error.statusMessage }}</p>
      <button type="button" class="mt-8 rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500" @click="clearError({ redirect: '/' })">Back to the home page</button>
    </div>
  </NuxtLayout>
</template>
