import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'
import type { Role } from '@/types'

export const useRoleStore = defineStore('role', () => {
  // ── State ────────────────────────────────────────────────────────────────
  const items = ref<Role[]>([])
  const loading = ref(false)
  const error = ref<unknown>(null)

  // ── Actions ──────────────────────────────────────────────────────────────
  async function fetchAll(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.get<Role[]>('/roles')
      items.value = data
    } catch (e: unknown) {
      error.value = e
    } finally {
      loading.value = false
    }
  }

  async function create(payload: Record<string, unknown>): Promise<Role> {
    const { data } = await http.post<Role>('/roles', payload)
    items.value.push(data)
    return data
  }

  async function update(id: number, payload: Record<string, unknown>): Promise<Role> {
    const { data } = await http.put<Role>(`/roles/${id}`, payload)
    const idx = items.value.findIndex((r) => r.id === id)
    if (idx !== -1) items.value[idx] = data
    return data
  }

  async function remove(id: number): Promise<void> {
    await http.delete(`/roles/${id}`)
    items.value = items.value.filter((r) => r.id !== id)
  }

  return { items, loading, error, fetchAll, create, update, remove }
})
