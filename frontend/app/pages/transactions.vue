<script setup lang="ts">
import type { TransactionItem } from '~/types/regain'
import { currentMonthJakarta, formatDateId, formatRupiah } from '~/utils/format'

const finance = useFinanceStore()

const filters = reactive({
  search: '',
  type: '',
  category_id: '',
  month: currentMonthJakarta(),
})

const showTxModal = ref(false)
const editingTx = ref<TransactionItem | null>(null)
const showRecurringForm = ref(false)
const recurringForm = reactive({
  name: 'Gaji Bulanan',
  amount: 3000000,
  day_of_month: 25,
  description: 'Gaji bulanan otomatis',
})
const statusBanner = ref<string | null>(null)

async function loadTransactions() {
  await finance.fetchTransactions({
    search: filters.search || undefined,
    type: filters.type || undefined,
    category_id: filters.category_id || undefined,
    month: filters.month || undefined,
  })
}

onMounted(async () => {
  await Promise.all([
    finance.categories.length === 0 ? finance.fetchCategories() : Promise.resolve(),
    finance.accounts.length === 0 ? finance.fetchAccounts() : Promise.resolve(),
    finance.fetchRecurringIncomes(),
    finance.loadPendingOffline(),
    loadTransactions(),
  ])
})

watch(() => [filters.type, filters.category_id, filters.month], loadTransactions)

function openCreate() {
  editingTx.value = null
  showTxModal.value = true
}

function openEdit(tx: TransactionItem) {
  editingTx.value = tx
  showTxModal.value = true
}

async function handleDelete(tx: TransactionItem) {
  if (!confirm(`Hapus transaksi "${tx.description || tx.category_name}"?`)) return
  await finance.deleteTransaction(tx.id)
  await Promise.all([loadTransactions(), finance.fetchDashboard()])
}

async function onSaved(info: { queuedOffline: boolean }) {
  statusBanner.value = info.queuedOffline
    ? 'Transaksi masuk antrean offline (belum tersinkronisasi ke server).'
    : 'Transaksi berhasil disimpan.'
  if (!info.queuedOffline) {
    await Promise.all([loadTransactions(), finance.fetchDashboard()])
  }
}

async function addRecurring() {
  await finance.createRecurringIncome({
    name: recurringForm.name,
    amount: Number(recurringForm.amount),
    day_of_month: Number(recurringForm.day_of_month),
    description: recurringForm.description,
  })
  showRecurringForm.value = false
  statusBanner.value = 'Pemasukan rutin berhasil ditambahkan.'
}

async function triggerDueRecurring() {
  const res = await finance.runDueRecurringIncomes()
  statusBanner.value = res.message
  await loadTransactions()
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold tracking-tight">Transaksi</h1>
        <p class="text-xs text-[var(--text-secondary)]">Pemasukan aktual, pengeluaran, dan gaji rutin</p>
      </div>
      <button
        type="button"
        class="rounded-xl bg-emerald-500 px-3.5 py-2 text-xs font-bold text-zinc-950 hover:bg-emerald-400"
        @click="openCreate"
      >
        + Tambah
      </button>
    </div>

    <div
      v-if="statusBanner"
      class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2.5 text-xs text-emerald-500 dark:text-emerald-300"
    >
      {{ statusBanner }}
    </div>

    <!-- Offline Pending Queue -->
    <section v-if="finance.pendingOffline.length > 0" class="card-surface border-amber-500/30 p-4">
      <div class="mb-2.5 flex items-center justify-between">
        <div class="text-xs font-bold text-amber-500">
          Antrean Offline — Belum Disinkronkan ({{ finance.pendingOffline.length }})
        </div>
        <button
          type="button"
          class="rounded-lg bg-amber-500/20 px-2.5 py-1 text-xs font-semibold text-amber-500"
          :disabled="finance.syncingOffline"
          @click="finance.syncPendingOffline()"
        >
          {{ finance.syncingOffline ? 'Menyinkronkan...' : 'Sinkronkan Sekarang' }}
        </button>
      </div>
      <div class="divide-y divide-[var(--border-subtle)] text-xs">
        <div
          v-for="item in finance.pendingOffline"
          :key="item.client_uuid"
          class="flex items-center justify-between py-2"
        >
          <div>
            <div class="font-medium">{{ item.description || item.category_name }}</div>
            <div class="text-[11px] text-amber-500">
              Status: {{ item.status === 'syncing' ? 'Menyinkronkan...' : item.status === 'failed' ? `Gagal (${item.error_message})` : 'Belum disinkronkan' }}
            </div>
          </div>
          <div class="flex items-center gap-3">
            <span class="font-bold tabular-nums">{{ formatRupiah(item.amount) }}</span>
            <button
              v-if="item.id"
              type="button"
              class="text-rose-400 hover:underline"
              @click="finance.removePendingOffline(item.id)"
            >
              Hapus
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Recurring Monthly Salary Section -->
    <section class="card-surface p-4">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 class="text-sm font-semibold">Pemasukan Rutin (Gaji Otomatis)</h2>
          <p class="text-[11px] text-[var(--text-secondary)]">Tercatat otomatis setiap bulan tanpa duplikasi</p>
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="rounded-lg border border-[var(--border-subtle)] px-2.5 py-1.5 text-xs font-medium hover:bg-[var(--bg-elevated)]"
            @click="triggerDueRecurring"
          >
            Cek Jatuh Tempo
          </button>
          <button
            type="button"
            class="rounded-lg bg-emerald-500/15 px-2.5 py-1.5 text-xs font-semibold text-emerald-500"
            @click="showRecurringForm = !showRecurringForm"
          >
            {{ showRecurringForm ? 'Tutup' : '+ Gaji Rutin' }}
          </button>
        </div>
      </div>

      <form v-if="showRecurringForm" class="mt-4 grid gap-3 border-t border-[var(--border-subtle)] pt-4 sm:grid-cols-3" @submit.prevent="addRecurring">
        <input v-model="recurringForm.name" type="text" placeholder="Nama (mis. Gaji Bulanan)" class="input-field" required>
        <input v-model.number="recurringForm.amount" type="number" min="1" placeholder="Nominal (Rp)" class="input-field tabular-nums" required>
        <div class="flex gap-2">
          <input v-model.number="recurringForm.day_of_month" type="number" min="1" max="31" placeholder="Tgl (1-31)" class="input-field" required>
          <button type="submit" class="shrink-0 rounded-xl bg-emerald-500 px-4 text-xs font-bold text-zinc-950">
            Simpan
          </button>
        </div>
      </form>

      <div v-if="finance.recurringIncomes.length > 0" class="mt-3 space-y-2">
        <div
          v-for="rec in finance.recurringIncomes"
          :key="rec.id"
          class="flex items-center justify-between rounded-xl bg-[var(--bg-elevated)] px-3.5 py-2.5 text-xs"
        >
          <div>
            <div class="font-semibold">{{ rec.name }}</div>
            <div class="text-[11px] text-[var(--text-secondary)]">
              Tanggal {{ rec.day_of_month }} setiap bulan · Berikutnya: {{ formatDateId(rec.next_run_date) }}
            </div>
          </div>
          <div class="flex items-center gap-3">
            <span class="font-bold text-emerald-500 tabular-nums">{{ formatRupiah(rec.amount) }}</span>
            <button type="button" class="text-rose-400 hover:underline" @click="finance.deleteRecurringIncome(rec.id)">
              Hapus
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Filters -->
    <div class="grid gap-2.5 sm:grid-cols-4">
      <input
        v-model="filters.search"
        type="search"
        placeholder="Cari catatan / kategori..."
        class="input-field sm:col-span-2"
        @keyup.enter="loadTransactions"
      >
      <select v-model="filters.type" class="input-field">
        <option value="">Semua Jenis</option>
        <option value="expense">Pengeluaran</option>
        <option value="income">Pemasukan</option>
      </select>
      <input v-model="filters.month" type="month" class="input-field">
    </div>

    <!-- Transaction List -->
    <section class="card-surface overflow-hidden">
      <div v-if="finance.transactions.length > 0" class="divide-y divide-[var(--border-subtle)]">
        <div
          v-for="tx in finance.transactions"
          :key="tx.id"
          class="flex items-center justify-between gap-3 px-4 py-3.5"
        >
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <span class="truncate text-sm font-medium">{{ tx.description || tx.category_name }}</span>
              <span
                v-if="tx.source === 'recurring'"
                class="rounded-md bg-sky-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-sky-400"
              >
                Rutin
              </span>
            </div>
            <div class="mt-0.5 text-xs text-[var(--text-secondary)]">
              {{ tx.category_name }}<span v-if="tx.subcategory_name"> · {{ tx.subcategory_name }}</span> · {{ formatDateId(tx.transaction_date) }} {{ (tx.transaction_time || '').slice(0, 5) }}
            </div>
          </div>
          <div class="flex shrink-0 items-center gap-3">
            <span
              class="text-sm font-bold tabular-nums"
              :class="tx.type === 'income' ? 'text-emerald-500' : 'text-[var(--text-primary)]'"
            >
              {{ tx.type === 'income' ? '+' : '-' }}{{ formatRupiah(tx.amount) }}
            </span>
            <button type="button" class="text-xs text-[var(--text-secondary)] hover:text-emerald-400" @click="openEdit(tx)">
              Edit
            </button>
            <button type="button" class="text-xs text-rose-400 hover:underline" @click="handleDelete(tx)">
              Hapus
            </button>
          </div>
        </div>
      </div>

      <div v-else class="p-8 text-center text-xs text-[var(--text-secondary)]">
        Tidak ada transaksi untuk filter ini.
      </div>
    </section>

    <TransactionModal
      :open="showTxModal"
      :editing="editingTx"
      @close="showTxModal = false"
      @saved="onSaved"
    />
  </div>
</template>
