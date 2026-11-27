<script setup lang="ts">
import { formatDateId, formatNumber, formatRupiah } from '~/utils/format'

const smoking = useSmokingStore()
const reportTab = ref<'weekly' | 'monthly'>('weekly')
const showRelapseModal = ref(false)
const showConfig = ref(false)
const feedbackMsg = ref<string | null>(null)

const profileForm = reactive({
  quit_date: '',
  packs_per_week: 10,
  price_per_pack: 30000,
  cigarettes_per_pack: 16,
})

function syncFormFromSummary() {
  if (!smoking.summary) return
  profileForm.quit_date = smoking.summary.profile.quit_date
  profileForm.packs_per_week = Number.parseFloat(smoking.summary.profile.packs_per_week)
  profileForm.price_per_pack = Number.parseFloat(smoking.summary.profile.price_per_pack)
  profileForm.cigarettes_per_pack = smoking.summary.profile.cigarettes_per_pack
}

onMounted(async () => {
  await Promise.all([smoking.fetchSummary(), smoking.fetchReports()])
  syncFormFromSummary()
})

async function saveProfile() {
  await smoking.updateProfile({
    quit_date: profileForm.quit_date,
    packs_per_week: Number(profileForm.packs_per_week),
    price_per_pack: Number(profileForm.price_per_pack),
    cigarettes_per_pack: Number(profileForm.cigarettes_per_pack),
  })
  showConfig.value = false
  feedbackMsg.value = 'Konfigurasi profil bebas rokok diperbarui.'
}

function onRelapseRecorded(msg: string) {
  feedbackMsg.value = msg
}

const activeChartItems = computed(() =>
  reportTab.value === 'weekly'
    ? (smoking.reports?.weekly ?? [])
    : (smoking.reports?.monthly ?? []),
)
</script>

<template>
  <div class="space-y-4 md:space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-2 px-4 md:px-0">
      <div>
        <h1 class="text-xl font-bold tracking-tight">Perjalanan Bebas Rokok</h1>
        <p class="text-xs text-[var(--text-secondary)]">Setiap rokok yang tidak dibeli adalah uang untuk masa depanmu</p>
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="rounded-xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] px-3 py-2 text-xs font-medium hover:bg-[var(--bg-elevated)]"
          @click="showConfig = !showConfig"
        >
          {{ showConfig ? 'Tutup Konfigurasi' : 'Atur Profil' }}
        </button>
        <button
          type="button"
          class="btn-primary rounded-xl px-3 py-2 text-xs font-semibold"
          @click="showRelapseModal = true"
        >
          Catat Relapse
        </button>
      </div>
    </div>

    <div
      v-if="feedbackMsg"
      class="mx-4 rounded-xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] px-4 py-3 text-xs leading-relaxed text-[var(--text-primary)] md:mx-0"
    >
      {{ feedbackMsg }}
    </div>

    <!-- Hero Streak & Estimated Savings -->
    <section class="card-surface p-5">
      <div class="grid gap-5 sm:grid-cols-2">
        <div>
          <span class="text-xs font-medium text-[var(--text-secondary)]">Streak Bebas Rokok Saat Ini</span>
          <div class="mt-1 flex items-baseline gap-2">
            <span class="text-4xl font-black tracking-tight tabular-nums">
              {{ smoking.summary?.progress.current_streak_days ?? 0 }}
            </span>
            <span class="text-sm font-medium text-[var(--text-secondary)]">hari berturut-turut</span>
          </div>
          <p class="mt-2 text-xs text-[var(--text-secondary)]">
            Mulai berhenti: <strong>{{ formatDateId(smoking.summary?.profile.quit_date) }}</strong> · Total hari bebas rokok: <strong>{{ smoking.summary?.progress.total_smoke_free_days ?? 0 }} hari</strong>
          </p>
        </div>

        <div class="sm:border-l sm:border-[var(--border-subtle)] sm:pl-5">
          <span class="text-xs font-medium text-[var(--text-secondary)]">Estimasi Uang Tersimpan</span>
          <div class="mt-1 text-3xl font-black tracking-tight text-[var(--text-primary)] tabular-nums">
            {{ formatRupiah(smoking.summary?.progress.estimated_money_saved) }}
          </div>
          <p class="mt-2 text-[11px] text-[var(--text-secondary)]">
            Estimasi ini tidak dihitung sebagai pemasukan aktual &amp; dapat dialokasikan secara virtual ke Target Keuangan.
          </p>
        </div>
      </div>

      <div class="mt-5 grid grid-cols-2 gap-3 border-t border-[var(--border-subtle)] pt-4 sm:grid-cols-4">
        <div>
          <div class="text-[11px] text-[var(--text-secondary)]">Bungkus Dihindari</div>
          <div class="mt-0.5 text-base font-bold tabular-nums">{{ formatNumber(smoking.summary?.progress.estimated_packs_avoided) }}</div>
        </div>
        <div>
          <div class="text-[11px] text-[var(--text-secondary)]">Batang Dihindari</div>
          <div class="mt-0.5 text-base font-bold tabular-nums">{{ smoking.summary?.progress.estimated_cigarettes_avoided ?? 0 }}</div>
        </div>
        <div>
          <div class="text-[11px] text-[var(--text-secondary)]">Pengeluaran Rokok / Bln</div>
          <div class="mt-0.5 text-base font-bold tabular-nums">{{ formatRupiah(smoking.summary?.baseline.monthly_expenditure) }}</div>
        </div>
        <div>
          <div class="text-[11px] text-[var(--text-secondary)]">Potensi Hemat / Tahun</div>
          <div class="mt-0.5 text-base font-bold text-[var(--text-primary)] tabular-nums">{{ formatRupiah(smoking.summary?.baseline.yearly_expenditure) }}</div>
        </div>
      </div>
    </section>

    <!-- Editable Profile Form -->
    <form v-if="showConfig" class="card-surface space-y-4 p-5" @submit.prevent="saveProfile">
      <h2 class="text-sm font-semibold">Konfigurasi Kebiasaan Sebelum Berhenti</h2>
      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label class="mb-1 block text-xs text-[var(--text-secondary)]">Tanggal Berhenti</label>
          <input v-model="profileForm.quit_date" type="date" class="input-field" required>
        </div>
        <div>
          <label class="mb-1 block text-xs text-[var(--text-secondary)]">Bungkus per Minggu</label>
          <input v-model.number="profileForm.packs_per_week" type="number" step="0.5" min="0.5" class="input-field tabular-nums" required>
        </div>
        <div>
          <label class="mb-1 block text-xs text-[var(--text-secondary)]">Harga per Bungkus (Rp)</label>
          <input v-model.number="profileForm.price_per_pack" type="number" min="1000" class="input-field tabular-nums" required>
        </div>
        <div>
          <label class="mb-1 block text-xs text-[var(--text-secondary)]">Batang per Bungkus</label>
          <input v-model.number="profileForm.cigarettes_per_pack" type="number" min="1" max="100" class="input-field tabular-nums" required>
        </div>
      </div>
      <button type="submit" class="btn-primary rounded-xl px-4 py-2.5 text-xs font-bold">
        Simpan Konfigurasi
      </button>
    </form>

    <!-- Weekly & Monthly Reports Chart -->
    <section class="card-surface p-5">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold">Laporan Estimasi Penghematan</h2>
        <div class="flex rounded-lg bg-[var(--bg-elevated)] p-0.5 text-xs">
          <button
            type="button"
            class="rounded-md px-3 py-1 font-medium transition-colors"
            :class="reportTab === 'weekly' ? 'btn-primary' : 'text-[var(--text-secondary)]'"
            @click="reportTab = 'weekly'"
          >
            Mingguan
          </button>
          <button
            type="button"
            class="rounded-md px-3 py-1 font-medium transition-colors"
            :class="reportTab === 'monthly' ? 'btn-primary' : 'text-[var(--text-secondary)]'"
            @click="reportTab = 'monthly'"
          >
            Bulanan
          </button>
        </div>
      </div>
      <SmokingChart :items="activeChartItems" />
    </section>

    <!-- Journey Events History -->
    <section class="card-surface p-5">
      <h2 class="mb-3 text-sm font-semibold">Riwayat Perjalanan</h2>
      <div v-if="(smoking.summary?.recent_events.length ?? 0) > 0" class="divide-y divide-[var(--border-subtle)] text-xs">
        <div
          v-for="ev in smoking.summary?.recent_events"
          :key="ev.id"
          class="flex items-start justify-between gap-3 py-3"
        >
          <div>
            <div class="font-semibold">
              {{ ev.event_type === 'quit_started' ? 'Mulai Berhenti Merokok' : ev.event_type === 'relapse' ? 'Catatan Kekambuhan' : 'Catatan Perjalanan' }}
            </div>
            <p v-if="ev.notes" class="mt-0.5 text-[var(--text-secondary)]">{{ ev.notes }}</p>
            <p v-if="ev.trigger" class="mt-0.5 text-[11px] text-[var(--text-secondary)]">Pemicu: {{ ev.trigger }}</p>
          </div>
          <div class="shrink-0 text-right text-[var(--text-secondary)]">
            <div>{{ formatDateId(ev.event_date) }}</div>
            <div v-if="ev.event_type === 'relapse'" class="font-medium text-[var(--text-primary)]">
              {{ ev.packs_smoked }} bks
            </div>
          </div>
        </div>
      </div>
    </section>

    <RelapseModal
      :open="showRelapseModal"
      @close="showRelapseModal = false"
      @recorded="onRelapseRecorded"
    />
  </div>
</template>
