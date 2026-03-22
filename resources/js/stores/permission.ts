import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'
import type { Permission } from '@/types'

export const usePermissionStore = defineStore('permission', () => {
  // ── State ────────────────────────────────────────────────────────────────
  const items = ref<Permission[]>([])
  const grouped = ref<Record<string, Permission[]>>({})
  const loading = ref(false)
  const error = ref<unknown>(null)

  // ── Actions ──────────────────────────────────────────────────────────────
  async function fetchAll(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.get<Permission[]>('/permissions')
      items.value = data
    } catch (e: unknown) {
      error.value = e
    } finally {
      loading.value = false
    }
  }

  async function fetchGrouped(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.get<Record<string, Permission[]>>('/permissions/grouped')
      grouped.value = data
    } catch (e: unknown) {
      error.value = e
    } finally {
      loading.value = false
    }
  }

  return { items, grouped, loading, error, fetchAll, fetchGrouped }
})
