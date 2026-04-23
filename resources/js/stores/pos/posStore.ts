import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import http from '@/services/http'

export interface DraftCustomer {
  id: number
  tp_title: string
  tp_phone: string | null
  tp_email: string | null
  type_compte: 'normal' | 'en_compte'
  encours_actuel: number
  seuil_credit: number
  price_list_id: number | null
}

export interface DraftTicket {
  id: string
  label: string
  customer: DraftCustomer | null
  cart: CartItem[]
  created_at: string
}

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

  // ── Held / parked tickets (per-session, persisted in localStorage) ──────
  const draftTickets = ref<DraftTicket[]>([])
  const activeCustomer = ref<DraftCustomer | null>(null)
  const draftCounter = ref(1)

  function draftsStorageKey(): string | null {
    const sid = currentSession.value?.id
    return sid ? `pos.drafts.session.${sid}` : null
  }

  function persistDrafts(): void {
    const key = draftsStorageKey()
    if (!key) return
    try {
      const payload = {
        drafts: draftTickets.value,
        counter: draftCounter.value,
      }
      localStorage.setItem(key, JSON.stringify(payload))
    } catch {
      /* quota errors are non-fatal */
    }
  }

  function loadDraftsFromStorage(): void {
    const key = draftsStorageKey()
    if (!key) {
      draftTickets.value = []
      draftCounter.value = 1
      return
    }
    try {
      const raw = localStorage.getItem(key)
      if (!raw) {
        draftTickets.value = []
        draftCounter.value = 1
        return
      }
      const parsed = JSON.parse(raw) as { drafts?: DraftTicket[]; counter?: number }
      draftTickets.value = Array.isArray(parsed.drafts) ? parsed.drafts : []
      draftCounter.value = typeof parsed.counter === 'number' ? parsed.counter : draftTickets.value.length + 1
    } catch {
      draftTickets.value = []
      draftCounter.value = 1
    }
  }

  // Keep storage in sync whenever drafts mutate.
  watch(draftTickets, () => persistDrafts(), { deep: true })
  watch(draftCounter, () => persistDrafts())

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
      loadDraftsFromStorage()
    } catch {
      currentSession.value = null
      currentTerminal.value = null
      draftTickets.value = []
      draftCounter.value = 1
    }
  }

  async function openSession(terminalId: number, openingCash: number): Promise<void> {
    const { data } = await http.post<PosSessionData>('/pos/sessions/open', {
      pos_terminal_id: terminalId,
      opening_cash: openingCash,
    })
    currentSession.value = data
    currentTerminal.value = data.terminal ?? null
    loadDraftsFromStorage()
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

  /**
   * Re-price every line in the cart using the PriceResolver for the given
   * customer (null = walk-in / no assigned price list). Quantities are
   * preserved so tier-based pricing continues to match. Silently no-ops
   * when the cart is empty.
   */
  async function refreshPricesForCustomer(customerId: number | null): Promise<void> {
    if (!cart.value.length) return
    try {
      const { data } = await http.post<{
        items: Array<{
          product_id: number
          unit_price: number
          tax_percent: number
        }>
      }>('/pos/products/reprice', {
        customer_id: customerId,
        items: cart.value.map((i) => ({ product_id: i.product_id, quantity: i.quantity })),
      })
      const byId = new Map(data.items.map((r) => [r.product_id, r]))
      for (const line of cart.value) {
        const priced = byId.get(line.product_id)
        if (!priced) continue
        line.unit_price = priced.unit_price
        line.tax_percent = priced.tax_percent
      }
    } catch {
      // Leave cart untouched on failure — the UI will surface the error elsewhere.
    }
  }

  // ── Held tickets: park / restore / discard ────────────────────────────

  function generateDraftId(): string {
    return 'drf_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8)
  }

  function buildDraftLabel(customer: DraftCustomer | null): string {
    if (customer?.tp_title) return customer.tp_title
    const n = draftCounter.value
    draftCounter.value = n + 1
    return `Ticket #${n}`
  }

  /**
   * Park the current cart (and optional customer) as a held ticket, then
   * empty the active cart so the cashier can start a new ticket. Returns
   * the id of the saved draft, or null when there is nothing to park.
   */
  function parkCurrentTicket(customer: DraftCustomer | null = null): string | null {
    if (!cart.value.length) return null
    const effectiveCustomer = customer ?? activeCustomer.value
    const draft: DraftTicket = {
      id: generateDraftId(),
      label: buildDraftLabel(effectiveCustomer),
      customer: effectiveCustomer ? { ...effectiveCustomer } : null,
      // Deep-copy the cart so later mutations on the live cart don't leak in.
      cart: cart.value.map((line) => ({ ...line })),
      created_at: new Date().toISOString(),
    }
    draftTickets.value.push(draft)
    cart.value = []
    activeCustomer.value = null
    return draft.id
  }

  /**
   * Load a held ticket into the active cart. Any current cart is parked
   * first (so nothing is lost). Returns the restored draft (with its
   * customer) so the UI can sync its local customer state, or null when
   * the id does not exist.
   */
  function restoreDraftTicket(id: string): DraftTicket | null {
    const idx = draftTickets.value.findIndex((d) => d.id === id)
    if (idx === -1) return null

    // If there's something in the active cart, park it first.
    if (cart.value.length) {
      parkCurrentTicket(activeCustomer.value)
    }

    const draft = draftTickets.value[idx]
    cart.value = draft.cart.map((line) => ({ ...line }))
    activeCustomer.value = draft.customer ? { ...draft.customer } : null
    draftTickets.value.splice(idx, 1)
    return draft
  }

  function discardDraftTicket(id: string): void {
    draftTickets.value = draftTickets.value.filter((d) => d.id !== id)
  }

  function clearAllDrafts(): void {
    draftTickets.value = []
    draftCounter.value = 1
    const key = draftsStorageKey()
    if (key) {
      try { localStorage.removeItem(key) } catch { /* noop */ }
    }
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
    activeCustomer.value = null
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
    draftTickets,
    activeCustomer,
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
    refreshPricesForCustomer,
    parkCurrentTicket,
    restoreDraftTicket,
    discardDraftTicket,
    clearAllDrafts,
    checkout,
    fetchTickets,
    voidTicket,
  }
})
