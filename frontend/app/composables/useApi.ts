export function useApi() {
  const config = useRuntimeConfig()
  const auth = useAuthStore()

  async function request<T>(
    path: string,
    options: {
      method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
      body?: Record<string, unknown> | unknown[]
      query?: Record<string, string | number | boolean | undefined | null>
    } = {},
  ): Promise<T> {
    const headers: Record<string, string> = {
      Accept: 'application/json',
    }

    if (auth.token) {
      headers.Authorization = `Bearer ${auth.token}`
    }

    try {
      return await $fetch<T>(`${config.public.apiBase}${path}`, {
        method: options.method ?? 'GET',
        headers,
        body: options.body,
        query: options.query,
      })
    } catch (error: any) {
      if (error?.response?.status === 401) {
        auth.clearSession()
        if (import.meta.client && window.location.pathname !== '/login' && window.location.pathname !== '/register') {
          await navigateTo('/login')
        }
      }
      throw error
    }
  }

  return { request }
}
