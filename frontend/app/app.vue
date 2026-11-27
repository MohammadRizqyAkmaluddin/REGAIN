<script setup lang="ts">
const { initTheme } = useTheme()
const showSplash = ref(true)

onMounted(() => {
  initTheme()
  window.setTimeout(() => {
    showSplash.value = false
  }, 750)
})
</script>

<template>
  <div class="relative min-h-dvh">
    <Transition name="splash-fade">
      <div
        v-if="showSplash"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-[var(--bg-canvas)] px-8"
        role="status"
        aria-label="Memuat Deep Pockets"
      >
        <img
          src="/logos/dark-full-transparent-logo.png"
          alt="Deep Pockets"
          class="w-[min(220px,62vw)] h-auto object-contain select-none dark:hidden"
        >
        <img
          src="/logos/light-full-transparent-logo.png"
          alt="Deep Pockets"
          class="hidden w-[min(220px,62vw)] h-auto object-contain select-none dark:block"
        >
      </div>
    </Transition>

    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </div>
</template>

<style scoped>
.splash-fade-leave-active {
  transition: opacity 280ms ease;
}
.splash-fade-leave-to {
  opacity: 0;
}
</style>
