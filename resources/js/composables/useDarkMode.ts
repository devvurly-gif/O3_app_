import { ref, watch, onMounted } from 'vue'

const STORAGE_KEY = 'theme'
type Theme = 'light' | 'dark' | 'system'

const isDark = ref(false)
const theme = ref<Theme>('system')
let initialized = false

function applyTheme() {
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
  const shouldBeDark = theme.value === 'dark' || (theme.value === 'system' && prefersDark)

  isDark.value = shouldBeDark
  document.documentElement.classList.toggle('dark', shouldBeDark)
}

export function useDarkMode() {
  if (!initialized) {
    initialized = true
    const stored = localStorage.getItem(STORAGE_KEY) as Theme | null
    theme.value = stored ?? 'system'
    applyTheme()

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyTheme)
    watch(theme, (val) => {
      localStorage.setItem(STORAGE_KEY, val)
      applyTheme()
    })
  }

  function toggle() {
    theme.value = isDark.value ? 'light' : 'dark'
  }

  function setTheme(t: Theme) {
    theme.value = t
  }

  return { isDark, theme, toggle, setTheme }
}
