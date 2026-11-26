<script setup lang="ts">
import type { FinancialGoalItem } from '~/types/regain'
import { formatDateId, formatRupiah } from '~/utils/format'

const goalsStore = useGoalsStore()
const showCreateModal = ref(false)
const activePreset = ref<{ name: string; category: string; target_amount: number } | null>(null)
const allocatingGoal = ref<FinancialGoalItem | null>(null)

const presets = [
  { name: 'Dana Darurat', category: 'emergency_fund', target_amount: 15000000 },
  { name: 'Laptop Baru', category: 'gadget', target_amount: 12000000 },
  { name: 'Liburan', category: 'vacation', target_amount: 5000000 },
  { name: 'Investasi', category: 'investment', target_amount: 10000000 },
]

onMounted(() => goalsStore.fetchGoals())

function openCreate(preset: { name: string; category: string; target_amount: number } | null = null) {
  activePreset.value = preset
  showCreateModal.value = true
}

async function removeGoal(goal: FinancialGoalItem) {
  if (!confirm(`Hapus target "${goal.name}"?`)) return
  await goalsStore.deleteGoal(goal.id)
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold tracking-tight">Target Keuangan</h1>
        <p class="text-xs text-[var(--text-secondary)]">Arahkan estimasi tabungan bebas rokok ke impianmu</p>
      </div>
      <button
        type="button"
        class="rounded-xl bg-emerald-500 px-3.5 py-2 text-xs font-bold text-zinc-950 hover:bg-emerald-400"
        @click="openCreate(null)"
      >
        + Target Baru
      </button>
    </div>

    <!-- Virtual Smoking Savings Pool Summary -->
    <section class="card-surface border-emerald-500/25 p-4">
      <div class="grid gap-3 sm:grid-cols-3">
        <div>
          <div class="text-[11px] text-[var(--text-secondary)]">Total Estimasi Tabungan Rokok</div>
          <div class="mt-0.5 text-lg font-bold text-emerald-500 tabular-nums">
            {{ formatRupiah(goalsStore.pool.estimated_money_saved) }}
          </div>
        </div>
        <div>
          <div class="text-[11px] text-[var(--text-secondary)]">Sudah Dialokasikan (Virtual)</div>
          <div class="mt-0.5 text-lg font-bold tabular-nums">
            {{ formatRupiah(goalsStore.pool.allocated_to_goals) }}
          </div>
        </div>
        <div>
          <div class="text-[11px] text-[var(--text-secondary)]">Tersedia untuk Dialokasikan</div>
          <div class="mt-0.5 text-lg font-bold text-sky-400 tabular-nums">
            {{ formatRupiah(goalsStore.pool.unallocated_savings) }}
          </div>
        </div>
      </div>
    </section>

    <!-- Quick Presets -->
    <div class="flex flex-wrap gap-2">
      <button
        v-for="p in presets"
        :key="p.name"
        type="button"
        class="rounded-full border border-[var(--border-subtle)] bg-[var(--bg-surface)] px-3 py-1.5 text-xs font-medium hover:border-emerald-500/40 hover:text-emerald-400"
        @click="openCreate(p)"
      >
        + {{ p.name }}
      </button>
    </div>

    <!-- Goals Grid -->
    <div v-if="goalsStore.goals.length > 0" class="grid gap-4 sm:grid-cols-2">
      <article
        v-for="goal in goalsStore.goals"
        :key="goal.id"
        class="card-surface flex flex-col justify-between p-5"
      >
        <div>
          <div class="flex items-start justify-between gap-2">
            <div>
              <h3 class="text-base font-bold">{{ goal.name }}</h3>
              <p v-if="goal.target_date" class="text-[11px] text-[var(--text-secondary)]">
                Target: {{ formatDateId(goal.target_date) }}
              </p>
            </div>
            <span
              class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold"
              :class="goal.status === 'completed' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-zinc-500/15 text-[var(--text-secondary)]'"
            >
              {{ goal.status === 'completed' ? 'Tercapai' : 'Aktif' }}
            </span>
          </div>

          <div class="mt-4 flex items-baseline justify-between text-xs">
            <span class="font-bold tabular-nums">{{ formatRupiah(goal.combined_progress_amount) }}</span>
            <span class="text-[var(--text-secondary)] tabular-nums">dari {{ formatRupiah(goal.target_amount) }}</span>
          </div>

          <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-[var(--bg-elevated)]">
            <div
              class="h-full rounded-full bg-emerald-500 transition-all duration-300"
              :style="{ width: `${Math.min(100, goal.progress_percentage)}%` }"
            />
          </div>

          <div class="mt-2.5 flex items-center justify-between text-[11px] text-[var(--text-secondary)]">
            <span>Tabungan Rokok: <strong class="text-emerald-500">{{ formatRupiah(goal.allocated_smoking_savings) }}</strong></span>
            <span class="font-semibold">{{ goal.progress_percentage }}%</span>
          </div>
        </div>

        <div class="mt-4 flex items-center justify-between border-t border-[var(--border-subtle)] pt-3 text-xs">
          <button
            type="button"
            class="font-semibold text-emerald-500 hover:underline"
            @click="allocatingGoal = goal"
          >
            Atur Alokasi
          </button>
          <button
            type="button"
            class="text-rose-400 hover:underline"
            @click="removeGoal(goal)"
          >
            Hapus
          </button>
        </div>
      </article>
    </div>

    <div v-else class="card-surface p-10 text-center text-xs text-[var(--text-secondary)]">
      Belum ada target keuangan. Pilih template cepat di atas atau klik <strong>+ Target Baru</strong>.
    </div>

    <GoalModal
      :open="showCreateModal"
      :preset="activePreset"
      @close="showCreateModal = false"
    />

    <GoalAllocateModal
      :open="Boolean(allocatingGoal)"
      :goal="allocatingGoal"
      :unallocated-savings="goalsStore.pool.unallocated_savings"
      @close="allocatingGoal = null"
    />
  </div>
</template>
