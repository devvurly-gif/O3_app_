import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'

export interface ModuleItem {
  id: number
  name: string
  display_name: string
  description: string | null
  is_active: boolean
  license_key: string | null
  licensed_until: string | null
  settings: Record<string, unknown> | null
}

export const useModuleStore = defineStore('module', () => {
  const items = ref<ModuleItem[]>([])
  const loading = ref(false)

  async function fetchAll(): Promise<void> {
    loading.value = true
    try {
      const { data } = await http.get<ModuleItem[]>('/modules')
      items.value = data
    } finally {
      loading.value = false
    }
  }

  async function update(id: number, payload: Partial<ModuleItem>): Promise<ModuleItem> {
    const { data } = await http.patch<ModuleItem>(`/modules/${id}`, payload)
    const idx = items.value.findIndex((m) => m.id === id)
    if (idx !== -1) items.value[idx] = data
    return data
  }

  return { items, loading, fetchAll, update }
})
