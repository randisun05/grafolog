import { ref, watchEffect } from 'vue'

const STORAGE_KEY = 'guratan_theme'

// 'system' (default) follows prefers-color-scheme via CSS alone (no
// data-theme attribute); 'light'/'dark' is an explicit user override,
// applied as data-theme so it wins over the OS preference either way.
const theme = ref(localStorage.getItem(STORAGE_KEY) ?? 'system')

watchEffect(() => {
  const root = document.documentElement
  if (theme.value === 'system') {
    root.removeAttribute('data-theme')
  } else {
    root.setAttribute('data-theme', theme.value)
  }
})

export function useTheme() {
  function setTheme(value) {
    theme.value = value
    localStorage.setItem(STORAGE_KEY, value)
  }

  function toggle() {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
    const currentlyDark = theme.value === 'dark' || (theme.value === 'system' && prefersDark)
    setTheme(currentlyDark ? 'light' : 'dark')
  }

  return { theme, setTheme, toggle }
}
