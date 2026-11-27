<script setup lang="ts">
import { todayJakarta } from '~/utils/format'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{
  close: []
  recorded: [message: string]
}>()

const smoking = useSmokingStore()
const submitting = ref(false)
const errorMsg = ref<string | null>(null)

const form = reactive({
  event_date: todayJakarta(),
  packs_smoked: 1,
  trigger: '',
  notes: '',
})

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    errorMsg.value = null
    form.event_date = todayJakarta()
    form.packs_smoked = 1
    form.trigger = ''
    form.notes = ''
  }
})

async function handleSubmit() {
  submitting.value = true
  errorMsg.value = null
  try {
    const res = await smoking.recordRelapse({
      event_date: form.event_date,
      packs_smoked: Number(form.packs_smoked),
      trigger: form.trigger || undefined,
      notes: form.notes || undefined,
    })
    emit('recorded', res.message)
    emit('close')
  } catch (err: any) {
    errorMsg.value = err?.data?.message || 'Gagal mencatat kejadian.'
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
        <div class="mb-2 flex items-center justify-between">
          <h3 class="text-base font-bold">Catat Kekambuhan (Relapse)</h3>
          <button
            type="button"
            class="rounded-full p-1.5 text-[var(--text-secondary)] hover:bg-[var(--bg-elevated)]"
            @click="emit('close')"
          >
            ✕
          </button>
        </div>

        <p class="mb-4 rounded-xl bg-[var(--bg-elevated)] p-3 text-xs leading-relaxed text-[var(--text-secondary)]">
          Tidak apa-apa. Setiap langkah pemulihan tetap berharga — seluruh riwayat hari bebas rokokmu sebelumnya tetap tersimpan dan tidak dihapus.
        </p>

        <form class="space-y-3.5" @submit.prevent="handleSubmit">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Tanggal</label>
              <input v-model="form.event_date" type="date" class="input-field" required>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Jumlah Bungkus</label>
              <input v-model.number="form.packs_smoked" type="number" step="0.25" min="0.1" max="20" class="input-field" required>
            </div>
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Pemicu (Opsional)</label>
            <input v-model="form.trigger" type="text" placeholder="Contoh: Stres kerja, nongkrong" class="input-field">
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Refleksi / Catatan</label>
            <textarea
              v-model="form.notes"
              rows="3"
              placeholder="Apa yang bisa membantu lain kali?"
              class="input-field"
            />
          </div>

          <p v-if="errorMsg" class="text-xs text-[var(--text-secondary)]">{{ errorMsg }}</p>

          <button
            type="submit"
            :disabled="submitting"
            class="btn-primary w-full rounded-xl py-3 text-sm font-semibold disabled:opacity-50"
          >
            {{ submitting ? 'Menyimpan...' : 'Simpan & Lanjutkan Perjalanan' }}
          </button>
        </form>
      </div>
    </div>
  </Teleport>
</template>
