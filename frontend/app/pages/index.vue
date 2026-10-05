<script setup lang="ts">
import type { Teaser } from '~/utils/types'

useHead({ title: null })
useSeoMeta({
  description: 'Development opportunities appraised before they are listed. £500 a month, ex VAT. You keep the margin.',
})

const api = useApi()
const { user, membership } = useAuth()

const { data } = await useAsyncData('teasers', () => api<{ deals: Teaser[] }>('/api/teasers'))
const deals = computed(() => data.value?.deals ?? [])

const price = computed(() => money(membership.value?.price_per_month ?? 500))
const term = computed(() => membership.value?.minimum_term_months ?? 3)
const cap = computed(() => membership.value?.room_cap ?? 20)

const benefits = computed(() => [
  {
    title: 'Appraised before it is listed',
    body: 'Nothing reaches the board without its appraisal attached: the comparables and the evidence behind them.',
    icon: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
  },
  {
    title: 'PD first, other development later',
    body: 'The board starts with permitted development deals. Other types of development come later.',
    icon: 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z',
  },
  {
    title: `${price.value} a month and nothing else`,
    body: 'No sourcing fee, no finder’s fee, no reservation fee. One flat monthly membership.',
    icon: 'M14.121 7.629A3 3 0 0 0 9.017 9.43c-.023.212-.002.425.028.636l.506 3.541a4.5 4.5 0 0 1-.43 2.65L9 16.5l1.539-.513a2.25 2.25 0 0 1 1.422 0l.655.218a2.25 2.25 0 0 0 1.718-.122L15 15.75M8.25 12H12m9 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
  },
  {
    title: 'You keep the margin',
    body: 'We take no cut of the deal and no share of the uplift. Whatever you make on it is yours.',
    icon: 'M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941',
  },
])
</script>

<template>
  <div>
    <!-- Banner -->
    <section class="relative isolate overflow-hidden bg-gray-900">
      <div class="relative h-72 sm:h-96 lg:absolute lg:inset-y-0 lg:right-0 lg:h-full lg:w-1/2">
        <img :src="'/images/john.jpg'" alt="John outside a town-centre building" class="size-full object-cover object-[50%_35%]" />
        <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-transparent to-transparent lg:bg-gradient-to-r lg:from-gray-900 lg:via-gray-900/10" />
      </div>
      <div class="mx-auto max-w-7xl px-4 pt-4 pb-16 sm:px-6 lg:px-8 lg:py-32">
        <div class="max-w-xl lg:w-1/2 lg:pr-12">
          <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-sm font-medium text-indigo-200 ring-1 ring-white/20 ring-inset">
            Invite only · {{ cap }} places
          </p>
          <h1 class="mt-6 text-4xl font-bold tracking-tight text-white sm:text-6xl">Development deals, appraised before they reach you.</h1>
          <p class="mt-6 text-lg/8 text-gray-200">John edits what gets published. Every deal on The Board has been appraised and checked before it goes up.</p>
          <div class="mt-10 flex flex-wrap items-center gap-4">
            <NuxtLink to="/request-invite" class="rounded-md bg-indigo-500 px-5 py-3 text-base font-semibold text-white shadow-sm hover:bg-indigo-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400">
              Request an invite
            </NuxtLink>
            <NuxtLink v-if="!user" to="/login" class="rounded-md px-5 py-3 text-base font-semibold text-white ring-1 ring-white/30 hover:bg-white/10">Log in</NuxtLink>
            <NuxtLink v-else-if="user.can_see_board" to="/board" class="rounded-md px-5 py-3 text-base font-semibold text-white ring-1 ring-white/30 hover:bg-white/10">Go to the board</NuxtLink>
          </div>
          <p class="mt-8 text-sm text-gray-300">{{ price }} per month, ex VAT · {{ term }}-month minimum</p>
        </div>
      </div>
    </section>

    <!-- Benefits -->
    <section id="how" class="bg-white py-20 sm:py-24">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
          <h2 class="text-base font-semibold text-indigo-600">Why members join</h2>
          <p class="mt-2 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Fewer deals, each one worked through.</p>
        </div>
        <dl class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <div v-for="benefit in benefits" :key="benefit.title" class="rounded-2xl bg-gray-50 p-6 ring-1 ring-gray-200">
            <dt>
              <span class="grid size-11 place-items-center rounded-lg bg-indigo-600 text-white">
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="benefit.icon" />
                </svg>
              </span>
              <span class="mt-5 block text-lg font-semibold text-gray-900">{{ benefit.title }}</span>
            </dt>
            <dd class="mt-2 text-sm/6 text-gray-600">{{ benefit.body }}</dd>
          </div>
        </dl>
      </div>
    </section>

    <!-- Deals -->
    <section id="deals" class="bg-gray-50 py-20 sm:py-24">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-6">
          <div class="max-w-2xl">
            <h2 class="text-base font-semibold text-indigo-600">On the board now</h2>
            <p class="mt-2 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Published deals</p>
            <p class="mt-4 text-base text-gray-600">A look at what members are seeing. The address, comparables, tenancy and pack are for members.</p>
          </div>
          <NuxtLink v-if="!user" to="/login" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">Member? Log in <span aria-hidden="true">→</span></NuxtLink>
        </div>

        <div v-if="deals.length" class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <TeaserCard v-for="deal in deals" :key="deal.id" :deal="deal" :locked="!user?.can_see_board" />
        </div>

        <div v-else class="mt-12 overflow-hidden rounded-2xl bg-indigo-600 px-6 py-12 text-center shadow-sm sm:px-12">
          <p class="text-2xl font-bold text-white">The next deals are being appraised.</p>
          <p class="mx-auto mt-3 max-w-xl text-indigo-100">New deals go up as soon as John has edited them. Request an invite and you will see them as they land.</p>
          <NuxtLink to="/request-invite" class="mt-8 inline-flex rounded-md bg-white px-5 py-3 text-sm font-semibold text-indigo-600 shadow-sm hover:bg-indigo-50">Request an invite</NuxtLink>
        </div>
      </div>
    </section>

    <!-- Pricing -->
    <section id="pricing" class="bg-white py-20 sm:py-24">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-3xl overflow-hidden rounded-3xl ring-1 ring-gray-200 lg:flex lg:max-w-none">
          <div class="p-8 sm:p-10 lg:flex-auto">
            <h2 class="text-3xl font-bold tracking-tight text-gray-900">Membership</h2>
            <p class="mt-4 text-base/7 text-gray-600">One plan. The room is kept small so every member gets a proper look at each deal.</p>
            <ul role="list" class="mt-8 grid gap-4 text-sm/6 text-gray-700 sm:grid-cols-2">
              <li v-for="item in [`${price} per month, ex VAT`, 'Nothing else to pay', `${term}-month minimum term`, `${cap} places in total`, 'Full appraisal and PDF pack on every deal', 'Membership by invitation']" :key="item" class="flex gap-3">
                <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                </svg>
                {{ item }}
              </li>
            </ul>
          </div>
          <div class="bg-gray-50 p-8 text-center ring-1 ring-gray-200 sm:p-10 lg:w-96 lg:flex-none">
            <p class="text-base font-semibold text-gray-600">Per month</p>
            <p class="mt-4 flex items-baseline justify-center gap-x-2">
              <span class="text-5xl font-bold tracking-tight text-gray-900">{{ price }}</span>
              <span class="text-sm font-semibold text-gray-600">ex VAT</span>
            </p>
            <p class="mt-2 text-sm text-gray-500">{{ term }}-month minimum · {{ cap }} places</p>
            <NuxtLink to="/request-invite" class="mt-8 block w-full rounded-md bg-indigo-600 px-3 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Request an invite</NuxtLink>
            <NuxtLink v-if="!user" to="/login" class="mt-3 block text-sm font-semibold text-gray-700 hover:text-gray-900">Already a member? Log in</NuxtLink>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
