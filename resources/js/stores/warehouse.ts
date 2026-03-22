import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'
import type { Warehouse } from '@/types'

export const useWarehouseStore = defineStore('warehouse', () => {
  // ── State ────────────────────────────────────────────────────────────────
  const items = ref<Warehouse[]>([])
  const loading = ref(false)
  const error = ref<unknown>(null)

  // ── Actions ──────────────────────────────────────────────────────────────
  async function fetchAll(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.get<Warehouse[]>('/warehouses')
      items.value = data
    } catch (e: unknown) {
      error.value = e
    } finally {
      loading.value = false
    }
  }

  async function create(payload: Partial<Warehouse>): Promise<Warehouse> {
    const { data } = await http.post<Warehouse>('/warehouses', payload)
    items.value.push(data)
    return data
  }

  async function update(id: number, payload: Partial<Warehouse>): Promise<Warehouse> {
    const { data } = await http.put<Warehouse>(`/warehouses/${id}`, payload)
    const idx = items.value.findIndex((w) => w.id === id)
    if (idx !== -1) items.value[idx] = data
    return data
  }

  async function remove(id: number): Promise<void> {
    await http.delete(`/warehouses/${id}`)
    items.value = items.value.filter((w) => w.id !== id)
  }

  return { items, loading, error, fetchAll, create, update, remove }
})
