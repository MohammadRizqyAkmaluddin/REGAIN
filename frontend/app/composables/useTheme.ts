export function useTheme() {
  const theme = useState<'dark' | 'light'>('regain-theme', () => 'dark')

  function applyTheme(next: 'dark' | 'light') {
    theme.value = next
    if (!import.meta.client) return
    const root = document.documentElement
    if (next === 'dark') {
      root.classList.add('dark')
    } else {
      root.classList.remove('dark')
    }
    localStorage.setItem('regain_theme', next)
  }

  function initTheme() {
    if (!import.meta.client) return
    const saved = localStorage.getItem('regain_theme')
    if (saved === 'light' || saved === 'dark') {
      applyTheme(saved)
    } else {
      applyTheme('dark')
    }
  }

  function toggleTheme() {
    applyTheme(theme.value === 'dark' ? 'light' : 'dark')
  }

  return { theme, applyTheme, initTheme, toggleTheme }
}
