import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'
import type { DocumentIncrementor } from '@/types'

export const useDocumentIncrementorStore = defineStore('documentIncrementor', () => {
  const items = ref<DocumentIncrementor[]>([])
  const loading = ref(false)
  const error = ref<unknown>(null)

  async function fetchAll(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.get<DocumentIncrementor[]>('/document-incrementors')
      items.value = data
    } catch (e: unknown) {
      error.value = e
    } finally {
      loading.value = false
    }
  }

  async function create(payload: Partial<DocumentIncrementor>): Promise<DocumentIncrementor> {
    const { data } = await http.post<DocumentIncrementor>('/document-incrementors', payload)
    items.value.push(data)
    return data
  }

  async function update(id: number, payload: Partial<DocumentIncrementor>): Promise<DocumentIncrementor> {
    const { data } = await http.put<DocumentIncrementor>(`/document-incrementors/${id}`, payload)
    const idx = items.value.findIndex((d) => d.id === id)
    if (idx !== -1) items.value[idx] = data
    return data
  }

  async function remove(id: number): Promise<void> {
    await http.delete(`/document-incrementors/${id}`)
    items.value = items.value.filter((d) => d.id !== id)
  }

  return { items, loading, error, fetchAll, create, update, remove }
})
