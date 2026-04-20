import { defineStore } from 'pinia'
import http from '@/services/http'
import { usePaginatedApi } from '@/composables/usePaginatedApi'
import type { Product, ProductImage } from '@/types'

export const useProductStore = defineStore('product', () => {
  const { items, meta, loading, error, params, fetchPage, goToPage } = usePaginatedApi<Product>('/products')

  async function create(payload: Partial<Product>): Promise<Product> {
    const { data } = await http.post<Product>('/products', payload)
    items.value.unshift(data)
    return data
  }

  async function update(id: number, payload: Partial<Product>): Promise<Product> {
    const { data } = await http.put<Product>(`/products/${id}`, payload)
    const idx = items.value.findIndex((p) => p.id === id)
    if (idx !== -1) items.value[idx] = data
    return data
  }

  async function remove(id: number): Promise<void> {
    await http.delete(`/products/${id}`)
    items.value = items.value.filter((p) => p.id !== id)
  }

  // ── Image actions ─────────────────────────────────────────────────────
  async function uploadImage(productId: number, formData: FormData): Promise<ProductImage> {
    const { data } = await http.post<ProductImage>(`/products/${productId}/images`, formData)
    const idx = items.value.findIndex((p) => p.id === productId)
    if (idx !== -1) {
      if (!items.value[idx].images) items.value[idx].images = []
      if (data.isPrimary) {
        items.value[idx].images!.forEach((img) => {
          img.isPrimary = false
        })
      }
      items.value[idx].images!.push(data)
      if (data.isPrimary) items.value[idx].primary_image = data
    }
    return data
  }

  async function setPrimaryImage(productId: number, imageId: number): Promise<ProductImage> {
    const { data } = await http.patch<ProductImage>(`/products/${productId}/images/${imageId}/set-primary`)
    const idx = items.value.findIndex((p) => p.id === productId)
    if (idx !== -1 && items.value[idx].images) {
      items.value[idx].images!.forEach((img) => {
        img.isPrimary = img.id === imageId
      })
      items.value[idx].primary_image = data
    }
    return data
  }

  async function deleteImage(productId: number, imageId: number): Promise<void> {
    await http.delete(`/products/${productId}/images/${imageId}`)
    const idx = items.value.findIndex((p) => p.id === productId)
    if (idx !== -1 && items.value[idx].images) {
      items.value[idx].images = items.value[idx].images!.filter((img) => img.id !== imageId)
      if (items.value[idx].primary_image?.id === imageId) {
        items.value[idx].primary_image = items.value[idx].images!.find((img) => img.isPrimary) ?? null
      }
    }
  }

  return {
    items,
    meta,
    loading,
    error,
    params,
    fetchPage,
    goToPage,
    create,
    update,
    remove,
    uploadImage,
    setPrimaryImage,
    deleteImage,
  }
})
