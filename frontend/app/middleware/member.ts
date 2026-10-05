// Deal pages and the board are for members. Logged-out visitors sign in and come straight back.
export default defineNuxtRouteMiddleware((to) => {
  const { user } = useAuth()

  if (!user.value) {
    return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
  }

  if (!user.value.can_see_board) {
    return navigateTo('/settings')
  }
})
