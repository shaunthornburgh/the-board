<script setup lang="ts">
// Account details now live on the settings page.
definePageMeta({ middleware: () => navigateTo('/settings', { redirectCode: 301 }) })
</script>

<template>
  <div />
</template>
