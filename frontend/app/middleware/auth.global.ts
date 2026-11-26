export default defineNuxtRouteMiddleware(async (to) => {
  const auth = useAuthStore()
  const publicRoutes = ['/login', '/register']

  if (!auth.initialized && auth.token) {
    await auth.fetchMe()
  }

  if (!auth.isAuthenticated && !publicRoutes.includes(to.path)) {
    return navigateTo('/login')
  }

  if (auth.isAuthenticated && publicRoutes.includes(to.path)) {
    return navigateTo('/')
  }
})
