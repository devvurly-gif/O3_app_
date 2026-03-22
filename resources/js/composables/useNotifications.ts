import { ref, onMounted, onUnmounted } from 'vue'
import http from '@/services/http'
import { useToastStore } from '@/stores/toastStore'
import { useAuthStore } from '@/stores/authStore'
import type { AppNotification } from '@/types'

export function useNotifications() {
  const unreadCount = ref(0)
  const notifications = ref<AppNotification[]>([])
  const loading = ref(false)
  const toast = useToastStore()

  async function fetchUnread(): Promise<void> {
    try {
      const { data } = await http.get<{ count: number; data: AppNotification[] }>('/notifications/unread')
      unreadCount.value = data.count
      notifications.value = data.data
    } catch {
      /* ignore */
    }
  }

  async function markAsRead(id: string): Promise<void> {
    try {
      await http.patch(`/notifications/${id}/read`)
      notifications.value = notifications.value.filter((n) => n.id !== id)
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    } catch {
      /* ignore */
    }
  }

  async function markAllAsRead(): Promise<void> {
    try {
      await http.post('/notifications/mark-all-read')
      notifications.value = []
      unreadCount.value = 0
    } catch {
      /* ignore */
    }
  }

  function showToast(notif: AppNotification) {
    const labels: Record<string, { message: string; type: 'info' | 'warning' | 'success' }> = {
      low_stock:            { message: 'Stock bas — produit(s) en alerte',       type: 'warning' },
      order_confirmation:   { message: 'Nouveau document confirme',              type: 'success' },
      invoice_due_reminder: { message: 'Facture(s) en retard de paiement',       type: 'warning' },
      payment_received:     { message: 'Paiement recu',                          type: 'success' },
      stock_movement:       { message: 'Alerte stock bas apres mouvement',       type: 'warning' },
    }

    const config = labels[notif.data?.type] ?? { message: 'Nouvelle notification', type: 'info' }
    toast.show(config.message, config.type)
  }

  function startListening() {
    const user = useAuthStore().user
    if (!user || !window.Echo) return

    window.Echo.private(`App.Models.User.${user.id}`)
      .listen('.new-notification', (e: { notification: AppNotification }) => {
        notifications.value.unshift(e.notification)
        unreadCount.value++
        showToast(e.notification)
      })
  }

  function stopListening() {
    const user = useAuthStore().user
    if (user && window.Echo) {
      window.Echo.leave(`App.Models.User.${user.id}`)
    }
  }

  onMounted(() => {
    fetchUnread()
    startListening()
  })

  onUnmounted(() => {
    stopListening()
  })

  return { unreadCount, notifications, loading, fetchUnread, markAsRead, markAllAsRead }
}
