import { ref, computed } from 'vue'

const tvaActive = ref<boolean>(true)
const defaultTaxRate = ref<number>(20)
let initialized = false

export function useTaxSettings() {
  const initTaxSettings = async () => {
    if (initialized) return
    
    try {
      const response = await fetch('/api/tax-settings', {
        headers: { 'Accept': 'application/json' }
      })
      
      if (response.ok) {
        const data = await response.json()
        tvaActive.value = data.tva_active === 'true' || data.tva_active === true
        // `|| 20` would turn a legitimate 0% default back into 20%.
        const rate = parseFloat(data.default_tax_rate)
        defaultTaxRate.value = Number.isFinite(rate) ? rate : 20
      }
    } catch (error) {
      console.error('Failed to load tax settings:', error)
    }
    
    initialized = true
  }

  const isTaxActive = computed(() => tvaActive.value)
  const getTaxRate = computed(() => defaultTaxRate.value)

  const calculateTax = (htPrice: number, taxRate: number): number => {
    if (!isTaxActive.value) return 0
    return Math.round(htPrice * (taxRate / 100) * 100) / 100
  }

  const calculateTTC = (htPrice: number, taxRate: number): number => {
    if (!isTaxActive.value) return Math.round(htPrice * 100) / 100
    const tax = calculateTax(htPrice, taxRate)
    return Math.round((htPrice + tax) * 100) / 100
  }

  const calculateHT = (ttcPrice: number, taxRate: number): number => {
    if (!isTaxActive.value) return Math.round(ttcPrice * 100) / 100
    return Math.round((ttcPrice / (1 + (taxRate / 100))) * 100) / 100
  }

  return {
    isTaxActive,
    getTaxRate,
    calculateTax,
    calculateTTC,
    calculateHT,
    initTaxSettings,
    tvaActive,
    defaultTaxRate,
  }
}
