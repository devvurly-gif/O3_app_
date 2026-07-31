import JsBarcode from 'jsbarcode'

const cache = new Map<string, string>()

/**
 * Render an EAN13 value to a PNG data URL via an offscreen canvas.
 * Cached per value since the same product can appear multiple times
 * (multiple copies) in one print/preview batch.
 */
export function renderBarcodeDataUrl(value: string): string {
  const cached = cache.get(value)
  if (cached) return cached

  const canvas = document.createElement('canvas')
  try {
    JsBarcode(canvas, value, {
      format: 'EAN13',
      displayValue: true,
      width: 2,
      height: 50,
      fontSize: 14,
      margin: 4,
    })
  } catch {
    // Not a valid EAN13 (wrong length/checksum) — fall back to Code128 so
    // the barcode still renders and stays scannable, just not as an EAN13.
    try {
      JsBarcode(canvas, value, {
        format: 'CODE128',
        displayValue: true,
        width: 2,
        height: 50,
        fontSize: 14,
        margin: 4,
      })
    } catch {
      return ''
    }
  }

  const url = canvas.toDataURL('image/png')
  cache.set(value, url)
  return url
}
