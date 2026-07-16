import { computed } from 'vue'
import type { ComputedRef } from 'vue'
import { useCartStore } from '@/stores/useCartStore'

// ── Types ─────────────────────────────────────────────────────────────────────

export interface CartSummary {
  /** Total number of individual units in the cart. */
  itemCount: ComputedRef<number>
  /** Human-readable total (e.g. "1 234,56 MAD"). */
  formattedTotal: ComputedRef<string>
  /** Whether the cart is empty. */
  isEmpty: ComputedRef<boolean>
}

// ── Composable ────────────────────────────────────────────────────────────────

/**
 * Exposes a read-only summary of the cart.
 * Use this in components that need to display cart info
 * without needing full cart mutation capabilities.
 */
export function useCartSummary(): CartSummary {
  const cart = useCartStore()

  /** Total number of individual units (delegates to store getter). */
  const itemCount = computed<number>(() => cart.itemCount)

  /**
   * Total price formatted with Intl.NumberFormat (French locale, MAD currency).
   * Example output: "1 234,56 MAD"
   */
  const formattedTotal = computed<string>(() =>
    new Intl.NumberFormat('fr-MA', {
      style: 'currency',
      currency: 'MAD',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(cart.total),
  )

  /** True when the cart contains no lines. */
  const isEmpty = computed<boolean>(() => cart.isEmpty)

  return {
    itemCount,
    formattedTotal,
    isEmpty,
  }
}
