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
        <h1 class="text-2xl font-bold tracking-tight">Masuk ke Deep Pockets</h1>
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

        <p v-if="errorMsg" class="text-xs text-[var(--text-secondary)]">{{ errorMsg }}</p>

        <button
          type="submit"
          :disabled="auth.loading"
          class="btn-primary w-full rounded-xl py-3 text-sm font-bold disabled:opacity-50"
        >
          {{ auth.loading ? 'Memproses...' : 'Masuk' }}
        </button>
      </form>

      <p class="text-center text-xs text-[var(--text-secondary)]">
        Belum punya akun?
        <NuxtLink to="/register" class="text-accent font-semibold hover:underline">
          Daftar Sekarang
        </NuxtLink>
      </p>
    </div>
  </div>
</template>
