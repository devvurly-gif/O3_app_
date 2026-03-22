import { ref } from 'vue'
import http from '@/services/http'

export function useExcelExport() {
  const exporting = ref(false)

  async function exportExcel(endpoint: string, filters: Record<string, unknown> = {}): Promise<void> {
    exporting.value = true
    try {
      const params = new URLSearchParams(
        Object.entries(filters)
          .filter(([, v]) => v !== null && v !== undefined && v !== '')
          .map(([k, v]) => [k, String(v)]),
      ).toString()

      const url = params ? `${endpoint}?${params}` : endpoint
      const res = await http.get(url, { responseType: 'blob' })

      const disposition = (res.headers['content-disposition'] as string) || ''
      const match = disposition.match(/filename="?([^"]+)"?/)
      const filename = match ? match[1] : 'export.xlsx'

      const blob = new Blob([res.data], {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      })
      const link = Object.assign(document.createElement('a'), {
        href: window.URL.createObjectURL(blob),
        download: filename,
      })
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(link.href)
    } catch {
      /* silently fail */
    }
    exporting.value = false
  }

  return { exporting, exportExcel }
}
