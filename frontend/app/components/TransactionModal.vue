<script setup lang="ts">
import type { TransactionItem } from '~/types/regain'
import { nowTimeJakarta, todayJakarta } from '~/utils/format'

const props = defineProps<{
  open: boolean
  editing?: TransactionItem | null
  defaultType?: 'income' | 'expense'
}>()

const emit = defineEmits<{
  close: []
  saved: [info: { queuedOffline: boolean }]
}>()

const finance = useFinanceStore()
const submitting = ref(false)
const errorMsg = ref<string | null>(null)

const form = reactive({
  type: 'expense' as 'income' | 'expense',
  account_id: null as number | null,
  category_id: 0,
  subcategory_id: null as number | null,
  amount: '',
  description: '',
  transaction_date: todayJakarta(),
  transaction_time: nowTimeJakarta(),
})

const filteredCategories = computed(() =>
  finance.categories.filter(c => c.type === form.type),
)

const activeSubcategories = computed(() => {
  const cat = filteredCategories.value.find(c => c.id === form.category_id)
  return cat?.subcategories ?? []
})

watch(() => props.open, (isOpen) => {
  if (!isOpen) return
  errorMsg.value = null
  if (props.editing) {
    form.type = props.editing.type
    form.account_id = props.editing.account_id
    form.category_id = props.editing.category_id
    form.subcategory_id = props.editing.subcategory_id
    form.amount = String(Math.round(Number.parseFloat(props.editing.amount)))
    form.description = props.editing.description ?? ''
    form.transaction_date = props.editing.transaction_date
    form.transaction_time = (props.editing.transaction_time || '00:00').slice(0, 5)
  } else {
    form.type = props.defaultType ?? 'expense'
    form.account_id = finance.accounts.find(a => a.is_default)?.id ?? finance.accounts[0]?.id ?? null
    const firstCat = finance.categories.find(c => c.type === form.type)
    form.category_id = firstCat?.id ?? 0
    form.subcategory_id = firstCat?.subcategories[0]?.id ?? null
    form.amount = ''
    form.description = ''
    form.transaction_date = todayJakarta()
    form.transaction_time = nowTimeJakarta()
  }
})

watch(() => form.type, () => {
  if (props.editing && props.editing.type === form.type) return
  const firstCat = filteredCategories.value[0]
  form.category_id = firstCat?.id ?? 0
  form.subcategory_id = firstCat?.subcategories[0]?.id ?? null
})

watch(() => form.category_id, () => {
  const exists = activeSubcategories.value.some(s => s.id === form.subcategory_id)
  if (!exists) {
    form.subcategory_id = activeSubcategories.value[0]?.id ?? null
  }
})

async function handleSubmit() {
  errorMsg.value = null
  const amountNum = Number.parseFloat(form.amount)
  if (Number.isNaN(amountNum) || amountNum <= 0) {
    errorMsg.value = 'Masukkan nominal yang valid.'
    return
  }
  if (!form.category_id) {
    errorMsg.value = 'Pilih kategori terlebih dahulu.'
    return
  }

  submitting.value = true
  try {
    if (props.editing) {
      await finance.updateTransaction(props.editing.id, {
        account_id: form.account_id,
        type: form.type,
        category_id: form.category_id,
        subcategory_id: form.subcategory_id,
        amount: amountNum,
        description: form.description || null,
        transaction_date: form.transaction_date,
        transaction_time: form.transaction_time,
      })
      emit('saved', { queuedOffline: false })
    } else {
      const res = await finance.createTransaction({
        account_id: form.account_id,
        type: form.type,
        category_id: form.category_id,
        subcategory_id: form.subcategory_id,
        amount: amountNum,
        description: form.description,
        transaction_date: form.transaction_date,
        transaction_time: form.transaction_time,
      })
      emit('saved', { queuedOffline: res.queuedOffline })
    }
    emit('close')
  } catch (err: any) {
    errorMsg.value = err?.data?.message || 'Gagal menyimpan transaksi.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 p-0 backdrop-blur-xs sm:items-center sm:p-4"
      @click.self="emit('close')"
    >
      <div class="w-full max-w-md rounded-t-3xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] p-5 shadow-2xl sm:rounded-3xl">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-base font-bold">{{ editing ? 'Edit Transaksi' : 'Catat Transaksi' }}</h3>
          <button
            type="button"
            class="rounded-full p-1.5 text-[var(--text-secondary)] hover:bg-[var(--bg-elevated)]"
            @click="emit('close')"
          >
            ✕
          </button>
        </div>

        <form class="space-y-3.5" @submit.prevent="handleSubmit">
          <!-- Type Toggle -->
          <div class="grid grid-cols-2 gap-2 rounded-xl bg-[var(--bg-elevated)] p-1">
            <button
              type="button"
              class="rounded-lg py-2 text-sm font-semibold transition-colors"
              :class="form.type === 'expense' ? 'btn-primary' : 'text-[var(--text-secondary)]'"
              @click="form.type = 'expense'"
            >
              Pengeluaran
            </button>
            <button
              type="button"
              class="rounded-lg py-2 text-sm font-semibold transition-colors"
              :class="form.type === 'income' ? 'btn-primary' : 'text-[var(--text-secondary)]'"
              @click="form.type = 'income'"
            >
              Pemasukan
            </button>
          </div>

          <!-- Amount -->
          <div>
            <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Nominal (Rp)</label>
            <input
              v-model="form.amount"
              type="number"
              inputmode="numeric"
              min="1"
              step="any"
              placeholder="Contoh: 35000"
              class="input-field tabular-nums text-lg font-bold"
              required
            >
          </div>

          <!-- Category & Subcategory -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Kategori</label>
              <select v-model.number="form.category_id" class="input-field" required>
                <option v-for="cat in filteredCategories" :key="cat.id" :value="cat.id">
                  {{ cat.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Subkategori</label>
              <select v-model="form.subcategory_id" class="input-field">
                <option :value="null">— Opsional —</option>
                <option v-for="sub in activeSubcategories" :key="sub.id" :value="sub.id">
                  {{ sub.name }}
                </option>
              </select>
            </div>
          </div>

          <!-- Account -->
          <div v-if="finance.accounts.length > 1">
            <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Akun</label>
            <select v-model.number="form.account_id" class="input-field">
              <option v-for="acc in finance.accounts" :key="acc.id" :value="acc.id">
                {{ acc.name }}
              </option>
            </select>
          </div>

          <!-- Date & Time -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Tanggal</label>
              <input v-model="form.transaction_date" type="date" class="input-field" required>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Waktu (WIB)</label>
              <input v-model="form.transaction_time" type="time" class="input-field" required>
            </div>
          </div>

          <!-- Description -->
          <div>
            <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Catatan</label>
            <input
              v-model="form.description"
              type="text"
              maxlength="255"
              placeholder="Contoh: Kopi susu pagi"
              class="input-field"
            >
          </div>

          <p v-if="errorMsg" class="text-xs text-[var(--text-secondary)]">
            {{ errorMsg }}
          </p>

          <button
            type="submit"
            :disabled="submitting"
            class="btn-primary w-full rounded-xl py-3 text-sm font-bold disabled:opacity-50"
          >
            {{ submitting ? 'Menyimpan...' : (editing ? 'Simpan Perubahan' : 'Simpan Transaksi') }}
          </button>
        </form>
      </div>
    </div>
  </Teleport>
</template>
