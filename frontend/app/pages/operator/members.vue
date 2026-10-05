<script setup lang="ts">
import type { InviteRequest, OperatorMember } from '~/utils/types'

definePageMeta({ middleware: 'operator' })
useHead({ title: 'Members · Operator' })

const api = useApi()
const flash = useFlash()

interface Members {
  members: OperatorMember[]
  requests: InviteRequest[]
  places_taken: number
  room_cap: number
  price_per_month: number
  minimum_term_months: number
}

const { data, refresh } = await useAsyncData('operator-members', () => api<Members>('/api/operator/members'))

const full = computed(() => !!data.value && data.value.places_taken >= data.value.room_cap)

const invite = reactive({ name: '', email: '' })
const inviteErrors = ref<Record<string, string>>({})
const inviting = ref(false)
const newLink = ref<string | null>(null)

async function sendInvite() {
  inviting.value = true
  inviteErrors.value = {}
  try {
    const result = await api<{ message: string; member: OperatorMember }>('/api/operator/members', { method: 'POST', body: invite })
    flash.show(result.message)
    newLink.value = result.member.invite_link
    invite.name = ''
    invite.email = ''
    await refresh()
  } catch (error) {
    inviteErrors.value = validationErrors(error)
    if (inviteErrors.value.form) flash.show(inviteErrors.value.form, 'error')
  } finally {
    inviting.value = false
  }
}

/** Fill the invite form from a request on the homepage form. */
function fillFromRequest(request: InviteRequest) {
  invite.name = request.name
  invite.email = request.email
  document.getElementById('invite-name')?.focus()
}

const busy = ref<number | null>(null)

async function act(member: OperatorMember, action: 'invited' | 'active' | 'revoke') {
  busy.value = member.id
  newLink.value = null
  try {
    const result = await api<{ message: string }>(`/api/operator/members/${member.id}/${action}`, { method: 'POST' })
    flash.show(result.message)
    await refresh()
  } catch (error) {
    flash.fail(error)
  } finally {
    busy.value = null
  }
}

const copied = ref<string | null>(null)
async function copy(link: string) {
  await navigator.clipboard?.writeText(link)
  copied.value = link
  setTimeout(() => (copied.value = null), 1500)
}

const statusClass: Record<string, string> = {
  active: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
  invited: 'bg-gray-50 text-gray-600 ring-gray-500/20',
  revoked: 'bg-red-50 text-red-700 ring-red-600/20',
}

const field = 'mt-2 block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600'
const small = 'rounded-md bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 ring-inset hover:bg-gray-50 disabled:opacity-60'
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <h1 class="text-3xl font-bold tracking-tight text-gray-900">Operator</h1>
    <OperatorNav class="mt-6" />

    <div v-if="data" class="mt-8 grid gap-8 lg:grid-cols-3">
      <div class="lg:col-span-2">
        <p class="text-sm text-gray-600">
          <span class="font-semibold text-gray-900">{{ data.places_taken }} of {{ data.room_cap }} places taken.</span>
          Invited and active members both hold a place. {{ money(data.price_per_month) }} per month ex VAT, {{ data.minimum_term_months }}-month minimum. Nothing is emailed: send each new member their invite link yourself.
        </p>

        <FlashMessage class="mt-6" :message="flash.message.value" :tone="flash.tone.value" @close="flash.clear()" />

        <div v-if="newLink" class="mt-4 rounded-lg bg-indigo-50 p-4 text-sm ring-1 ring-indigo-100">
          <p class="font-medium text-indigo-900">Invite link</p>
          <div class="mt-2 flex items-center gap-2">
            <code class="flex-1 truncate rounded bg-white px-2 py-1 text-xs text-gray-700 ring-1 ring-indigo-100 select-all">{{ newLink }}</code>
            <button type="button" :class="small" @click="copy(newLink)">{{ copied === newLink ? 'Copied' : 'Copy' }}</button>
          </div>
        </div>

        <div class="mt-6 overflow-x-auto rounded-xl ring-1 ring-gray-200">
          <table class="min-w-full divide-y divide-gray-200 bg-white text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-600">
              <tr>
                <th class="px-4 py-3">Member</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Invited</th>
                <th class="px-4 py-3">Active since</th>
                <th class="px-4 py-3">Term ends</th>
                <th class="px-4 py-3" />
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-if="!data.members.length">
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">No members yet. Invite someone using the form.</td>
              </tr>
              <tr v-for="member in data.members" :key="member.id" class="align-top">
                <td class="px-4 py-3">
                  <p class="font-medium text-gray-900">{{ member.name }}</p>
                  <p class="text-xs text-gray-500">{{ member.email }}</p>
                  <div v-if="member.invite_link" class="mt-1.5 flex items-center gap-2">
                    <code class="max-w-56 truncate text-[11px] text-gray-400 select-all">{{ member.invite_link }}</code>
                    <button type="button" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500" @click="copy(member.invite_link)">{{ copied === member.invite_link ? 'Copied' : 'Copy link' }}</button>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <span class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ring-1 ring-inset" :class="statusClass[member.status]">{{ member.status }}</span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ day(member.invited_at) }}</td>
                <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ day(member.activated_at) }}</td>
                <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ day(member.minimum_term_ends_at) }}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                  <button v-if="member.status !== 'invited'" type="button" :disabled="busy === member.id" :class="[small, 'mr-1.5']" @click="act(member, 'invited')">Mark invited</button>
                  <button v-if="member.status !== 'active'" type="button" :disabled="busy === member.id" :class="[small, 'mr-1.5']" @click="act(member, 'active')">Set active</button>
                  <button v-if="member.status !== 'revoked'" type="button" :disabled="busy === member.id" :class="[small, 'text-red-700']" @click="act(member, 'revoke')">Revoke</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="space-y-8">
        <form class="space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200" @submit.prevent="sendInvite">
          <h2 class="text-base font-semibold text-gray-900">Invite a member</h2>
          <div>
            <label for="invite-name" class="block text-sm font-medium text-gray-900">Name</label>
            <input id="invite-name" v-model="invite.name" required :class="field" />
            <p v-if="inviteErrors.name" class="mt-2 text-sm text-red-600">{{ inviteErrors.name }}</p>
          </div>
          <div>
            <label for="invite-email" class="block text-sm font-medium text-gray-900">Email</label>
            <input id="invite-email" v-model="invite.email" type="email" required :class="field" />
            <p v-if="inviteErrors.email" class="mt-2 text-sm text-red-600">{{ inviteErrors.email }}</p>
          </div>
          <button type="submit" :disabled="inviting || full" class="w-full rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">
            {{ full ? 'The room is full' : inviting ? 'Inviting…' : 'Create invite link' }}
          </button>
        </form>

        <section>
          <h2 class="text-base font-semibold text-gray-900">Invite requests <span class="font-normal text-gray-500">· {{ data.requests.length }}</span></h2>
          <p class="mt-1 text-sm text-gray-600">From “Request an invite” on the homepage.</p>
          <p v-if="!data.requests.length" class="mt-3 rounded-xl bg-gray-50 px-4 py-6 text-center text-sm text-gray-500 ring-1 ring-gray-200">No requests yet.</p>
          <ul v-else class="mt-3 divide-y divide-gray-100 rounded-xl bg-white text-sm ring-1 ring-gray-200">
            <li v-for="request in data.requests" :key="request.id" class="px-4 py-3">
              <div class="flex items-baseline gap-2">
                <p class="font-medium text-gray-900">{{ request.name }}</p>
                <span class="ml-auto text-xs text-gray-400">{{ day(request.created_at) }}</span>
              </div>
              <a :href="`mailto:${request.email}`" class="text-indigo-600 hover:text-indigo-500">{{ request.email }}</a>
              <span v-if="request.phone" class="ml-2 text-gray-500">{{ request.phone }}</span>
              <p v-if="request.message" class="mt-1 whitespace-pre-line text-gray-600">{{ request.message }}</p>
              <button type="button" class="mt-2 text-xs font-semibold text-indigo-600 hover:text-indigo-500" @click="fillFromRequest(request)">Use in invite form</button>
            </li>
          </ul>
        </section>
      </div>
    </div>
  </div>
</template>
