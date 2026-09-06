/**
 * Composable for number & currency formatting.
 * Reads `display.price_decimals` from the setting store so the
 * number of decimal places is driven by the per-tenant configuration.
 */
import { useSettingStore } from '@/stores/setting'

/**
 * Un montant tel que l'affichent les tableaux : deux decimales, groupees a la
 * marocaine, sans symbole — le « DH » est pose par le gabarit.
 *
 * La conversion en nombre n'est pas cosmetique. Les colonnes monetaires sont
 * castees `decimal:2` cote Laravel, et Eloquent serialise un decimal en chaine
 * JSON. Or `"103455.00".toLocaleString('fr-MA', ...)` appelle la methode de
 * String, qui ignore les options et rend la chaine telle quelle : l'historique
 * des documents affichait 103455.00 la ou son propre pied, calcule en memoire,
 * affichait 103.455,00.
 *
 * Hors du composable, donc sans dependance au store : ce formatage-la est
 * fixe et n'a pas a lire la configuration du tenant.
 */
export function formatAmount(value: number | string | null | undefined): string {
  return Number(value ?? 0).toLocaleString('fr-MA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export function useFormat() {
  const settingStore = useSettingStore()

  /** How many decimal places to show (driven by display.price_decimals, default 2) */
  function decimals(): number {
    return Number(settingStore.settings?.display?.price_decimals ?? 2)
  }

  /** Format a numeric value using the tenant decimal setting */
  function fmt(n: number | string | undefined | null): string {
    return Number(n ?? 0).toFixed(decimals())
  }

  /** Format as currency string (e.g. "1 234 DH" or "1 234.56 DH") */
  function currency(n: number | string | undefined | null): string {
    const d = decimals()
    const val = Number(n ?? 0)
    return val.toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d }) + ' DH'
  }

  /**
   * Format a date using the tenant's `locale.date_format` setting.
   * Accepts PHP-style tokens (the actual stored convention, e.g. "d/m/Y")
   * as well as the human-friendly tokens shown as a placeholder in the
   * settings UI (e.g. "DD/MM/YYYY"). Defaults to "d/m/Y".
   */
  function date(value: string | Date | undefined | null): string {
    if (!value) return '—'
    const d = value instanceof Date ? value : new Date(value)
    if (isNaN(d.getTime())) return '—'

    const pad = (n: number) => String(n).padStart(2, '0')
    const year = d.getFullYear()
    const month = d.getMonth() + 1
    const day = d.getDate()
    const tokens: Record<string, string> = {
      YYYY: String(year),
      YY: String(year).slice(-2),
      MM: pad(month),
      DD: pad(day),
      Y: String(year),
      y: String(year).slice(-2),
      m: pad(month),
      n: String(month),
      d: pad(day),
      j: String(day),
    }
    const pattern = settingStore.settings?.locale?.date_format || 'd/m/Y'
    return pattern.replace(/YYYY|YY|MM|DD|Y|y|m|n|j|d/g, (token) => tokens[token])
  }

  return { fmt, currency, decimals, date }
}
