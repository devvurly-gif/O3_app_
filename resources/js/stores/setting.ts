import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'
import type { Settings } from '@/types'

export const useSettingStore = defineStore('setting', () => {
  const settings = ref<Settings>({})
  const loading = ref(false)
  const error = ref<unknown>(null)

  async function fetchAll(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.get<Settings>('/settings')
      settings.value = data
    } catch (e: unknown) {
      error.value = e
    } finally {
      loading.value = false
    }
  }

  async function save(domain: string, values: Record<string, string>): Promise<void> {
    await http.post('/settings', { domain, settings: values })
    settings.value[domain] = { ...(settings.value[domain] ?? {}), ...values }
  }

  async function remove(domain: string, key: string): Promise<void> {
    await http.delete('/settings', { data: { domain, key } })
    if (settings.value[domain]) {
      delete settings.value[domain][key]
    }
  }

  return { settings, loading, error, fetchAll, save, remove }
})
