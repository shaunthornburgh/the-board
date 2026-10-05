export default defineNuxtPlugin(async () => {
  const { me, refresh } = useAuth()
  const token = useToken()

  if (!me.value) {
    await refresh().catch(() => {
      me.value = null
    })
  }

  // A token the API no longer accepts (expired, revoked, logged out elsewhere) is dropped.
  if (token.token.value && !me.value?.user) {
    token.clear()
  }
})
