import { ref } from 'vue'
import http from '@/services/http'

/**
 * Composable for PDF download & preview.
 * Replaces identical downloadPdf() / previewPdf() in DocumentVenteShow & DocumentAchatShow.
 */
export function usePdf() {
  const pdfLoading = ref(false)

  async function downloadPdf(documentId: number, filename: string) {
    pdfLoading.value = true
    try {
      const res = await http.get(`/documents/${documentId}/pdf/download`, { responseType: 'blob' })
      const url = window.URL.createObjectURL(new Blob([res.data]))
      const a = Object.assign(window.document.createElement('a'), {
        href: url,
        download: filename,
      })
      window.document.body.appendChild(a)
      a.click()
      a.remove()
      window.URL.revokeObjectURL(url)
    } catch {
      /* silently fail */
    }
    pdfLoading.value = false
  }

  async function previewPdf(documentId: number) {
    pdfLoading.value = true
    try {
      const res = await http.get(`/documents/${documentId}/pdf/stream`, { responseType: 'blob' })
      const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
      window.open(url, '_blank')
    } catch {
      /* silently fail */
    }
    pdfLoading.value = false
  }

  return { pdfLoading, downloadPdf, previewPdf }
}
