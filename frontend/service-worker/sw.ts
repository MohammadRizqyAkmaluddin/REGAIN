/// <reference lib="webworker" />
import { cleanupOutdatedCaches, precacheAndRoute } from 'workbox-precaching'
import { NavigationRoute, registerRoute } from 'workbox-routing'
import { NetworkOnly } from 'workbox-strategies'

declare let self: ServiceWorkerGlobalScope

// Precache static app shell assets injected by Workbox
cleanupOutdatedCaches()
precacheAndRoute(self.__WB_MANIFEST || [])

// NEVER cache sensitive financial API responses in the Service Worker cache
registerRoute(
  ({ url }) => url.pathname.startsWith('/api/'),
  new NetworkOnly(),
)

// App shell fallback for SPA navigation when offline
registerRoute(
  new NavigationRoute(async ({ request }) => {
    try {
      return await fetch(request)
    } catch {
      const cached = await caches.match('/index.html') || await caches.match('/')
      return cached || new Response('Offline — Deep Pockets', { status: 503, headers: { 'Content-Type': 'text/plain' } })
    }
  }, {
    denylist: [/^\/api\//],
  }),
)

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting()
  }
})

// Web Push Notification handler (works when PWA is closed)
self.addEventListener('push', (event: PushEvent) => {
  if (!event.data) return

  let data: { title?: string; body?: string; tag?: string; url?: string; icon?: string; badge?: string } = {}
  try {
    data = event.data.json()
  } catch {
    data = { title: 'Deep Pockets', body: event.data.text() }
  }

  const title = data.title || 'Deep Pockets'
  const options: NotificationOptions = {
    body: data.body || 'Setiap rokok yang tidak kamu beli adalah uang untuk masa depanmu.',
    icon: data.icon || '/icons/icon-192.png',
    badge: data.badge || '/icons/icon-192.png',
    tag: data.tag || 'regain-motivation',
    data: { url: data.url || '/smoke-free' },
  }

  event.waitUntil(self.registration.showNotification(title, options))
})

self.addEventListener('notificationclick', (event: NotificationEvent) => {
  event.notification.close()
  const targetUrl = (event.notification.data as { url?: string } | undefined)?.url || '/'

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if ('focus' in client) {
          client.navigate(targetUrl)
          return client.focus()
        }
      }
      return self.clients.openWindow(targetUrl)
    }),
  )
})
