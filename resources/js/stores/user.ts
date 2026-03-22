import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'
import type { User } from '@/types'

export const useUserStore = defineStore('user', () => {
  const items = ref<User[]>([])
  const loading = ref(false)
  const error = ref<unknown>(null)

  async function fetchAll(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.get<User[]>('/users')
      items.value = data
    } catch (e: unknown) {
      error.value = e
    } finally {
      loading.value = false
    }
  }

  async function create(payload: Partial<User>): Promise<User> {
    const { data } = await http.post<User>('/users', payload)
    items.value.push(data)
    return data
  }

  async function update(id: number, payload: Partial<User>): Promise<User> {
    const { data } = await http.put<User>(`/users/${id}`, payload)
    const idx = items.value.findIndex((u) => u.id === id)
    if (idx !== -1) items.value[idx] = data
    return data
  }

  async function remove(id: number): Promise<void> {
    await http.delete(`/users/${id}`)
    items.value = items.value.filter((u) => u.id !== id)
  }

  return { items, loading, error, fetchAll, create, update, remove }
})
