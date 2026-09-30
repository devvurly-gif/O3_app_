import { defineStore } from 'pinia'
import http from '@/services/http'
import { usePaginatedApi } from '@/composables/usePaginatedApi'
import type { ThirdPartner } from '@/types'

export const useThirdPartnerStore = defineStore('thirdPartner', () => {
  const { items, meta, loading, error, params, fetchPage, goToPage } = usePaginatedApi<ThirdPartner>('/third-partners')

  async function create(payload: Partial<ThirdPartner>): Promise<ThirdPartner> {
    const { data } = await http.post<ThirdPartner & { order_pin?: string }>('/third-partners', payload)
    // Le PIN de commande en clair n'est montre qu'une fois : il ne reste pas dans la liste.
    const { order_pin: _pin, ...row } = data
    items.value.unshift(row)
    return data
  }

  async function update(id: number, payload: Partial<ThirdPartner>): Promise<ThirdPartner> {
    const { data } = await http.put<ThirdPartner>(`/third-partners/${id}`, payload)
    const idx = items.value.findIndex((p) => p.id === id)
    if (idx !== -1) items.value[idx] = data
    return data
  }

  async function remove(id: number): Promise<void> {
    await http.delete(`/third-partners/${id}`)
    items.value = items.value.filter((p) => p.id !== id)
  }

  return { items, meta, loading, error, params, fetchPage, goToPage, create, update, remove }
})
