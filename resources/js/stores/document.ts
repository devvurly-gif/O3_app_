import { defineStore } from 'pinia'
import http from '@/services/http'
import { usePaginatedApi } from '@/composables/usePaginatedApi'
import type { DocumentHeader, DocumentLigne, DocumentFooter } from '@/types'

export const useDocumentStore = defineStore('document', () => {
  const { items, meta, loading, error, params, fetchPage, goToPage } = usePaginatedApi<DocumentHeader>('/documents')

  async function fetchOne(id: number): Promise<DocumentHeader> {
    const { data } = await http.get<DocumentHeader>(`/documents/${id}`)
    return data
  }

  async function create(payload: Partial<DocumentHeader>): Promise<DocumentHeader> {
    const { data } = await http.post<DocumentHeader>('/documents', payload)
    items.value.unshift(data)
    return data
  }

  async function update(id: number, payload: Partial<DocumentHeader>): Promise<DocumentHeader> {
    const { data } = await http.put<DocumentHeader>(`/documents/${id}`, payload)
    const idx = items.value.findIndex((d) => d.id === id)
    if (idx !== -1) items.value[idx] = data
    return data
  }

  async function remove(id: number): Promise<void> {
    await http.delete(`/documents/${id}`)
    items.value = items.value.filter((d) => d.id !== id)
  }

  // ── Line items ────────────────────────────────────────────────────────
  async function addLigne(documentId: number, payload: Partial<DocumentLigne>): Promise<DocumentLigne> {
    const { data } = await http.post<DocumentLigne>(`/documents/${documentId}/lines`, payload)
    return data
  }

  async function updateLigne(
    documentId: number,
    ligneId: number,
    payload: Partial<DocumentLigne>,
  ): Promise<DocumentLigne> {
    const { data } = await http.patch<DocumentLigne>(`/documents/${documentId}/lines/${ligneId}`, payload)
    return data
  }

  async function removeLigne(documentId: number, ligneId: number): Promise<void> {
    await http.delete(`/documents/${documentId}/lines/${ligneId}`)
  }

  // ── Footer ────────────────────────────────────────────────────────────
  async function upsertFooter(documentId: number, payload: Partial<DocumentFooter>): Promise<DocumentFooter> {
    const { data } = await http.put<DocumentFooter>(`/documents/${documentId}/footer`, payload)
    return data
  }

  return {
    items,
    meta,
    loading,
    error,
    params,
    fetchPage,
    goToPage,
    fetchOne,
    create,
    update,
    remove,
    addLigne,
    updateLigne,
    removeLigne,
    upsertFooter,
  }
})
