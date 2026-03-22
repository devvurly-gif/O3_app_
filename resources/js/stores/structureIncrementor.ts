import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'
import type { StructureIncrementor } from '@/types'

export const useStructureIncrementorStore = defineStore('structureIncrementor', () => {
  const items = ref<StructureIncrementor[]>([])
  const loading = ref(false)
  const error = ref<unknown>(null)

  async function fetchAll(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.get<StructureIncrementor[]>('/structures')
      items.value = data
    } catch (e: unknown) {
      error.value = e
    } finally {
      loading.value = false
    }
  }

  async function create(payload: Partial<StructureIncrementor>): Promise<StructureIncrementor> {
    const { data } = await http.post<StructureIncrementor>('/structures', payload)
    items.value.push(data)
    return data
  }

  async function update(id: number, payload: Partial<StructureIncrementor>): Promise<StructureIncrementor> {
    const { data } = await http.put<StructureIncrementor>(`/structures/${id}`, payload)
    const idx = items.value.findIndex((s) => s.id === id)
    if (idx !== -1) items.value[idx] = data
    return data
  }

  async function remove(id: number): Promise<void> {
    await http.delete(`/structures/${id}`)
    items.value = items.value.filter((s) => s.id !== id)
  }

  return { items, loading, error, fetchAll, create, update, remove }
})
