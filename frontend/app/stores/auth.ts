import { defineStore } from 'pinia'
import type { AppSettingsItem, User } from '~/types/regain'

const TOKEN_KEY = 'regain_auth_token'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(import.meta.client ? localStorage.getItem(TOKEN_KEY) : null)
  const user = ref<User | null>(null)
  const loading = ref(false)
  const initialized = ref(false)

  const isAuthenticated = computed(() => Boolean(token.value))

  function setToken(nextToken: string) {
    token.value = nextToken
    if (import.meta.client) {
      localStorage.setItem(TOKEN_KEY, nextToken)
    }
  }

  function clearSession() {
    token.value = null
    user.value = null
    if (import.meta.client) {
      localStorage.removeItem(TOKEN_KEY)
    }
  }

  async function fetchMe() {
    if (!token.value) {
      initialized.value = true
      return
    }
    const api = useApi()
    try {
      const res = await api.request<{ user: User; settings?: AppSettingsItem }>('/auth/me')
      user.value = res.user
      if (res.settings?.theme && (res.settings.theme === 'dark' || res.settings.theme === 'light')) {
        const { applyTheme } = useTheme()
        applyTheme(res.settings.theme)
      }
    } catch {
      clearSession()
    } finally {
      initialized.value = true
    }
  }

  async function login(payload: { email: string; password: string }) {
    loading.value = true
    const api = useApi()
    try {
      const res = await api.request<{ user: User; token: string }>('/auth/login', {
        method: 'POST',
        body: payload,
      })
      setToken(res.token)
      user.value = res.user
      initialized.value = true
      return res
    } finally {
      loading.value = false
    }
  }

  async function register(payload: {
    name: string
    email: string
    password: string
    password_confirmation: string
    opening_balance?: number
    quit_date?: string
  }) {
    loading.value = true
    const api = useApi()
    try {
      const res = await api.request<{ user: User; token: string }>('/auth/register', {
        method: 'POST',
        body: payload,
      })
      setToken(res.token)
      user.value = res.user
      initialized.value = true
      return res
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    const api = useApi()
    try {
      if (token.value) {
        await api.request('/auth/logout', { method: 'POST' })
      }
    } catch {
      // Ignore network errors during logout
    } finally {
      clearSession()
    }
  }

  return {
    token,
    user,
    loading,
    initialized,
    isAuthenticated,
    setToken,
    clearSession,
    fetchMe,
    login,
    register,
    logout,
  }
})
