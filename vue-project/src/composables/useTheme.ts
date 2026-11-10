import { computed, ref } from 'vue'

export type ThemeMode = 'light' | 'dark'

const STORAGE_KEY = 'carease.theme'

function readInitialTheme(): ThemeMode {
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY)
    if (stored === 'light' || stored === 'dark') return stored
  } catch {
    /* ignore */
  }
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

const theme = ref<ThemeMode>(readInitialTheme())

function applyTheme(value: ThemeMode) {
  document.documentElement.dataset.theme = value
  document.documentElement.style.colorScheme = value
}

applyTheme(theme.value)

export function useTheme() {
  const isDark = computed(() => theme.value === 'dark')

  function setTheme(value: ThemeMode) {
    theme.value = value
    applyTheme(value)
    try {
      window.localStorage.setItem(STORAGE_KEY, value)
    } catch {
      /* ignore */
    }
  }

  function toggleTheme() {
    setTheme(theme.value === 'dark' ? 'light' : 'dark')
  }

  return { theme, isDark, setTheme, toggleTheme }
}
