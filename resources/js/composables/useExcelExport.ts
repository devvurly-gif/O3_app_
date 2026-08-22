import { computed, ref } from 'vue'
import http from '@/services/http'
import { useAuthStore } from '@/stores/authStore'

export function useExcelExport() {
  const exporting = ref(false)
  const auth = useAuthStore()

  /**
   * Les routes /api/export/* sont réservées à admin et manager : un export
   * sort la table entière dans un fichier qui quitte l'application.
   *
   * Le serveur tranche, mais les pages concernées — produits, clients,
   * documents, mouvements — restent lisibles par tous les rôles. Sans ce
   * garde, un caissier verrait un bouton qui échoue en 403 : la règle doit
   * donc se lire aussi côté interface, à un seul endroit.
   */
  const canExport = computed(() => ['admin', 'manager'].includes(auth.userRole))

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

  return { exporting, exportExcel, canExport }
}
