import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

// ── Interfaces ────────────────────────────────────────────────────────────────

export interface CartProduct {
  id: number
  name: string
  price: number
  imageUrl?: string | null
}

export interface CartItem {
  product: CartProduct
  quantity: number
}

// ── Store ─────────────────────────────────────────────────────────────────────

export const useCartStore = defineStore('cart', () => {
  // ── State ────────────────────────────────────────────────────────────────

  const items = ref<CartItem[]>([])

  // ── Getters ──────────────────────────────────────────────────────────────

  /** Total number of individual units across all lines. */
  const itemCount = computed<number>(() =>
    items.value.reduce((sum, item) => sum + item.quantity, 0),
  )

  /** Raw total price (quantity × unit price for each line). */
  const total = computed<number>(() =>
    items.value.reduce((sum, item) => sum + item.quantity * item.product.price, 0),
  )

  /** Whether the cart has at least one line. */
  const isEmpty = computed<boolean>(() => items.value.length === 0)

  // ── Actions ──────────────────────────────────────────────────────────────

  /**
   * Add a product to the cart.
   * If the product is already present, increment its quantity by `qty`.
   */
  function addItem(product: CartProduct, qty: number = 1): void {
    if (qty <= 0) return

    const existing = items.value.find((i) => i.product.id === product.id)

    if (existing) {
      existing.quantity += qty
    } else {
      items.value.push({ product: { ...product }, quantity: qty })
    }
  }

  /**
   * Remove a product line entirely from the cart.
   * No-ops silently when the product is not in the cart.
   */
  function removeItem(productId: number): void {
    items.value = items.value.filter((i) => i.product.id !== productId)
  }

  /**
   * Set the quantity for a specific product.
   * Passing `qty <= 0` removes the line.
   */
  function updateQuantity(productId: number, qty: number): void {
    if (qty <= 0) {
      removeItem(productId)
      return
    }

    const item = items.value.find((i) => i.product.id === productId)
    if (item) {
      item.quantity = qty
    }
  }

  /** Empty the cart completely. */
  function clearCart(): void {
    items.value = []
  }

  // ── Return ───────────────────────────────────────────────────────────────

  return {
    // State
    items,
    // Getters
    itemCount,
    total,
    isEmpty,
    // Actions
    addItem,
    removeItem,
    updateQuantity,
    clearCart,
  }
})
