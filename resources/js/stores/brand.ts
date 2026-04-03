import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'
import type { Brand } from '@/types'

export const useBrandStore = defineStore('brand', () => {
  // ── State ────────────────────────────────────────────────────────────────
  const items = ref<Brand[]>([])
  const loading = ref(false)
  const error = ref<unknown>(null)

  // ── Actions ──────────────────────────────────────────────────────────────
  async function fetchAll(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.get<Brand[]>('/brands')
      items.value = Array.isArray(data) ? data : (data as any).data ?? []
    } catch (e: unknown) {
      error.value = e
    } finally {
      loading.value = false
    }
  }

  async function create(payload: Partial<Brand>): Promise<Brand> {
    const { data } = await http.post<Brand>('/brands', payload)
    items.value.push(data)
    return data
  }

  async function update(id: number, payload: Partial<Brand>): Promise<Brand> {
    const { data } = await http.put<Brand>(`/brands/${id}`, payload)
    const idx = items.value.findIndex((b) => b.id === id)
    if (idx !== -1) items.value[idx] = data
    return data
  }

  async function remove(id: number): Promise<void> {
    await http.delete(`/brands/${id}`)
    items.value = items.value.filter((b) => b.id !== id)
  }

  return { items, loading, error, fetchAll, create, update, remove }
})
