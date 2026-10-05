<template>
  <nav class="flex gap-1 border-b border-gray-200" aria-label="Operator">
    <NuxtLink
      v-for="tab in [{ to: '/operator/deals', label: 'Deals' }, { to: '/operator/members', label: 'Members' }]"
      :key="tab.to"
      :to="tab.to"
      class="-mb-px border-b-2 border-transparent px-3 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700"
      active-class="!border-indigo-600 !text-indigo-600"
    >
      {{ tab.label }}
    </NuxtLink>
  </nav>
</template>
