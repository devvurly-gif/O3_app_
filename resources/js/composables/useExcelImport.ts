import { ref } from 'vue'
import http from '@/services/http'
import type { ImportResult, ImportErrors } from '@/types'

export function useExcelImport() {
  const importing = ref(false)
  const importResult = ref<ImportResult | null>(null)
  const importErrors = ref<ImportErrors | null>(null)

  async function importExcel(endpoint: string, file: File): Promise<void> {
    importing.value = true
    importResult.value = null
    importErrors.value = null

    const form = new FormData()
    form.append('file', file)

    try {
      const res = await http.post<ImportResult>(endpoint, form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      importResult.value = res.data
    } catch (err: unknown) {
      const error = err as { response?: { status?: number; data?: ImportErrors } }
      if (error.response?.status === 422) {
        importErrors.value = error.response.data ?? { message: 'Erreurs de validation.' }
      } else {
        importErrors.value = { message: "Erreur lors de l'import." }
      }
    }
    importing.value = false
  }

  function resetImport(): void {
    importResult.value = null
    importErrors.value = null
  }

  return { importing, importResult, importErrors, importExcel, resetImport }
}
