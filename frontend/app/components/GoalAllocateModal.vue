<script setup lang="ts">
import type { FinancialGoalItem } from '~/types/regain'
import { formatRupiah } from '~/utils/format'

const props = defineProps<{
  open: boolean
  goal: FinancialGoalItem | null
  unallocatedSavings: string
}>()

const emit = defineEmits<{ close: [] }>()
const goals = useGoalsStore()
const submitting = ref(false)
const errorMsg = ref<string | null>(null)

const form = reactive({
  allocated_smoking_savings: 0,
  current_actual_allocation: 0,
})

const maxSmokingForGoal = computed(() => {
  const current = Number.parseFloat(props.goal?.allocated_smoking_savings ?? '0')
  const unalloc = Number.parseFloat(props.unallocatedSavings || '0')
  return Math.floor(current + unalloc)
})

watch(() => props.open, (isOpen) => {
  if (!isOpen || !props.goal) return
  errorMsg.value = null
  form.allocated_smoking_savings = Math.round(Number.parseFloat(props.goal.allocated_smoking_savings))
  form.current_actual_allocation = Math.round(Number.parseFloat(props.goal.current_actual_allocation))
})

async function handleSubmit() {
  if (!props.goal) return
  submitting.value = true
  errorMsg.value = null
  try {
    await goals.allocateGoal(props.goal.id, {
      allocated_smoking_savings: Number(form.allocated_smoking_savings),
      current_actual_allocation: Number(form.current_actual_allocation),
    })
    emit('close')
  } catch (err: any) {
    errorMsg.value = err?.data?.message || 'Gagal memperbarui alokasi.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open && goal"
      class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 p-0 backdrop-blur-xs sm:items-center sm:p-4"
      @click.self="emit('close')"
    >
      <div class="w-full max-w-md rounded-t-3xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] p-5 shadow-2xl sm:rounded-3xl">
        <div class="mb-2 flex items-center justify-between">
          <h3 class="text-base font-bold">Alokasi Dana — {{ goal.name }}</h3>
          <button type="button" class="rounded-full p-1.5 text-[var(--text-secondary)]" @click="emit('close')">✕</button>
        </div>

        <p class="mb-4 text-xs text-[var(--text-secondary)]">
          Alokasi tabungan bebas rokok bersifat <strong>virtual</strong> dan tidak mengubah saldo akun keuangan aktualmu.
        </p>

        <form class="space-y-4" @submit.prevent="handleSubmit">
          <div>
            <div class="mb-1 flex items-center justify-between text-xs">
              <label class="font-medium text-emerald-500">Alokasi Virtual Tabungan Rokok (Rp)</label>
              <span class="text-[var(--text-secondary)]">Maks: {{ formatRupiah(maxSmokingForGoal) }}</span>
            </div>
            <input
              v-model.number="form.allocated_smoking_savings"
              type="number"
              min="0"
              :max="maxSmokingForGoal"
              class="input-field tabular-nums"
            >
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Alokasi Dana Aktual (Catatan Progres, Rp)</label>
            <input
              v-model.number="form.current_actual_allocation"
              type="number"
              min="0"
              class="input-field tabular-nums"
            >
          </div>

          <p v-if="errorMsg" class="text-xs text-rose-400">{{ errorMsg }}</p>

          <button
            type="submit"
            :disabled="submitting"
            class="w-full rounded-xl bg-emerald-500 py-3 text-sm font-bold text-zinc-950 hover:bg-emerald-400 disabled:opacity-50"
          >
            {{ submitting ? 'Menyimpan...' : 'Simpan Alokasi' }}
          </button>
        </form>
      </div>
    </div>
  </Teleport>
</template>
