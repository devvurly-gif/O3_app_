import { ref, readonly } from 'vue'

// QZ Tray singleton state — shared across all component instances
const isConnected = ref(false)
const isConnecting = ref(false)
let qz: any = null
let reconnectTimer: ReturnType<typeof setTimeout> | null = null

const QZ_CDN = 'https://cdn.qz.io/2.2.4/qz-tray.js'

function loadScript(): Promise<void> {
  return new Promise((resolve, reject) => {
    if ((window as any).qz) { resolve(); return }
    const s = document.createElement('script')
    s.src = QZ_CDN
    s.onload = () => resolve()
    s.onerror = () => reject(new Error('QZ Tray script load failed'))
    document.head.appendChild(s)
  })
}

async function connect(): Promise<void> {
  if (isConnecting.value || isConnected.value) return
  isConnecting.value = true
  try {
    await loadScript()
    qz = (window as any).qz

    // Unsigned mode: resolve() with no certificate/signature.
    // QZ Tray will show a one-time "Allow unsigned" popup on first connection,
    // then remember the choice. After that, all connections are silent.
    qz.security.setCertificatePromise((resolve: any) => resolve())
    qz.security.setSignatureAlgorithm('SHA512')
    qz.security.setSignaturePromise((_toSign: any, resolve: any) => resolve())

    qz.websocket.setClosedCallbacks(() => {
      isConnected.value = false
      scheduleReconnect()
    })

    // Try insecure ws://localhost:8182 first (no SSL cert needed on localhost)
    await qz.websocket.connect({
      host: 'localhost',
      port: { secure: [8181], insecure: [8182] },
      usingSecure: false,
    })
    isConnected.value = true
  } catch {
    isConnected.value = false
    scheduleReconnect()
  } finally {
    isConnecting.value = false
  }
}

function scheduleReconnect() {
  if (reconnectTimer) return
  reconnectTimer = setTimeout(() => {
    reconnectTimer = null
    connect()
  }, 8000)
}

/**
 * Print raw HTML to the named printer via QZ Tray.
 * Returns true on success, false if QZ Tray is not connected or printer not found.
 */
async function printHtml(printerName: string, html: string): Promise<boolean> {
  if (!isConnected.value || !qz) return false
  try {
    const printer = await qz.printers.find(printerName)
    const config = qz.configs.create(printer, { copies: 1 })
    const data = [{ type: 'pixel', format: 'html', flavor: 'plain', data: html }]
    await qz.print(config, data)
    return true
  } catch (e) {
    console.warn('[QZ Tray] print error:', e)
    return false
  }
}

export function useQzTray() {
  return {
    isConnected: readonly(isConnected),
    isConnecting: readonly(isConnecting),
    connect,
    printHtml,
  }
}
