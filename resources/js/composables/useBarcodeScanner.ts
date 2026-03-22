import { onMounted, onUnmounted } from 'vue'

/**
 * Composable that listens for barcode scanner input.
 * Barcode scanners type characters very quickly (<50ms between keystrokes)
 * and end with Enter.
 */
export function useBarcodeScanner(callback: (code: string) => void) {
  let buffer = ''
  let lastKeyTime = 0
  const MAX_INTERVAL = 50 // ms between rapid key presses

  function onKeyDown(e: KeyboardEvent) {
    const now = Date.now()

    // Ignore if an input/textarea is focused (unless it's a scanner — fast typing)
    const target = e.target as HTMLElement
    const isInput = target.tagName === 'INPUT' || target.tagName === 'TEXTAREA'

    if (e.key === 'Enter' && buffer.length >= 3) {
      e.preventDefault()
      callback(buffer)
      buffer = ''
      return
    }

    // If too slow, reset buffer (user typing normally)
    if (now - lastKeyTime > MAX_INTERVAL && buffer.length > 0) {
      // If in an input, don't interfere with normal typing
      if (isInput) {
        buffer = ''
        lastKeyTime = now
        return
      }
      buffer = ''
    }

    lastKeyTime = now

    // Only accumulate printable single characters
    if (e.key.length === 1) {
      buffer += e.key
    }
  }

  onMounted(() => {
    window.addEventListener('keydown', onKeyDown, true)
  })

  onUnmounted(() => {
    window.removeEventListener('keydown', onKeyDown, true)
  })
}
