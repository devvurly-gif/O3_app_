import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'
import type { PriceList, PriceListItem } from '@/types'

export const usePriceListStore = defineStore('priceList', () => {
  // ── State ────────────────────────────────────────────────────────────────
  const items = ref<PriceList[]>([])
  const current = ref<PriceList | null>(null)
  const loading = ref(false)
  const error = ref<unknown>(null)

  // ── Actions (lists) ──────────────────────────────────────────────────────
  async function fetchAll(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.get<PriceList[]>('/price-lists')
      items.value = Array.isArray(data) ? data : ((data as any).data ?? [])
    } catch (e: unknown) {
      error.value = e
    } finally {
      loading.value = false
    }
  }

  async function fetchOne(id: number): Promise<PriceList> {
    loading.value = true
    try {
      const { data } = await http.get<PriceList>(`/price-lists/${id}`)
      const list = (data as any).data ?? data
      current.value = list as PriceList
      return current.value
    } finally {
      loading.value = false
    }
  }

  async function create(payload: Partial<PriceList>): Promise<PriceList> {
    const { data } = await http.post<PriceList>('/price-lists', payload)
    const list = ((data as any).data ?? data) as PriceList
    items.value.push(list)
    return list
  }

  async function update(id: number, payload: Partial<PriceList>): Promise<PriceList> {
    const { data } = await http.put<PriceList>(`/price-lists/${id}`, payload)
    const list = ((data as any).data ?? data) as PriceList
    const idx = items.value.findIndex((l) => l.id === id)
    if (idx !== -1) items.value[idx] = { ...items.value[idx], ...list }
    return list
  }

  async function remove(id: number): Promise<void> {
    await http.delete(`/price-lists/${id}`)
    items.value = items.value.filter((l) => l.id !== id)
  }

  // ── Actions (items) ──────────────────────────────────────────────────────
  async function upsertItems(
    listId: number,
    rows: Array<{
      product_id: number
      price_ht: number
      min_qty?: number
      valid_from?: string | null
      valid_to?: string | null
    }>,
  ): Promise<PriceListItem[]> {
    const { data } = await http.post<PriceListItem[]>(`/price-lists/${listId}/items`, { items: rows })
    const payload = ((data as any).data ?? data) as PriceListItem[]
    if (current.value && current.value.id === listId) {
      await fetchOne(listId)
    }
    return payload
  }

  async function removeItem(listId: number, itemId: number): Promise<void> {
    await http.delete(`/price-lists/${listId}/items/${itemId}`)
    if (current.value && current.value.id === listId && current.value.items) {
      current.value.items = current.value.items.filter((i) => i.id !== itemId)
    }
  }

  return {
    items,
    current,
    loading,
    error,
    fetchAll,
    fetchOne,
    create,
    update,
    remove,
    upsertItems,
    removeItem,
  }
})
