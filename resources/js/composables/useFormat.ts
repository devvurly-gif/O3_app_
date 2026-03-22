/**
 * Composable for number & currency formatting.
 * Centralises the `fmt()` helper used across 6+ pages.
 */
export function useFormat() {
  /** Format a numeric value to 2 decimal places */
  function fmt(n: number | string | undefined | null): string {
    return Number(n ?? 0).toFixed(2)
  }

  /** Format as currency string (e.g. "1 234.56 DH") */
  function currency(n: number | string | undefined | null): string {
    const val = Number(n ?? 0)
    return val.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' DH'
  }

  return { fmt, currency }
}
