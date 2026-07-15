import { computed } from 'vue'
import { useSettingStore } from '@/stores/setting'

export function useNumberFormat() {
  const settingStore = useSettingStore()

  // Get price decimal places from settings (default to 2)
  const priceDecimalPlaces = computed(() => {
    const decimals = settingStore.settings.value?.general?.price_decimals
    const value = parseInt(String(decimals), 10)
    // Only allow 2, 3, or 4 decimals
    if ([2, 3, 4].includes(value)) {
      return value
    }
    return 2 // default to 2
  })

  /**
   * Format a price value with configurable decimal places
   * Returns formatted string with MAD currency symbol
   */
  const formatPrice = (value: number): string => {
    const num = Number(value)
    if (isNaN(num)) return '0.00 MAD'
    
    const decimals = priceDecimalPlaces.value
    const formatted = num.toFixed(decimals)
    return `${formatted} MAD`
  }

  /**
   * Format a price using Intl.NumberFormat with locale and currency
   * Uses configurable decimal places from settings
   */
  const formatPriceIntl = (value: number): string => {
    return new Intl.NumberFormat('fr-MA', {
      style: 'currency',
      currency: 'MAD',
      minimumFractionDigits: priceDecimalPlaces.value,
      maximumFractionDigits: priceDecimalPlaces.value
    }).format(value)
  }

  /**
   * Format a quantity - always returns whole numbers (no decimals)
   */
  const formatQuantity = (value: number): string => {
    const num = Number(value)
    if (isNaN(num)) return '0'
    return Math.floor(num).toString()
  }

  /**
   * Format a quantity for display in UI (e.g., "5 pcs")
   */
  const formatQuantityDisplay = (value: number, unit?: string): string => {
    const qty = formatQuantity(value)
    return unit ? `${qty} ${unit}` : qty
  }

  return {
    formatPrice,
    formatPriceIntl,
    formatQuantity,
    formatQuantityDisplay,
    priceDecimalPlaces
  }
}
