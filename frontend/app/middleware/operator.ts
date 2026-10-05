// Operator screens. Logged-out visitors sign in first; members get a plain 404, as if the pages did not exist.
export default defineNuxtRouteMiddleware((to) => {
  const { user } = useAuth()

  if (!user.value) {
    return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
  }

  if (user.value.role !== 'operator') {
    return abortNavigation(createError({ statusCode: 404, statusMessage: 'Not found' }))
  }
})
