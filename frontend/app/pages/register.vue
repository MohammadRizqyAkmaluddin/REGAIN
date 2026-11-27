<script setup lang="ts">
import { todayJakarta } from '~/utils/format'

const auth = useAuthStore()
const errorMsg = ref<string | null>(null)

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  opening_balance: 0,
  quit_date: todayJakarta(),
})

async function handleRegister() {
  errorMsg.value = null
  try {
    await auth.register({
      name: form.name,
      email: form.email,
      password: form.password,
      password_confirmation: form.password_confirmation,
      opening_balance: Number(form.opening_balance || 0),
      quit_date: form.quit_date || undefined,
    })
    await navigateTo('/')
  } catch (err: any) {
    const errors = err?.data?.errors
    if (errors) {
      const first = Object.values(errors)[0]
      errorMsg.value = Array.isArray(first) ? String(first[0]) : err?.data?.message
    } else {
      errorMsg.value = err?.data?.message || 'Pendaftaran gagal.'
    }
  }
}
</script>

<template>
  <div class="safe-top-auth flex min-h-dvh items-center justify-center px-4 pb-10">
    <div class="w-full max-w-sm space-y-6">
      <div class="text-center">
        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center">
          <img
            src="/logos/dark-transparent-logo.png"
            alt="Deep Pockets"
            class="h-12 w-12 object-contain dark:hidden"
          >
          <img
            src="/logos/light-transparent-logo.png"
            alt="Deep Pockets"
            class="hidden h-12 w-12 object-contain dark:block"
          >
        </div>
        <h1 class="text-2xl font-bold tracking-tight">Mulai dengan Deep Pockets</h1>
        <p class="mt-1.5 text-xs text-[var(--text-secondary)]">
          Atur saldo awal dan tanggal berhenti merokokmu.
        </p>
      </div>

      <form class="card-surface space-y-3.5 p-5" @submit.prevent="handleRegister">
        <div>
          <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Nama Lengkap</label>
          <input v-model="form.name" type="text" placeholder="Nama kamu" class="input-field" required>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Email</label>
          <input v-model="form.email" type="email" placeholder="nama@email.com" class="input-field" required>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Kata Sandi</label>
            <input v-model="form.password" type="password" minlength="8" placeholder="Min. 8 karakter" class="input-field" required>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Konfirmasi</label>
            <input v-model="form.password_confirmation" type="password" minlength="8" placeholder="Ulangi sandi" class="input-field" required>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Saldo Awal (Rp)</label>
            <input v-model.number="form.opening_balance" type="number" min="0" class="input-field tabular-nums">
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Tanggal Berhenti</label>
            <input v-model="form.quit_date" type="date" class="input-field">
          </div>
        </div>

        <p v-if="errorMsg" class="text-xs text-[var(--text-secondary)]">{{ errorMsg }}</p>

        <button
          type="submit"
          :disabled="auth.loading"
          class="btn-primary w-full rounded-xl py-3 text-sm font-bold disabled:opacity-50"
        >
          {{ auth.loading ? 'Mendaftarkan...' : 'Buat Akun' }}
        </button>
      </form>

      <p class="text-center text-xs text-[var(--text-secondary)]">
        Sudah punya akun?
        <NuxtLink to="/login" class="text-accent font-semibold hover:underline">
          Masuk
        </NuxtLink>
      </p>
    </div>
  </div>
</template>
