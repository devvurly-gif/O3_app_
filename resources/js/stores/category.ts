import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'
import type { Category } from '@/types'

export const useCategoryStore = defineStore('category', () => {
  // ── State ────────────────────────────────────────────────────────────────
  const items = ref<Category[]>([])
  const loading = ref(false)
  const error = ref<unknown>(null)

  // ── Actions ──────────────────────────────────────────────────────────────
  async function fetchAll(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.get<Category[]>('/categories')
      items.value = Array.isArray(data) ? data : (data as any).data ?? []
    } catch (e: unknown) {
      error.value = e
    } finally {
      loading.value = false
    }
  }

  async function create(payload: Partial<Category>): Promise<Category> {
    const { data } = await http.post<Category>('/categories', payload)
    items.value.push(data)
    return data
  }

  async function update(id: number, payload: Partial<Category>): Promise<Category> {
    const { data } = await http.put<Category>(`/categories/${id}`, payload)
    const idx = items.value.findIndex((c) => c.id === id)
    if (idx !== -1) items.value[idx] = data
    return data
  }

  async function remove(id: number): Promise<void> {
    await http.delete(`/categories/${id}`)
    items.value = items.value.filter((c) => c.id !== id)
  }

  return { items, loading, error, fetchAll, create, update, remove }
})
