function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
  const rawData = window.atob(base64)
  const outputArray = new Uint8Array(rawData.length)
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i)
  }
  return outputArray
}

export function usePushNotifications() {
  const api = useApi()
  const isSupported = computed(() => {
    if (!import.meta.client) return false
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
  })

  const permission = ref<NotificationPermission>(
    import.meta.client && 'Notification' in window ? Notification.permission : 'default',
  )
  const isSubscribed = ref(false)
  const busy = ref(false)
  const statusMessage = ref<string | null>(null)

  async function checkSubscription() {
    if (!isSupported.value) return
    permission.value = Notification.permission
    try {
      const reg = await navigator.serviceWorker.ready
      const sub = await reg.pushManager.getSubscription()
      isSubscribed.value = Boolean(sub)
    } catch {
      isSubscribed.value = false
    }
  }

  async function subscribe(): Promise<boolean> {
    if (!isSupported.value) {
      statusMessage.value = 'Perangkat atau browser ini belum mendukung Web Push.'
      return false
    }

    busy.value = true
    statusMessage.value = null
    try {
      const result = await Notification.requestPermission()
      permission.value = result
      if (result !== 'granted') {
        statusMessage.value = 'Izin notifikasi belum diberikan.'
        return false
      }

      const vapid = await api.request<{ public_key: string; configured: boolean }>('/push/vapid-public-key')
      if (!vapid.configured || !vapid.public_key) {
        statusMessage.value = 'Server belum mengonfigurasi VAPID public key.'
        return false
      }

      const reg = await navigator.serviceWorker.ready
      let sub = await reg.pushManager.getSubscription()
      if (!sub) {
        sub = await reg.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(vapid.public_key) as BufferSource,
        })
      }

      const json = sub.toJSON()
      await api.request('/push/subscriptions', {
        method: 'POST',
        body: {
          endpoint: sub.endpoint,
          keys: {
            p256dh: json.keys?.p256dh ?? '',
            auth: json.keys?.auth ?? '',
          },
          content_encoding: 'aes128gcm',
        },
      })

      isSubscribed.value = true
      statusMessage.value = 'Notifikasi motivasi aktif pada perangkat ini.'
      return true
    } catch (err: any) {
      statusMessage.value = err?.data?.message || err?.message || 'Gagal mengaktifkan notifikasi push.'
      return false
    } finally {
      busy.value = false
    }
  }

  async function unsubscribe(): Promise<boolean> {
    if (!isSupported.value) return false
    busy.value = true
    statusMessage.value = null
    try {
      const reg = await navigator.serviceWorker.ready
      const sub = await reg.pushManager.getSubscription()
      if (sub) {
        await api.request('/push/subscriptions', {
          method: 'DELETE',
          body: { endpoint: sub.endpoint },
        })
        await sub.unsubscribe()
      }
      isSubscribed.value = false
      statusMessage.value = 'Notifikasi push dinonaktifkan pada perangkat ini.'
      return true
    } catch (err: any) {
      statusMessage.value = err?.data?.message || 'Gagal menonaktifkan notifikasi.'
      return false
    } finally {
      busy.value = false
    }
  }

  return {
    isSupported,
    permission,
    isSubscribed,
    busy,
    statusMessage,
    checkSubscription,
    subscribe,
    unsubscribe,
  }
}
