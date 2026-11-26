import { defineStore } from 'pinia'
import type { AppSettingsItem, NotificationLogItem } from '~/types/regain'

export const useSettingsStore = defineStore('settings', () => {
  const settings = ref<AppSettingsItem | null>(null)
  const notificationLogs = ref<NotificationLogItem[]>([])
  const activeSubscriptionsCount = ref(0)
  const loading = ref(false)

  async function fetchSettings() {
    loading.value = true
    const api = useApi()
    try {
      const res = await api.request<{ data: AppSettingsItem }>('/settings')
      settings.value = res.data
    } finally {
      loading.value = false
    }
  }

  async function updateSettings(payload: Partial<AppSettingsItem>) {
    const api = useApi()
    const res = await api.request<{ data: AppSettingsItem }>('/settings', {
      method: 'PUT',
      body: payload,
    })
    settings.value = res.data
    if (res.data.theme === 'dark' || res.data.theme === 'light') {
      const { applyTheme } = useTheme()
      applyTheme(res.data.theme)
    }
    return res.data
  }

  async function fetchNotificationLogs() {
    const api = useApi()
    const res = await api.request<{ data: NotificationLogItem[]; active_subscriptions_count: number }>('/notifications')
    notificationLogs.value = res.data
    activeSubscriptionsCount.value = res.active_subscriptions_count
  }

  async function sendTestNotification() {
    const api = useApi()
    const res = await api.request<{ message: string }>('/notifications/test', { method: 'POST' })
    await fetchNotificationLogs()
    return res
  }

  return {
    settings,
    notificationLogs,
    activeSubscriptionsCount,
    loading,
    fetchSettings,
    updateSettings,
    fetchNotificationLogs,
    sendTestNotification,
  }
})
