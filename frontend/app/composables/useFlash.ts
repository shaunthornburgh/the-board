/** A message shown at the top of an operator page after an action. */
export function useFlash() {
  const message = ref<string | null>(null)
  const tone = ref<'success' | 'error'>('success')

  function show(text: string, kind: 'success' | 'error' = 'success') {
    message.value = text
    tone.value = kind
  }

  function fail(error: unknown) {
    const data = (error as { data?: { message?: string; errors?: Record<string, string[]> } })?.data
    show(data?.errors ? Object.values(data.errors)[0]![0]! : data?.message || 'Something went wrong. Try again.', 'error')
  }

  return { message, tone, show, fail, clear: () => (message.value = null) }
}
