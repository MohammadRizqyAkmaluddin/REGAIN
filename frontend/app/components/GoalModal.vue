<script setup lang="ts">
const props = defineProps<{
  open: boolean
  preset?: { name: string; category: string; target_amount: number } | null
}>()

const emit = defineEmits<{ close: [] }>()
const goals = useGoalsStore()
const submitting = ref(false)
const errorMsg = ref<string | null>(null)

const form = reactive({
  name: '',
  category: 'emergency_fund',
  target_amount: '',
  target_date: '',
  description: '',
})

watch(() => props.open, (isOpen) => {
  if (!isOpen) return
  errorMsg.value = null
  if (props.preset) {
    form.name = props.preset.name
    form.category = props.preset.category
    form.target_amount = String(props.preset.target_amount)
  } else {
    form.name = ''
    form.category = 'emergency_fund'
    form.target_amount = ''
  }
  form.target_date = ''
  form.description = ''
})

async function handleSubmit() {
  errorMsg.value = null
  submitting.value = true
  try {
    await goals.createGoal({
      name: form.name,
      category: form.category,
      target_amount: Number.parseFloat(form.target_amount),
      target_date: form.target_date || null,
      description: form.description || null,
    })
    emit('close')
  } catch (err: any) {
    errorMsg.value = err?.data?.message || 'Gagal membuat target.'
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
          <h3 class="text-base font-bold">Tambah Target Keuangan</h3>
          <button type="button" class="rounded-full p-1.5 text-[var(--text-secondary)]" @click="emit('close')">✕</button>
        </div>

        <form class="space-y-3.5" @submit.prevent="handleSubmit">
          <div>
            <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Nama Target</label>
            <input v-model="form.name" type="text" placeholder="Contoh: Dana Darurat" class="input-field" required>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Kategori</label>
              <select v-model="form.category" class="input-field">
                <option value="emergency_fund">Dana Darurat</option>
                <option value="gadget">Laptop / Gadget</option>
                <option value="vacation">Liburan</option>
                <option value="investment">Investasi</option>
                <option value="general">Lainnya</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Target Tanggal</label>
              <input v-model="form.target_date" type="date" class="input-field">
            </div>
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Nominal Target (Rp)</label>
            <input v-model="form.target_amount" type="number" min="1000" placeholder="10000000" class="input-field tabular-nums" required>
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Deskripsi (Opsional)</label>
            <input v-model="form.description" type="text" placeholder="Catatan singkat target" class="input-field">
          </div>

          <p v-if="errorMsg" class="text-xs text-[var(--text-secondary)]">{{ errorMsg }}</p>

          <button
            type="submit"
            :disabled="submitting"
            class="btn-primary w-full rounded-xl py-3 text-sm font-bold disabled:opacity-50"
          >
            {{ submitting ? 'Menyimpan...' : 'Simpan Target' }}
          </button>
        </form>
      </div>
    </div>
  </Teleport>
</template>
