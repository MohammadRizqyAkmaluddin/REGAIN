<script setup lang="ts">
import { useOnline } from '@vueuse/core'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const finance = useFinanceStore()
const online = useOnline()
const { $pwa } = useNuxtApp()

const navItems = [
  { to: '/', label: 'Beranda', icon: 'home' },
  { to: '/transactions', label: 'Transaksi', icon: 'list' },
  { to: '/smoke-free', label: 'Bebas Rokok', icon: 'flame' },
  { to: '/goals', label: 'Target', icon: 'target' },
  { to: '/settings', label: 'Pengaturan', icon: 'settings' },
]

const showShell = computed(() => auth.isAuthenticated && !['/login', '/register'].includes(route.path))

// Smooth scroll-shrink bottom navbar state (jitter-free & overscroll-safe)
const isNavCompact = ref(false)
let lastScrollY = 0
let scrollAccum = 0
let scrollDirection: 'up' | 'down' | null = null
let rafId: number | null = null

function updateNavOnScroll() {
  rafId = null
  const docEl = document.documentElement
  const maxScroll = Math.max(0, docEl.scrollHeight - window.innerHeight)

  // Keep navbar at default size on short pages that don't meaningfully scroll
  if (maxScroll < 48) {
    isNavCompact.value = false
    lastScrollY = 0
    scrollAccum = 0
    return
  }

  const currentY = Math.max(0, Math.min(window.scrollY || docEl.scrollTop || 0, maxScroll))
  const delta = currentY - lastScrollY

  if (currentY <= 16) {
    isNavCompact.value = false
    scrollAccum = 0
    scrollDirection = null
  } else if (currentY >= maxScroll - 8) {
    // Ignore iOS rubber-band bounce at the very bottom
  } else if (delta > 0) {
    if (scrollDirection !== 'down') {
      scrollDirection = 'down'
      scrollAccum = 0
    }
    scrollAccum += delta
    if (scrollAccum >= 24 && currentY > 40) {
      isNavCompact.value = true
    }
  } else if (delta < 0) {
    if (scrollDirection !== 'up') {
      scrollDirection = 'up'
      scrollAccum = 0
    }
    scrollAccum += Math.abs(delta)
    if (scrollAccum >= 18) {
      isNavCompact.value = false
    }
  }

  lastScrollY = currentY
}

function handleScroll() {
  if (rafId === null) {
    rafId = window.requestAnimationFrame(updateNavOnScroll)
  }
}

// Horizontal swipe between bottom-navbar tabs (mobile)
let touchStartX = 0
let touchStartY = 0
let touchStartTime = 0
let touchEligible = false

function onTouchStart(e: TouchEvent) {
  if (e.touches.length !== 1) {
    touchEligible = false
    return
  }
  const touch = e.touches[0]
  if (!touch) return

  // Ignore iOS system edge-swipe zones and interactive form controls
  const screenW = window.innerWidth
  if (touch.clientX < 24 || touch.clientX > screenW - 24) {
    touchEligible = false
    return
  }
  const target = e.target as HTMLElement | null
  if (target?.closest('input, textarea, select, [data-no-swipe]')) {
    touchEligible = false
    return
  }

  touchStartX = touch.clientX
  touchStartY = touch.clientY
  touchStartTime = Date.now()
  touchEligible = true
}

function onTouchEnd(e: TouchEvent) {
  if (!touchEligible || e.changedTouches.length !== 1) return
  touchEligible = false

  const touch = e.changedTouches[0]
  if (!touch) return

  const dx = touch.clientX - touchStartX
  const dy = touch.clientY - touchStartY
  const elapsed = Date.now() - touchStartTime

  // Require clear horizontal swipe gesture
  if (elapsed > 600 || Math.abs(dx) < 56 || Math.abs(dx) < Math.abs(dy) * 1.45) {
    return
  }

  const currentIdx = navItems.findIndex(item => item.to === route.path)
  if (currentIdx === -1) return

  const nextIdx = dx < 0 ? currentIdx + 1 : currentIdx - 1
  const nextItem = navItems[nextIdx]
  if (nextItem) {
    router.replace(nextItem.to)
  }
}

function navigateTab(path: string) {
  if (route.path === path) return
  router.replace(path)
}

watch(
  () => route.path,
  () => {
    isNavCompact.value = false
    lastScrollY = 0
    scrollAccum = 0
    scrollDirection = null
    if (import.meta.client) {
      window.scrollTo({ top: 0, behavior: 'instant' as ScrollBehavior })
    }
  },
)

onMounted(async () => {
  lastScrollY = window.scrollY || 0
  window.addEventListener('scroll', handleScroll, { passive: true })

  if (auth.isAuthenticated) {
    await finance.loadPendingOffline()
    if (online.value && finance.pendingOffline.length > 0) {
      await finance.syncPendingOffline()
    }
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', handleScroll)
  if (rafId !== null) {
    window.cancelAnimationFrame(rafId)
  }
})

watch(online, async (isNowOnline) => {
  if (isNowOnline && auth.isAuthenticated && finance.pendingOffline.length > 0) {
    await finance.syncPendingOffline()
  }
})

async function reloadForUpdate() {
  await ($pwa as any)?.updateServiceWorker?.(true)
}
</script>

<template>
  <div class="min-h-dvh bg-[var(--bg-canvas)] text-[var(--text-primary)]">
    <!-- PWA Update Prompt -->
    <div
      v-if="($pwa as any)?.needRefresh"
      class="safe-top-fixed fixed left-1/2 z-50 flex -translate-x-1/2 items-center gap-3 rounded-full border border-white/15 bg-[#313131] px-4 py-2 text-xs font-medium text-white shadow-xl"
    >
      <span>Pembaruan Deep Pockets tersedia</span>
      <button
        type="button"
        class="rounded-full bg-white px-3 py-1 font-semibold text-[#313131] hover:bg-zinc-200"
        @click="reloadForUpdate"
      >
        Perbarui
      </button>
    </div>

    <div v-if="showShell" class="mx-auto flex min-h-dvh max-w-6xl">
      <!-- Desktop Sidebar -->
      <aside class="hidden w-64 shrink-0 border-r border-[var(--border-subtle)] p-5 md:flex md:flex-col md:justify-between">
        <div>
          <div class="mb-8 flex items-center gap-3 px-2">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center">
              <img
                src="/logos/dark-transparent-logo.png"
                alt="Deep Pockets"
                class="h-9 w-9 object-contain dark:hidden"
              >
              <img
                src="/logos/light-transparent-logo.png"
                alt="Deep Pockets"
                class="hidden h-9 w-9 object-contain dark:block"
              >
            </div>
            <div>
              <div class="text-base font-bold tracking-tight">Deep Pockets</div>
              <div class="text-xs text-[var(--text-secondary)]">Keuangan &amp; Bebas Rokok</div>
            </div>
          </div>

          <nav class="space-y-1.5">
            <button
              v-for="item in navItems"
              :key="item.to"
              type="button"
              class="flex w-full items-center gap-3 rounded-xl px-3.5 py-2.5 text-left text-sm font-medium transition-colors"
              :class="route.path === item.to
                ? 'bg-[#313131] text-white dark:bg-white/12 dark:text-white'
                : 'text-[var(--text-secondary)] hover:bg-[var(--bg-elevated)] hover:text-[var(--text-primary)]'"
              @click="navigateTab(item.to)"
            >
              <svg v-if="item.icon === 'home'" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5.5v-6.5h-5V21H4a1 1 0 0 1-1-1v-9.5Z" /></svg>
              <svg v-else-if="item.icon === 'list'" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10" /></svg>
              <svg v-else-if="item.icon === 'flame'" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c.5 4.5-3 6.5-3 10a4 4 0 0 0 8 0c0-3.5-2.5-6-5-10Z" /></svg>
              <svg v-else-if="item.icon === 'target'" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8" /><circle cx="12" cy="12" r="4" /><circle cx="12" cy="12" r="1" fill="currentColor" /></svg>
              <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Zm8 3.5-1.9-.7a6.6 6.6 0 0 0-.6-1.5l.9-1.8-1.9-1.9-1.8.9a6.6 6.6 0 0 0-1.5-.6L12 4l-1.2 1.9a6.6 6.6 0 0 0-1.5.6l-1.8-.9-1.9 1.9.9 1.8a6.6 6.6 0 0 0-.6 1.5L4 12l1.9 1.2c.1.5.3 1 .6 1.5l-.9 1.8 1.9 1.9 1.8-.9c.5.3 1 .5 1.5.6L12 20l1.2-1.9c.5-.1 1-.3 1.5-.6l1.8.9 1.9-1.9-.9-1.8c.3-.5.5-1 .6-1.5L20 12Z" /></svg>
              <span>{{ item.label }}</span>
            </button>
          </nav>
        </div>

        <div class="rounded-2xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] p-3.5 text-xs">
          <div class="font-semibold text-[var(--text-primary)]">{{ auth.user?.name || 'Pengguna' }}</div>
          <div class="mt-0.5 truncate text-[var(--text-secondary)]">{{ auth.user?.email }}</div>
        </div>
      </aside>

      <!-- Main Content (supports mobile left/right tab swipe) -->
      <div
        class="flex min-w-0 flex-1 flex-col"
        @touchstart.passive="onTouchStart"
        @touchend.passive="onTouchEnd"
      >
        <!-- Connectivity & Offline Queue Banner -->
        <div
          v-if="!online || finance.pendingOffline.length > 0"
          class="flex items-center justify-between gap-3 border-b border-[var(--border-subtle)] bg-[var(--bg-elevated)] px-4 pb-2.5 text-xs text-[var(--text-primary)]"
          style="padding-top: calc(env(safe-area-inset-top, 0px) + 0.625rem);"
        >
          <div class="flex items-center gap-2">
            <span class="h-2 w-2 rounded-full" :class="online ? 'bg-zinc-500' : 'bg-zinc-900 dark:bg-zinc-200'" />
            <span v-if="!online">Mode offline — transaksi baru disimpan lokal &amp; belum tersinkronisasi ke server.</span>
            <span v-else>{{ finance.pendingOffline.length }} transaksi offline menunggu sinkronisasi.</span>
          </div>
          <button
            v-if="online && finance.pendingOffline.length > 0"
            type="button"
            class="btn-primary shrink-0 rounded-lg px-2.5 py-1 font-semibold disabled:opacity-50"
            :disabled="finance.syncingOffline"
            @click="finance.syncPendingOffline()"
          >
            {{ finance.syncingOffline ? 'Menyinkronkan...' : 'Sinkronkan' }}
          </button>
        </div>

        <main class="safe-top-shell mx-auto w-full max-w-4xl flex-1 px-0 md:px-8">
          <slot />
        </main>
      </div>

      <!-- Mobile Floating Pill Bottom Navigation (Fixed consistent anchor across all pages) -->
      <nav
        aria-label="Navigasi utama"
        class="mobile-nav-anchor md:hidden"
      >
        <div
          class="liquid-glass-nav pointer-events-auto grid w-full max-w-[344px] grid-cols-5 items-center rounded-full p-1.5"
          :class="{ 'is-compact': isNavCompact }"
        >
          <button
            v-for="item in navItems"
            :key="item.to"
            type="button"
            :aria-label="item.label"
            :title="item.label"
            class="flex h-11 items-center justify-center rounded-full transition-all duration-200 active:scale-90"
            :class="route.path === item.to
              ? 'liquid-glass-active-pill text-[#313131] dark:text-white'
              : 'text-zinc-600 hover:text-[#313131] dark:text-zinc-400 dark:hover:text-white'"
            @click="navigateTab(item.to)"
          >
            <!-- Home -->
            <svg
              v-if="item.icon === 'home'"
              class="h-6 w-6"
              :fill="route.path === item.to ? 'currentColor' : 'none'"
              stroke="currentColor"
              :stroke-width="route.path === item.to ? '1.6' : '1.9'"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5.5v-6.5h-5V21H4a1 1 0 0 1-1-1v-9.5Z" />
            </svg>

            <!-- Transactions -->
            <svg
              v-else-if="item.icon === 'list'"
              class="h-6 w-6"
              fill="none"
              stroke="currentColor"
              :stroke-width="route.path === item.to ? '2.4' : '1.9'"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10" />
            </svg>

            <!-- Smoke-free -->
            <svg
              v-else-if="item.icon === 'flame'"
              class="h-6 w-6"
              :fill="route.path === item.to ? 'currentColor' : 'none'"
              stroke="currentColor"
              :stroke-width="route.path === item.to ? '1.6' : '1.9'"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c.5 4.5-3 6.5-3 10a4 4 0 0 0 8 0c0-3.5-2.5-6-5-10Z" />
            </svg>

            <!-- Goals / Target -->
            <svg
              v-else-if="item.icon === 'target'"
              class="h-6 w-6"
              fill="none"
              stroke="currentColor"
              :stroke-width="route.path === item.to ? '2.2' : '1.9'"
              viewBox="0 0 24 24"
            >
              <circle cx="12" cy="12" r="8" />
              <circle cx="12" cy="12" r="4" />
              <circle cx="12" cy="12" r="1.5" fill="currentColor" />
            </svg>

            <!-- Settings -->
            <svg
              v-else
              class="h-6 w-6"
              :fill="route.path === item.to ? 'currentColor' : 'none'"
              stroke="currentColor"
              :stroke-width="route.path === item.to ? '1.6' : '1.9'"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Zm8 3.5-1.9-.7a6.6 6.6 0 0 0-.6-1.5l.9-1.8-1.9-1.9-1.8.9a6.6 6.6 0 0 0-1.5-.6L12 4l-1.2 1.9a6.6 6.6 0 0 0-1.5.6l-1.8-.9-1.9 1.9.9 1.8a6.6 6.6 0 0 0-.6 1.5L4 12l1.9 1.2c.1.5.3 1 .6 1.5l-.9 1.8 1.9 1.9 1.8-.9c.5.3 1 .5 1.5.6L12 20l1.2-1.9c.5-.1 1-.3 1.5-.6l1.8.9 1.9-1.9-.9-1.8c.3-.5.5-1 .6-1.5L20 12Z" />
            </svg>
          </button>
        </div>
      </nav>
    </div>

    <div v-else>
      <slot />
    </div>
  </div>
</template>
