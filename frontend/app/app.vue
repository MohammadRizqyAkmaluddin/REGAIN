<script setup lang="ts">
const { initTheme } = useTheme()
const showSplash = ref(true)

function preventGestureZoom(e: Event) {
  e.preventDefault()
}

function preventPinchTouch(e: TouchEvent) {
  if (e.touches.length > 1) {
    e.preventDefault()
  }
}

onMounted(() => {
  initTheme()
  document.addEventListener('gesturestart', preventGestureZoom, { passive: false })
  document.addEventListener('gesturechange', preventGestureZoom, { passive: false })
  document.addEventListener('gestureend', preventGestureZoom, { passive: false })
  document.addEventListener('touchmove', preventPinchTouch, { passive: false })

  window.setTimeout(() => {
    showSplash.value = false
  }, 750)
})

onBeforeUnmount(() => {
  document.removeEventListener('gesturestart', preventGestureZoom)
  document.removeEventListener('gesturechange', preventGestureZoom)
  document.removeEventListener('gestureend', preventGestureZoom)
  document.removeEventListener('touchmove', preventPinchTouch)
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
