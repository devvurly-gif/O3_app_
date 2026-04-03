import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'

export interface Tenant {
  id: string
  name: string
  email: string
  plan: 'starter' | 'business' | 'enterprise'
  is_active: boolean
  pos_enabled: boolean
  paiement_bl_enabled: boolean
  ecom_enabled: boolean
  ecom_api_key?: string
  trial_ends_at: string | null
  created_at: string
  updated_at: string
  tenancy_db_name: string
  domains?: { id: number; domain: string }[]
}

export interface CreateTenantPayload {
  id: string
  name: string
  email: string
  domain: string
  plan: string
  admin_password: string
}

export interface ScrapedProduct {
  name: string
  price: number
  old_price: number | null
  brand: string | null
  image: string | null
  description: string
  url: string | null
}

export const useTenantStore = defineStore('tenant', () => {
  const items = ref<Tenant[]>([])
  const loading = ref(false)
  const error = ref<unknown>(null)

  async function fetchAll(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.get('/central/tenants')
      items.value = data.data
    } catch (e) {
      error.value = e
    } finally {
      loading.value = false
    }
  }

  async function fetchOne(id: string): Promise<Tenant> {
    const { data } = await http.get(`/central/tenants/${id}`)
    return data.data
  }

  async function create(payload: CreateTenantPayload): Promise<Tenant> {
    const { data } = await http.post('/central/tenants', payload)
    await fetchAll()
    return data.data
  }

  async function update(id: string, payload: Partial<Tenant>): Promise<Tenant> {
    const { data } = await http.put(`/central/tenants/${id}`, payload)
    await fetchAll()
    return data.data
  }

  async function remove(id: string): Promise<void> {
    await http.delete(`/central/tenants/${id}`)
    items.value = items.value.filter(t => t.id !== id)
  }

  async function resetPassword(id: string, password: string): Promise<void> {
    await http.post(`/central/tenants/${id}/reset-password`, { password })
  }

  async function resetDatabase(id: string): Promise<string> {
    const { data } = await http.post(`/central/tenants/${id}/reset-database`, { confirm: 'RESET' })
    return data.message
  }

  async function purgeFiles(id: string, types: ('images' | 'pdfs')[]): Promise<{ message: string; deleted: { images: number; pdfs: number } }> {
    const { data } = await http.post(`/central/tenants/${id}/purge-files`, { types })
    return data
  }

  async function scrapeProducts(url: string): Promise<{ products: ScrapedProduct[]; source: string; count: number }> {
    const { data } = await http.post('/central/tenants/scrape-products', { url })
    return data
  }

  async function importProducts(id: string, products: ScrapedProduct[], category: string): Promise<{ message: string; created: number; skipped: number; errors: string[] }> {
    const { data } = await http.post(`/central/tenants/${id}/import-products`, { products, category })
    return data
  }

  return { items, loading, error, fetchAll, fetchOne, create, update, remove, resetPassword, resetDatabase, purgeFiles, scrapeProducts, importProducts }
})
