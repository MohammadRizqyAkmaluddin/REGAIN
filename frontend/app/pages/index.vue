<script setup lang="ts">
import { currentMonthJakarta, formatDateId, formatNumber, formatRupiah } from '~/utils/format'

const auth = useAuthStore()
const finance = useFinanceStore()

const selectedMonth = ref(currentMonthJakarta())
const showTxModal = ref(false)
const txModalType = ref<'income' | 'expense'>('expense')
const toastMsg = ref<string | null>(null)

async function loadData() {
  await Promise.all([
    finance.fetchDashboard(selectedMonth.value),
    finance.categories.length === 0 ? finance.fetchCategories() : Promise.resolve(),
  ])
}

onMounted(loadData)
watch(selectedMonth, loadData)

function openQuickAdd(type: 'income' | 'expense') {
  txModalType.value = type
  showTxModal.value = true
}

async function onTransactionSaved(info: { queuedOffline: boolean }) {
  if (info.queuedOffline) {
    toastMsg.value = 'Disimpan di antrean offline (belum tersinkronisasi ke server).'
  } else {
    toastMsg.value = 'Transaksi berhasil dicatat.'
    await finance.fetchDashboard(selectedMonth.value)
  }
  setTimeout(() => {
    toastMsg.value = null
  }, 4000)
}
</script>

<template>
  <div class="space-y-4 md:space-y-5">
    <!-- Top Header -->
    <div class="flex flex-wrap items-center justify-between gap-3 px-4 md:px-0">
      <div class="flex min-w-0 items-center gap-2.5">
        <img
          src="/logos/dark-transparent-logo.png"
          alt="Deep Pockets"
          class="h-9 w-9 shrink-0 object-contain dark:hidden md:hidden"
        >
        <img
          src="/logos/light-transparent-logo.png"
          alt="Deep Pockets"
          class="hidden h-9 w-9 shrink-0 object-contain dark:block md:hidden"
        >
        <div class="min-w-0">
          <p class="truncate text-xs text-[var(--text-secondary)]">{{ auth.user?.name || 'Deep Pockets' }}</p>
          <h1 class="text-lg font-bold tracking-tight sm:text-xl">Ringkasan Keuangan</h1>
        </div>
      </div>
      <div class="flex shrink-0 items-center gap-2">
        <input
          v-model="selectedMonth"
          type="month"
          class="rounded-xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] px-2.5 py-1.5 text-xs font-medium text-[var(--text-primary)]"
        >
        <button
          type="button"
          class="btn-primary whitespace-nowrap rounded-xl px-3 py-2 text-xs font-bold"
          @click="openQuickAdd('expense')"
        >
          + Catat
        </button>
      </div>
    </div>

    <div
      v-if="toastMsg"
      class="mx-4 rounded-xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] px-4 py-2.5 text-xs font-medium text-[var(--text-primary)] md:mx-0"
    >
      {{ toastMsg }}
    </div>

    <NotificationOnboardingBanner />

    <!-- Hero Cards: Actual Net Worth + Smoking Savings (Strictly Separated) -->
    <div class="grid gap-3 md:grid-cols-2 md:gap-4">
      <!-- Actual Net Worth -->
      <section class="card-surface p-5">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-[var(--text-secondary)]">Kekayaan Bersih Aktual</span>
          <span class="rounded-full bg-[var(--bg-elevated)] px-2.5 py-0.5 text-[11px] font-medium text-[var(--text-secondary)]">
            Saldo Nyata
          </span>
        </div>
        <div class="mt-2 text-3xl font-bold tracking-tight tabular-nums">
          {{ formatRupiah(finance.dashboard?.finance.actual_net_worth) }}
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3 border-t border-[var(--border-subtle)] pt-3.5">
          <div>
            <div class="text-[11px] text-[var(--text-secondary)]">Pemasukan Bulan Ini</div>
            <div class="mt-0.5 text-sm font-semibold text-[var(--text-primary)] tabular-nums">
              +{{ formatRupiah(finance.dashboard?.finance.monthly.monthly_income) }}
            </div>
          </div>
          <div>
            <div class="text-[11px] text-[var(--text-secondary)]">Pengeluaran Bulan Ini</div>
            <div class="mt-0.5 text-sm font-semibold text-[var(--text-secondary)] tabular-nums">
              -{{ formatRupiah(finance.dashboard?.finance.monthly.monthly_expenses) }}
            </div>
          </div>
        </div>
      </section>

      <!-- Smoking Savings (Strictly Separated) -->
      <section class="card-surface relative overflow-hidden p-5">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-[var(--text-secondary)]">Estimasi Tabungan Bebas Rokok</span>
          <NuxtLink to="/smoke-free" class="text-accent text-[11px] font-semibold hover:underline">
            Detail →
          </NuxtLink>
        </div>
        <div class="mt-2 flex items-baseline justify-between gap-2">
          <div class="text-3xl font-bold tracking-tight text-[var(--text-primary)] tabular-nums">
            {{ formatRupiah(finance.dashboard?.smoking.estimated_money_saved) }}
          </div>
          <div class="rounded-xl bg-[var(--accent-soft)] px-3 py-1.5 text-right">
            <div class="text-accent text-lg font-extrabold leading-none tabular-nums">
              {{ finance.dashboard?.smoking.current_streak_days ?? 0 }}
            </div>
            <div class="mt-0.5 text-[10px] font-medium text-[var(--text-secondary)]">hari bebas</div>
          </div>
        </div>
        <p class="mt-3 border-t border-[var(--border-subtle)] pt-3 text-[11px] text-[var(--text-secondary)]">
          {{ formatNumber(finance.dashboard?.smoking.estimated_packs_avoided) }} bungkus ({{ finance.dashboard?.smoking.estimated_cigarettes_avoided ?? 0 }} batang) dihindari.
          <span class="opacity-80">Terpisah dari kekayaan bersih aktual.</span>
        </p>
      </section>
    </div>

    <!-- Financial Charts -->
    <div class="grid gap-3 md:gap-4 lg:grid-cols-5">
      <section class="card-surface p-5 lg:col-span-3">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-sm font-semibold">Tren 6 Bulan Terakhir</h2>
          <span class="text-[11px] text-[var(--text-secondary)]">Pemasukan, Pengeluaran &amp; Kekayaan</span>
        </div>
        <MoneyChart mode="trend" :trend="finance.dashboard?.finance.trend" />
      </section>

      <section class="card-surface p-5 lg:col-span-2">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-sm font-semibold">Pengeluaran per Kategori</h2>
          <span class="text-[11px] text-[var(--text-secondary)]">{{ selectedMonth }}</span>
        </div>
        <div v-if="(finance.dashboard?.finance.monthly.expense_by_category.length ?? 0) > 0">
          <MoneyChart mode="category" :categories="finance.dashboard?.finance.monthly.expense_by_category" />
          <ul class="mt-2 space-y-1.5 text-xs">
            <li
              v-for="cat in finance.dashboard?.finance.monthly.expense_by_category.slice(0, 4)"
              :key="cat.category_id"
              class="flex items-center justify-between"
            >
              <span class="flex items-center gap-2 truncate">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: cat.color }" />
                <span class="truncate">{{ cat.category_name }}</span>
              </span>
              <span class="font-medium tabular-nums">{{ formatRupiah(cat.total) }}</span>
            </li>
          </ul>
        </div>
        <div v-else class="flex h-56 items-center justify-center text-xs text-[var(--text-secondary)]">
          Belum ada pengeluaran bulan ini.
        </div>
      </section>
    </div>

    <!-- Recent Transactions -->
    <section class="card-surface p-5">
      <div class="mb-3 flex items-center justify-between">
        <h2 class="text-sm font-semibold">Transaksi Terbaru</h2>
        <NuxtLink to="/transactions" class="text-accent text-xs font-semibold hover:underline">
          Lihat Semua →
        </NuxtLink>
      </div>

      <div v-if="(finance.dashboard?.recent_transactions.length ?? 0) > 0" class="divide-y divide-[var(--border-subtle)]">
        <div
          v-for="tx in finance.dashboard?.recent_transactions"
          :key="tx.id"
          class="flex items-center justify-between gap-3 py-3"
        >
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <span class="truncate text-sm font-medium">{{ tx.description || tx.category_name }}</span>
              <span
                v-if="tx.source === 'recurring'"
                class="rounded-md bg-[var(--accent-soft)] px-1.5 py-0.5 text-[10px] font-semibold text-[var(--text-secondary)]"
              >
                Rutin
              </span>
            </div>
            <div class="mt-0.5 text-xs text-[var(--text-secondary)]">
              {{ tx.category_name }}<span v-if="tx.subcategory_name"> · {{ tx.subcategory_name }}</span> · {{ formatDateId(tx.transaction_date) }}
            </div>
          </div>
          <div
            class="shrink-0 text-sm font-bold tabular-nums"
            :class="tx.type === 'income' ? 'text-[var(--text-primary)]' : 'text-[var(--text-secondary)]'"
          >
            {{ tx.type === 'income' ? '+' : '-' }}{{ formatRupiah(tx.amount) }}
          </div>
        </div>
      </div>

      <div v-else class="py-8 text-center text-xs text-[var(--text-secondary)]">
        Belum ada transaksi. Klik <strong>+ Catat</strong> untuk mencatat pemasukan atau pengeluaran pertamamu.
      </div>
    </section>

    <TransactionModal
      :open="showTxModal"
      :default-type="txModalType"
      @close="showTxModal = false"
      @saved="onTransactionSaved"
    />
  </div>
</template>
