<script setup lang="ts">
const auth = useAuthStore()
const errorMsg = ref<string | null>(null)

const form = reactive({
  email: '',
  password: '',
})

async function handleLogin() {
  errorMsg.value = null
  try {
    await auth.login({ email: form.email, password: form.password })
    await navigateTo('/')
  } catch (err: any) {
    errorMsg.value = err?.data?.message || 'Email atau kata sandi salah.'
  }
}
</script>

<template>
  <div class="flex min-h-dvh items-center justify-center px-4 py-10">
    <div class="w-full max-w-sm space-y-6">
      <div class="text-center">
        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30">
          <span class="text-base font-black tracking-tight">R:G</span>
        </div>
        <h1 class="text-2xl font-bold tracking-tight">Masuk ke RE:GAIN</h1>
        <p class="mt-1.5 text-xs text-[var(--text-secondary)]">
          Setiap rokok yang tidak kamu beli adalah uang yang kamu simpan untuk masa depan.
        </p>
      </div>

      <form class="card-surface space-y-4 p-5" @submit.prevent="handleLogin">
        <div>
          <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Email</label>
          <input v-model="form.email" type="email" autocomplete="email" placeholder="nama@email.com" class="input-field" required>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Kata Sandi</label>
          <input v-model="form.password" type="password" autocomplete="current-password" placeholder="••••••••" class="input-field" required>
        </div>

        <p v-if="errorMsg" class="text-xs text-rose-400">{{ errorMsg }}</p>

        <button
          type="submit"
          :disabled="auth.loading"
          class="w-full rounded-xl bg-emerald-500 py-3 text-sm font-bold text-zinc-950 transition-colors hover:bg-emerald-400 disabled:opacity-50"
        >
          {{ auth.loading ? 'Memproses...' : 'Masuk' }}
        </button>
      </form>

      <p class="text-center text-xs text-[var(--text-secondary)]">
        Belum punya akun?
        <NuxtLink to="/register" class="font-semibold text-emerald-500 hover:underline">
          Daftar Sekarang
        </NuxtLink>
      </p>
    </div>
  </div>
</template>
