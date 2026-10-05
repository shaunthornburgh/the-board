import type { NitroFetchOptions } from 'nitropack'

type Options = NitroFetchOptions<string> & { method?: 'GET' | 'POST' | 'PUT' | 'DELETE' }

/**
 * The Sanctum token. It lives in shared state for this app instance and is mirrored to a cookie the site
 * owns, so server rendering can send it too. "Keep me signed in" keeps the cookie for 30 days; otherwise
 * it ends with the browser session.
 */
export function useToken() {
  const remembered = useCookie<string | null>('board_token', { sameSite: 'lax', maxAge: 60 * 60 * 24 * 30, default: () => null })
  const session = useCookie<string | null>('board_token', { sameSite: 'lax', default: () => null })
  const token = useState<string | null>('board_token', () => remembered.value ?? null)

  return {
    token,
    set(value: string, remember: boolean) {
      token.value = value
      ;(remember ? remembered : session).value = value
    },
    clear() {
      token.value = null
      remembered.value = null
    },
  }
}

/** Where to reach the API: the internal address while rendering on the server, the public one in the browser. */
export function apiBase() {
  const config = useRuntimeConfig()
  return import.meta.server ? config.apiBase : config.public.apiBase
}

/** Calls the Laravel API with the signed-in user's Bearer token. */
export function useApi() {
  const base = apiBase()
  const { token } = useToken()

  return function api<T>(path: string, options: Options = {}): Promise<T> {
    const headers: Record<string, string> = { accept: 'application/json', ...(options.headers as Record<string, string>) }
    if (token.value) headers.authorization = `Bearer ${token.value}`

    return $fetch<T>(`${base}${path}`, { ...options, headers } as never)
  }
}

/** Download a file the API only hands out with a token, such as a deal pack. */
export function useDownload() {
  const api = useApi()

  return async function download(path: string, filename: string) {
    const blob = await api<Blob>(path, { responseType: 'blob', headers: { accept: 'application/pdf' } })
    const url = URL.createObjectURL(blob)
    const link = Object.assign(document.createElement('a'), { href: url, download: filename })
    document.body.append(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  }
}

/** Laravel validation errors, flattened to the first message per field. */
export function validationErrors(error: unknown): Record<string, string> {
  const data = (error as { data?: { errors?: Record<string, string[]>; message?: string } })?.data
  if (data?.errors) {
    return Object.fromEntries(Object.entries(data.errors).map(([key, messages]) => [key, messages[0]]))
  }
  return { form: data?.message || 'Something went wrong. Try again.' }
}
