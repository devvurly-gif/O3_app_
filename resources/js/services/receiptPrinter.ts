/**
 * Receipt printer service.
 * Supports ESC/POS thermal printers via Web Serial API
 * with HTML fallback via window.print().
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
declare global {
  interface Navigator {
    serial?: {
      requestPort(): Promise<any>
    }
  }
}

// ── ESC/POS Commands ──────────────────────────────────────────
const ESC = 0x1b
const GS = 0x1d

const CMD = {
  INIT: [ESC, 0x40],
  BOLD_ON: [ESC, 0x45, 0x01],
  BOLD_OFF: [ESC, 0x45, 0x00],
  ALIGN_LEFT: [ESC, 0x61, 0x00],
  ALIGN_CENTER: [ESC, 0x61, 0x01],
  ALIGN_RIGHT: [ESC, 0x61, 0x02],
  FONT_NORMAL: [ESC, 0x21, 0x00],
  FONT_DOUBLE_HEIGHT: [ESC, 0x21, 0x10],
  CUT: [GS, 0x56, 0x00],
  PARTIAL_CUT: [GS, 0x56, 0x01],
  OPEN_DRAWER: [ESC, 0x70, 0x00, 0x19, 0xfa],
  LINE_FEED: [0x0a],
}

interface TicketLine {
  designation: string
  quantity: number
  unit_price: number
  total: number
}

interface TicketData {
  reference: string
  date: string
  terminal: string
  cashier: string
  lines: TicketLine[]
  subtotal: number
  tax: number
  total: number
  paymentMethod: string
  amountGiven?: number
  change?: number
  companyName?: string
}

class ReceiptPrinter {
  private port: any = null
  private writer: WritableStreamDefaultWriter<Uint8Array> | null = null

  /**
   * Check if Web Serial API is available.
   */
  isSerialSupported(): boolean {
    return 'serial' in navigator
  }

  /**
   * Connect to a serial printer.
   */
  async connect(): Promise<boolean> {
    if (!this.isSerialSupported()) return false

    try {
      this.port = await navigator.serial.requestPort()
      await this.port.open({ baudRate: 9600 })
      this.writer = this.port.writable?.getWriter() ?? null
      return true
    } catch {
      return false
    }
  }

  /**
   * Disconnect from serial printer.
   */
  async disconnect(): Promise<void> {
    if (this.writer) {
      this.writer.releaseLock()
      this.writer = null
    }
    if (this.port) {
      await this.port.close()
      this.port = null
    }
  }

  /**
   * Print a receipt. Uses ESC/POS if connected, otherwise HTML fallback.
   */
  async print(data: TicketData): Promise<void> {
    if (this.writer) {
      await this.printEscPos(data)
    } else {
      this.printHtml(data)
    }
  }

  // ── ESC/POS printing ──────────────────────────────────────
  private async printEscPos(data: TicketData): Promise<void> {
    if (!this.writer) return

    const encoder = new TextEncoder()

    const send = async (bytes: number[]) => {
      await this.writer!.write(new Uint8Array(bytes))
    }

    const text = async (str: string) => {
      await this.writer!.write(encoder.encode(str))
    }

    await send(CMD.INIT)

    // Header
    await send(CMD.ALIGN_CENTER)
    await send(CMD.FONT_DOUBLE_HEIGHT)
    await send(CMD.BOLD_ON)
    await text(data.companyName ?? 'POS')
    await send(CMD.LINE_FEED)
    await send(CMD.FONT_NORMAL)
    await send(CMD.BOLD_OFF)
    await text(`Terminal: ${data.terminal}`)
    await send(CMD.LINE_FEED)
    await text(`Caissier: ${data.cashier}`)
    await send(CMD.LINE_FEED)
    await text(data.date)
    await send(CMD.LINE_FEED)
    await text('--------------------------------')
    await send(CMD.LINE_FEED)

    // Reference
    await send(CMD.BOLD_ON)
    await text(`Ticket: ${data.reference}`)
    await send(CMD.BOLD_OFF)
    await send(CMD.LINE_FEED)
    await text('--------------------------------')
    await send(CMD.LINE_FEED)

    // Lines
    await send(CMD.ALIGN_LEFT)
    for (const line of data.lines) {
      await text(`${line.designation}`)
      await send(CMD.LINE_FEED)
      await text(`  ${line.quantity} x ${line.unit_price.toFixed(2)}    ${line.total.toFixed(2)}`)
      await send(CMD.LINE_FEED)
    }

    await text('--------------------------------')
    await send(CMD.LINE_FEED)

    // Totals
    await send(CMD.ALIGN_RIGHT)
    await text(`Sous-total HT: ${data.subtotal.toFixed(2)}`)
    await send(CMD.LINE_FEED)
    await text(`TVA: ${data.tax.toFixed(2)}`)
    await send(CMD.LINE_FEED)
    await send(CMD.BOLD_ON)
    await send(CMD.FONT_DOUBLE_HEIGHT)
    await text(`TOTAL: ${data.total.toFixed(2)} MAD`)
    await send(CMD.FONT_NORMAL)
    await send(CMD.BOLD_OFF)
    await send(CMD.LINE_FEED)

    // Payment info
    await send(CMD.ALIGN_LEFT)
    await text(`Paiement: ${data.paymentMethod}`)
    await send(CMD.LINE_FEED)
    if (data.amountGiven !== undefined) {
      await text(`Donné: ${data.amountGiven.toFixed(2)}`)
      await send(CMD.LINE_FEED)
      await text(`Rendu: ${(data.change ?? 0).toFixed(2)}`)
      await send(CMD.LINE_FEED)
    }

    // Footer
    await send(CMD.ALIGN_CENTER)
    await send(CMD.LINE_FEED)
    await text('Merci de votre visite!')
    await send(CMD.LINE_FEED)
    await send(CMD.LINE_FEED)
    await send(CMD.LINE_FEED)

    // Cut & open drawer
    await send(CMD.PARTIAL_CUT)
    await send(CMD.OPEN_DRAWER)
  }

  // ── HTML fallback ─────────────────────────────────────────
  private printHtml(data: TicketData): void {
    const win = window.open('', '_blank', 'width=300,height=600')
    if (!win) return

    const linesHtml = data.lines
      .map(
        (l) => `
        <tr>
          <td colspan="3" style="padding-top:4px;font-size:12px">${l.designation}</td>
        </tr>
        <tr>
          <td style="font-size:11px;color:#666;padding-left:10px">${l.quantity} x ${l.unit_price.toFixed(2)}</td>
          <td></td>
          <td style="text-align:right;font-size:12px">${l.total.toFixed(2)}</td>
        </tr>`,
      )
      .join('')

    win.document.write(`<!DOCTYPE html>
<html><head><title>Ticket ${data.reference}</title>
<style>
  body { font-family: monospace; width: 280px; margin: 0 auto; padding: 10px; font-size: 12px; }
  h1 { text-align: center; margin: 0 0 4px; font-size: 18px; }
  .center { text-align: center; }
  .right { text-align: right; }
  .sep { border-top: 1px dashed #000; margin: 6px 0; }
  table { width: 100%; border-collapse: collapse; }
  .total { font-size: 16px; font-weight: bold; }
</style></head>
<body>
  <h1>${data.companyName ?? 'POS'}</h1>
  <p class="center" style="margin:2px 0">${data.terminal} | ${data.cashier}</p>
  <p class="center" style="margin:2px 0">${data.date}</p>
  <div class="sep"></div>
  <p class="center"><strong>Ticket: ${data.reference}</strong></p>
  <div class="sep"></div>
  <table>${linesHtml}</table>
  <div class="sep"></div>
  <p class="right">Sous-total HT: ${data.subtotal.toFixed(2)}</p>
  <p class="right">TVA: ${data.tax.toFixed(2)}</p>
  <p class="right total">TOTAL: ${data.total.toFixed(2)} MAD</p>
  <div class="sep"></div>
  <p>Paiement: ${data.paymentMethod}</p>
  ${data.amountGiven !== undefined ? `<p>Donné: ${data.amountGiven.toFixed(2)} | Rendu: ${(data.change ?? 0).toFixed(2)}</p>` : ''}
  <div class="sep"></div>
  <p class="center" style="margin-top:10px">Merci de votre visite!</p>
</body></html>`)

    win.document.close()
    win.focus()
    win.print()
    win.close()
  }
}

export const receiptPrinter = new ReceiptPrinter()
export type { TicketData, TicketLine }
