<script setup lang="ts">
import { formatRupiah } from '~/utils/format'

const auth = useAuthStore()
const settingsStore = useSettingsStore()
const finance = useFinanceStore()
const push = usePushNotifications()

const saving = ref(false)
const feedbackMsg = ref<string | null>(null)

const notifForm = reactive({
  notifications_enabled: true,
  active_hours_start: '08:00',
  active_hours_end: '21:00',
  max_daily_notifications: 3,
  notification_cooldown_minutes: 180,
})

const newAccount = reactive({
  name: '',
  type: 'bank' as 'cash' | 'bank' | 'ewallet' | 'investment',
  opening_balance: 0,
})

onMounted(async () => {
  await Promise.all([
    settingsStore.fetchSettings(),
    settingsStore.fetchNotificationLogs(),
    finance.fetchAccounts(),
    push.checkSubscription(),
  ])

  if (settingsStore.settings) {
    notifForm.notifications_enabled = settingsStore.settings.notifications_enabled
    notifForm.active_hours_start = settingsStore.settings.active_hours_start
    notifForm.active_hours_end = settingsStore.settings.active_hours_end
    notifForm.max_daily_notifications = settingsStore.settings.max_daily_notifications
    notifForm.notification_cooldown_minutes = settingsStore.settings.notification_cooldown_minutes
  }
})

async function saveNotificationSettings() {
  saving.value = true
  feedbackMsg.value = null
  try {
    await settingsStore.updateSettings({
      notifications_enabled: notifForm.notifications_enabled,
      active_hours_start: notifForm.active_hours_start,
      active_hours_end: notifForm.active_hours_end,
      max_daily_notifications: Number(notifForm.max_daily_notifications),
      notification_cooldown_minutes: Number(notifForm.notification_cooldown_minutes),
    })
    feedbackMsg.value = 'Pengaturan notifikasi berhasil disimpan.'
  } finally {
    saving.value = false
  }
}

async function sendTestPush() {
  const res = await settingsStore.sendTestNotification()
  feedbackMsg.value = res.message
}

async function addAccount() {
  const api = useApi()
  await api.request('/accounts', {
    method: 'POST',
    body: {
      name: newAccount.name,
      type: newAccount.type,
      opening_balance: Number(newAccount.opening_balance),
    },
  })
  newAccount.name = ''
  newAccount.opening_balance = 0
  await finance.fetchAccounts()
  feedbackMsg.value = 'Akun keuangan baru ditambahkan.'
}

async function handleLogout() {
  await auth.logout()
  await navigateTo('/login')
}
</script>

<template>
  <div class="space-y-4 md:space-y-5">
    <div class="px-4 md:px-0">
      <h1 class="text-xl font-bold tracking-tight">Pengaturan</h1>
      <p class="text-xs text-[var(--text-secondary)]">Akun keuangan dan notifikasi motivasi</p>
    </div>

    <div
      v-if="feedbackMsg"
      class="mx-4 rounded-xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] px-4 py-2.5 text-xs text-[var(--text-primary)] md:mx-0"
    >
      {{ feedbackMsg }}
    </div>

    <!-- Accounts -->
    <section class="card-surface space-y-4 p-5">
      <div>
        <h2 class="text-sm font-semibold">Akun Keuangan Aktual</h2>
        <p class="text-xs text-[var(--text-secondary)]">Saldo awal seluruh akun dihitung dalam Kekayaan Bersih Aktual.</p>
      </div>

      <div class="space-y-2">
        <div
          v-for="acc in finance.accounts"
          :key="acc.id"
          class="flex items-center justify-between rounded-xl bg-[var(--bg-elevated)] px-3.5 py-2.5 text-xs"
        >
          <div>
            <span class="font-semibold">{{ acc.name }}</span>
            <span class="ml-2 text-[var(--text-secondary)] uppercase">{{ acc.type }}</span>
          </div>
          <div class="font-bold tabular-nums">
            {{ formatRupiah(acc.current_balance ?? acc.opening_balance) }}
          </div>
        </div>
      </div>

      <form class="grid gap-2.5 border-t border-[var(--border-subtle)] pt-3 sm:grid-cols-4" @submit.prevent="addAccount">
        <input v-model="newAccount.name" type="text" placeholder="Nama akun (mis. BCA)" class="input-field" required>
        <select v-model="newAccount.type" class="input-field">
          <option value="bank">Bank</option>
          <option value="ewallet">E-Wallet</option>
          <option value="cash">Tunai</option>
          <option value="investment">Investasi</option>
        </select>
        <input v-model.number="newAccount.opening_balance" type="number" min="0" placeholder="Saldo awal" class="input-field tabular-nums">
        <button type="submit" class="btn-primary rounded-xl px-3 py-2 text-xs font-bold">
          + Tambah Akun
        </button>
      </form>
    </section>

    <!-- Web Push Notifications -->
    <section class="card-surface space-y-4 p-5">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 class="text-sm font-semibold">Notifikasi Push Motivasi</h2>
          <p class="text-xs text-[var(--text-secondary)]">
            Perangkat terdaftar: <strong>{{ settingsStore.activeSubscriptionsCount }}</strong> · Zona waktu: Asia/Jakarta
          </p>
        </div>
        <div class="flex gap-2">
          <button
            v-if="!push.isSubscribed.value"
            type="button"
            :disabled="push.busy.value"
            class="btn-primary rounded-xl px-3.5 py-2 text-xs font-bold"
            @click="push.subscribe()"
          >
            Aktifkan di Perangkat Ini
          </button>
          <button
            v-else
            type="button"
            :disabled="push.busy.value"
            class="rounded-xl border border-[var(--border-subtle)] px-3.5 py-2 text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--bg-elevated)]"
            @click="push.unsubscribe()"
          >
            Matikan di Perangkat Ini
          </button>
          <button
            type="button"
            class="rounded-xl border border-[var(--border-subtle)] px-3 py-2 text-xs font-medium hover:bg-[var(--bg-elevated)]"
            @click="sendTestPush"
          >
            Uji Kirim
          </button>
        </div>
      </div>

      <p v-if="push.statusMessage.value" class="text-xs text-[var(--text-secondary)]">
        {{ push.statusMessage.value }}
      </p>

      <form class="space-y-3 border-t border-[var(--border-subtle)] pt-4" @submit.prevent="saveNotificationSettings">
        <label class="flex items-center gap-2.5 text-xs font-medium">
          <input v-model="notifForm.notifications_enabled" type="checkbox" class="h-4 w-4 accent-[#313131]">
          Aktifkan penjadwalan notifikasi motivasi acak
        </label>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <div>
            <label class="mb-1 block text-[11px] text-[var(--text-secondary)]">Jam Aktif Mulai</label>
            <input v-model="notifForm.active_hours_start" type="time" class="input-field">
          </div>
          <div>
            <label class="mb-1 block text-[11px] text-[var(--text-secondary)]">Jam Aktif Selesai</label>
            <input v-model="notifForm.active_hours_end" type="time" class="input-field">
          </div>
          <div>
            <label class="mb-1 block text-[11px] text-[var(--text-secondary)]">Maks / Hari</label>
            <input v-model.number="notifForm.max_daily_notifications" type="number" min="0" max="10" class="input-field tabular-nums">
          </div>
          <div>
            <label class="mb-1 block text-[11px] text-[var(--text-secondary)]">Jeda (Menit)</label>
            <input v-model.number="notifForm.notification_cooldown_minutes" type="number" min="15" max="1440" class="input-field tabular-nums">
          </div>
        </div>

        <button
          type="submit"
          :disabled="saving"
          class="btn-primary rounded-xl px-4 py-2 text-xs font-bold"
        >
          {{ saving ? 'Menyimpan...' : 'Simpan Pengaturan Notifikasi' }}
        </button>
      </form>

      <!-- Notification History -->
      <div v-if="settingsStore.notificationLogs.length > 0" class="border-t border-[var(--border-subtle)] pt-3">
        <h3 class="mb-2 text-xs font-semibold text-[var(--text-secondary)]">Riwayat Notifikasi Terbaru</h3>
        <div class="space-y-2 text-xs">
          <div
            v-for="log in settingsStore.notificationLogs.slice(0, 6)"
            :key="log.id"
            class="rounded-xl bg-[var(--bg-elevated)] p-3"
          >
            <div class="flex items-center justify-between gap-2">
              <span class="font-semibold">{{ log.title }}</span>
              <span class="rounded-md bg-[var(--accent-soft)] px-1.5 py-0.5 text-[10px] uppercase">{{ log.status }}</span>
            </div>
            <p class="mt-0.5 text-[var(--text-secondary)]">{{ log.body }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Account Sign Out -->
    <section class="card-surface flex items-center justify-between p-5">
      <div>
        <div class="text-sm font-semibold">{{ auth.user?.name }}</div>
        <div class="text-xs text-[var(--text-secondary)]">{{ auth.user?.email }}</div>
      </div>
      <button
        type="button"
        class="rounded-xl border border-[var(--border-subtle)] px-4 py-2 text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--bg-elevated)]"
        @click="handleLogout"
      >
        Keluar
      </button>
    </section>
  </div>
</template>
