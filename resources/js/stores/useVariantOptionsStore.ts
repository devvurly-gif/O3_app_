import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'

export interface VariantOptionValue {
  id: number
  variant_option_type_id: number
  key: string
  value: string
  position: number
}

export interface VariantOptionType {
  id: number
  name: string
  slug: string
  position: number
  is_active: boolean
  values: VariantOptionValue[]
}

export const useVariantOptionsStore = defineStore('variantOptions', () => {
  const items = ref<VariantOptionType[]>([])
  const loading = ref(false)
  const saving = ref(false)

  async function fetchAll() {
    loading.value = true
    try {
      const { data } = await http.get<VariantOptionType[]>('/variant-options')
      items.value = data
    } finally {
      loading.value = false
    }
  }

  async function createType(name: string): Promise<VariantOptionType> {
    saving.value = true
    try {
      const { data } = await http.post<VariantOptionType>('/variant-options', { name })
      items.value.push(data)
      return data
    } finally {
      saving.value = false
    }
  }

  async function updateType(id: number, payload: Partial<VariantOptionType>) {
    const { data } = await http.put<VariantOptionType>(`/variant-options/${id}`, payload)
    const idx = items.value.findIndex(t => t.id === id)
    if (idx !== -1) items.value[idx] = data
  }

  async function deleteType(id: number) {
    await http.delete(`/variant-options/${id}`)
    items.value = items.value.filter(t => t.id !== id)
  }

  async function addValue(typeId: number, key: string, value: string): Promise<VariantOptionValue> {
    saving.value = true
    try {
      const { data } = await http.post<VariantOptionValue>(`/variant-options/${typeId}/values`, { key, value })
      const type = items.value.find(t => t.id === typeId)
      if (type) type.values.push(data)
      return data
    } finally {
      saving.value = false
    }
  }

  async function updateValue(typeId: number, valueId: number, payload: { key?: string; value?: string }) {
    const { data } = await http.put<VariantOptionValue>(`/variant-options/${typeId}/values/${valueId}`, payload)
    const type = items.value.find(t => t.id === typeId)
    if (type) {
      const idx = type.values.findIndex(v => v.id === valueId)
      if (idx !== -1) type.values[idx] = data
    }
  }

  async function deleteValue(typeId: number, valueId: number) {
    await http.delete(`/variant-options/${typeId}/values/${valueId}`)
    const type = items.value.find(t => t.id === typeId)
    if (type) type.values = type.values.filter(v => v.id !== valueId)
  }

  return { items, loading, saving, fetchAll, createType, updateType, deleteType, addValue, updateValue, deleteValue }
})
