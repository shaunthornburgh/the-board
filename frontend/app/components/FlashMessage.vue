<script setup lang="ts">
defineProps<{ message: string | null; tone?: 'success' | 'error' }>()
defineEmits<{ close: [] }>()
</script>

<template>
  <div
    v-if="message"
    role="status"
    class="flex items-start gap-3 rounded-lg px-4 py-3 text-sm ring-1"
    :class="tone === 'error' ? 'bg-red-50 text-red-800 ring-red-200' : 'bg-emerald-50 text-emerald-800 ring-emerald-200'"
  >
    <p class="flex-1">{{ message }}</p>
    <button type="button" class="font-medium opacity-70 hover:opacity-100" aria-label="Dismiss" @click="$emit('close')">✕</button>
  </div>
</template>
