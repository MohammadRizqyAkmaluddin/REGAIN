<script setup lang="ts">
const push = usePushNotifications()
const dismissed = ref(false)

onMounted(async () => {
  if (import.meta.client) {
    dismissed.value = localStorage.getItem('regain_notif_banner_dismissed') === '1'
  }
  await push.checkSubscription()
})

const shouldShow = computed(() =>
  push.isSupported.value && !push.isSubscribed.value && push.permission.value !== 'denied' && !dismissed.value,
)

function dismiss() {
  dismissed.value = true
  if (import.meta.client) {
    localStorage.setItem('regain_notif_banner_dismissed', '1')
  }
}
</script>

<template>
  <div
    v-if="shouldShow"
    class="card-surface flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
  >
    <div class="space-y-0.5">
      <div class="text-sm font-semibold text-[var(--text-primary)]">Aktifkan Pengingat Motivasi Bebas Rokok</div>
      <p class="text-xs text-[var(--text-secondary)]">
        Terima maksimal 3 notifikasi acak per hari pada jam aktif untuk menjaga semangat dan progres tabunganmu.
      </p>
      <p v-if="push.statusMessage.value" class="text-xs text-[var(--text-secondary)]">
        {{ push.statusMessage.value }}
      </p>
    </div>
    <div class="flex shrink-0 items-center gap-2">
      <button
        type="button"
        class="rounded-xl px-3 py-2 text-xs font-medium text-[var(--text-secondary)] hover:bg-[var(--bg-elevated)]"
        @click="dismiss"
      >
        Nanti
      </button>
      <button
        type="button"
        :disabled="push.busy.value"
        class="btn-primary rounded-xl px-3.5 py-2 text-xs font-bold disabled:opacity-50"
        @click="push.subscribe()"
      >
        {{ push.busy.value ? 'Memproses...' : 'Aktifkan' }}
      </button>
    </div>
  </div>
</template>
