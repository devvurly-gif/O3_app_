import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import http from '@/services/http'

export interface CartItem {
  product_id: number
  designation: string
  reference: string | null
  quantity: number
  unit_price: number
  unit: string
  discount_percent: number
  tax_percent: number
  stock: number
  image_url?: string | null
}

export interface PosTerminal {
  id: number
  name: string
  code: string
  warehouse_id: number
  is_active: boolean
  warehouse?: { id: number; wh_title: string }
}

export interface PosSessionData {
  id: number
  pos_terminal_id: number
  user_id: number
  opened_at: string
  closed_at: string | null
  opening_cash: number
  closing_cash: number | null
  expected_cash: number | null
  cash_difference: number | null
  notes: string | null
  terminal?: PosTerminal
}

export interface PosProduct {
  id: number
  p_title: string
  p_code: string
  p_sku: string | null
  p_ean13: string | null
  p_salePrice: number
  p_taxRate: number
  p_unit: string
  p_status: boolean
  category_id: number | null
  category?: { id: number; ctg_title: string }
  primary_image?: { url: string } | null
  stock: number
}

export const usePosStore = defineStore('pos', () => {
  const currentSession = ref<PosSessionData | null>(null)
  const currentTerminal = ref<PosTerminal | null>(null)
  const cart = ref<CartItem[]>([])
  const products = ref<PosProduct[]>([])
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const tickets = ref<any[]>([])
  const searchQuery = ref('')
  const selectedCategoryId = ref<number | null>(null)
  const loadingProducts = ref(false)

  // ── Getters ────────────────────────────────────────────────────
  const cartSubtotal = computed(() =>
    cart.value.reduce((sum, item) => {
      const lineHt = item.quantity * item.unit_price * (1 - item.discount_percent / 100)
      return sum + lineHt
    }, 0),
  )

  const cartTax = computed(() =>
    cart.value.reduce((sum, item) => {
      const lineHt = item.quantity * item.unit_price * (1 - item.discount_percent / 100)
      return sum + lineHt * (item.tax_percent / 100)
    }, 0),
  )

  const cartTotal = computed(() => cartSubtotal.value + cartTax.value)

  const cartItemCount = computed(() => cart.value.reduce((s, i) => s + i.quantity, 0))

  const hasOpenSession = computed(() => currentSession.value !== null && currentSession.value.closed_at === null)

  // ── Actions ────────────────────────────────────────────────────
  async function fetchCurrentSession(): Promise<void> {
    try {
      const resp = await http.get<PosSessionData | null>('/pos/sessions/current')
      const session = resp.data && resp.data.id ? resp.data : null
      currentSession.value = session
      currentTerminal.value = session?.terminal ?? null
    } catch {
      currentSession.value = null
      currentTerminal.value = null
    }
  }

  async function openSession(terminalId: number, openingCash: number): Promise<void> {
    const { data } = await http.post<PosSessionData>('/pos/sessions/open', {
      pos_terminal_id: terminalId,
      opening_cash: openingCash,
    })
    currentSession.value = data
    currentTerminal.value = data.terminal ?? null
  }

  async function closeSession(closingCash: number, notes?: string): Promise<PosSessionData> {
    const { data } = await http.post<PosSessionData>(
      `/pos/sessions/${currentSession.value!.id}/close`,
      { closing_cash: closingCash, notes },
    )
    currentSession.value = data
    return data
  }

  async function fetchProducts(): Promise<void> {
    loadingProducts.value = true
    try {
      const params: Record<string, unknown> = {}
      if (searchQuery.value) params.search = searchQuery.value
      if (selectedCategoryId.value) params.category_id = selectedCategoryId.value
      const { data } = await http.get<PosProduct[]>('/pos/products', { params })
      products.value = data
    } finally {
      loadingProducts.value = false
    }
  }

  function addToCart(product: PosProduct): void {
    const existing = cart.value.find((i) => i.product_id === product.id)
    if (existing) {
      existing.quantity += 1
      return
    }
    cart.value.push({
      product_id: product.id,
      designation: product.p_title,
      reference: product.p_sku ?? product.p_code,
      quantity: 1,
      unit_price: product.p_salePrice,
      unit: product.p_unit,
      discount_percent: 0,
      tax_percent: product.p_taxRate,
      stock: product.stock,
      image_url: product.primary_image?.url ?? null,
    })
  }

  function updateCartItemQty(productId: number, qty: number): void {
    const item = cart.value.find((i) => i.product_id === productId)
    if (!item) return
    if (qty <= 0) {
      removeFromCart(productId)
      return
    }
    item.quantity = qty
  }

  function removeFromCart(productId: number): void {
    cart.value = cart.value.filter((i) => i.product_id !== productId)
  }

  function clearCart(): void {
    cart.value = []
  }

  async function checkout(
    payments: { amount: number; method: string; reference?: string }[],
    customerId?: number | null,
  ): Promise<unknown> {
    const { data } = await http.post('/pos/tickets', {
      items: cart.value.map((item) => ({
        product_id: item.product_id,
        designation: item.designation,
        reference: item.reference,
        quantity: item.quantity,
        unit_price: item.unit_price,
        unit: item.unit,
        discount_percent: item.discount_percent,
        tax_percent: item.tax_percent,
      })),
      payments,
      customer_id: customerId ?? null,
    })
    clearCart()
    return data
  }

  async function fetchTickets(): Promise<void> {
    const { data } = await http.get('/pos/tickets')
    tickets.value = data
  }

  async function voidTicket(ticketId: number): Promise<void> {
    await http.post(`/pos/tickets/${ticketId}/void`)
    await fetchTickets()
  }

  return {
    currentSession,
    currentTerminal,
    cart,
    products,
    tickets,
    searchQuery,
    selectedCategoryId,
    loadingProducts,
    cartSubtotal,
    cartTax,
    cartTotal,
    cartItemCount,
    hasOpenSession,
    fetchCurrentSession,
    openSession,
    closeSession,
    fetchProducts,
    addToCart,
    updateCartItemQty,
    removeFromCart,
    clearCart,
    checkout,
    fetchTickets,
    voidTicket,
  }
})
