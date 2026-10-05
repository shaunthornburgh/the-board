export default defineNuxtRouteMiddleware((to) => {
  const { user } = useAuth()

  if (!user.value) {
    return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
  }
})
