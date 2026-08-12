import { ref, computed } from 'vue'
import http from '@/services/http'

const tvaActive = ref<boolean>(true)
const defaultTaxRate = ref<number>(20)
let initialized = false
let inFlight: Promise<void> | null = null

export function useTaxSettings() {
  const initTaxSettings = async () => {
    if (initialized) return
    if (inFlight) return inFlight // two components mounting at once share one call

    inFlight = (async () => {
      try {
        // /api/tax-settings sits behind auth:sanctum — a bare fetch() carries
        // no Bearer token, so it always 401'd and every tenant kept 20%.
        const { data } = await http.get('/tax-settings')

        tvaActive.value = data.tva_active === 'true' || data.tva_active === true
        // `|| 20` would turn a legitimate 0% default back into 20%.
        const rate = parseFloat(data.default_tax_rate)
        defaultTaxRate.value = Number.isFinite(rate) ? rate : 20
        initialized = true
      } catch (error) {
        // Leave `initialized` false so the next caller retries instead of
        // freezing the fallback rate for the whole session.
        console.error('Failed to load tax settings:', error)
      } finally {
        inFlight = null
      }
    })()

    return inFlight
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
