let mediaQueryListenerBound = false

export function useTheme() {
  const theme = useState<'dark' | 'light'>('deep-pockets-theme', () => 'dark')

  function syncWithSystem(isDark: boolean) {
    const next = isDark ? 'dark' : 'light'
    theme.value = next
    if (!import.meta.client) return
    document.documentElement.classList.toggle('dark', isDark)
  }

  function initTheme() {
    if (!import.meta.client) return

    // Remove legacy manual theme preference so OS setting is always respected
    try {
      localStorage.removeItem('regain_theme')
    } catch {
      // ignore storage errors
    }

    const mq = window.matchMedia('(prefers-color-scheme: dark)')
    syncWithSystem(mq.matches)

    if (!mediaQueryListenerBound) {
      mediaQueryListenerBound = true
      const onChange = (event: MediaQueryListEvent) => {
        syncWithSystem(event.matches)
      }
      if (typeof mq.addEventListener === 'function') {
        mq.addEventListener('change', onChange)
      } else {
        mq.addListener(onChange)
      }
    }
  }

  return { theme, initTheme }
}
