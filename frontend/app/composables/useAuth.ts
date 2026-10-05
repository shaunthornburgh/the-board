export interface User {
  name: string
  email: string
  role: 'operator' | 'member'
  membership_status: 'invited' | 'active' | 'revoked' | null
  can_see_board: boolean
}

export interface Membership {
  price_per_month: number
  minimum_term_months: number
  room_cap: number
  places_taken: number
}

interface Me {
  user: User | null
  membership: Membership
}

export function useAuth() {
  const me = useState<Me | null>('me', () => null)
  const api = useApi()
  const token = useToken()

  async function refresh() {
    me.value = await api<Me>('/api/me')
  }

  async function login(email: string, password: string, remember: boolean) {
    const result = await api<{ token: string }>('/api/login', { method: 'POST', body: { email, password } })
    token.set(result.token, remember)
    await refresh()
  }

  /** Store a token issued elsewhere, such as when an invite is accepted. */
  async function signIn(value: string) {
    token.set(value, true)
    await refresh()
  }

  async function logout() {
    // Revoke the token on the API, then forget it here even if that call fails.
    await api('/api/logout', { method: 'POST' }).catch(() => {})
    token.clear()
    await refresh()
  }

  return {
    me,
    user: computed(() => me.value?.user ?? null),
    membership: computed(() => me.value?.membership ?? null),
    refresh,
    login,
    signIn,
    logout,
  }
}

/** Where to go after signing in. Only same-site paths are accepted. */
export function safeRedirect(value: unknown, user: User | null): string {
  const target = typeof value === 'string' ? value : ''
  if (target.startsWith('/') && !target.startsWith('//') && !target.startsWith('/login')) return target
  return user?.can_see_board ? '/board' : '/settings'
}
