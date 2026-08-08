import { ref, onMounted } from 'vue'
import http from '@/services/http'
import { useToastStore } from '@/stores/toastStore'

/**
 * Abonnement du navigateur aux notifications push.
 *
 * Complete useNotifications() plutot que de le remplacer : celui-la ecoute
 * Reverb et n'existe que tant qu'un onglet est ouvert, celui-ci passe par le
 * service de push du navigateur et arrive telephone verrouille, application
 * fermee.
 */

/**
 * Le format VAPID est du base64url ; l'API navigateur veut des octets bruts.
 *
 * Renvoie l'ArrayBuffer plutot que la vue Uint8Array : depuis TypeScript 5.7
 * Uint8Array est generique sur son buffer et la vue par defaut n'est plus
 * assignable a BufferSource, que reclame applicationServerKey.
 */
function urlBase64ToArrayBuffer(base64String: string): ArrayBuffer {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
  const raw = window.atob(base64)
  const buffer = new ArrayBuffer(raw.length)
  const output = new Uint8Array(buffer)
  for (let i = 0; i < raw.length; ++i) output[i] = raw.charCodeAt(i)
  return buffer
}

export function usePushNotifications() {
  const supported = ref(false)
  const enabled = ref(false)
  const busy = ref(false)
  const blocked = ref(false)
  const toast = useToastStore()

  /**
   * Safari iOS n'expose PushManager que dans une PWA installee sur l'ecran
   * d'accueil. Onglet Safari classique : les API existent parfois mais
   * l'abonnement echoue. On teste donc la capacite reelle, pas l'agent.
   */
  function detectSupport(): boolean {
    return (
      'serviceWorker' in navigator &&
      'PushManager' in window &&
      'Notification' in window &&
      // Le push exige un contexte securise. localhost compte comme tel, ce qui
      // permet de tester en local sans certificat.
      window.isSecureContext
    )
  }

  async function registration(): Promise<ServiceWorkerRegistration> {
    return navigator.serviceWorker.register('/sw.js', { scope: '/' })
  }

  async function refreshState(): Promise<void> {
    supported.value = detectSupport()
    if (!supported.value) return

    blocked.value = Notification.permission === 'denied'

    try {
      const reg = await navigator.serviceWorker.getRegistration('/')
      const sub = await reg?.pushManager.getSubscription()
      enabled.value = !!sub
    } catch {
      enabled.value = false
    }
  }

  async function enable(): Promise<void> {
    if (busy.value) return
    busy.value = true

    try {
      if (!detectSupport()) {
        toast.error("Ce navigateur ne gere pas les notifications push.")
        return
      }

      const permission = await Notification.requestPermission()

      if (permission !== 'granted') {
        blocked.value = permission === 'denied'
        toast.warning(
          blocked.value
            ? 'Notifications bloquees. Autorisez-les dans les reglages du navigateur.'
            : 'Notifications non autorisees.',
        )
        return
      }

      const { data } = await http.get<{ key: string; enabled: boolean }>(
        '/push-subscriptions/vapid-key',
      )

      if (!data.enabled || !data.key) {
        toast.error('Le serveur n\'est pas configure pour les notifications push.')
        return
      }

      const reg = await registration()
      // Attend l'activation du worker : s'abonner sur un registration encore
      // en "installing" leve une erreur difficile a diagnostiquer.
      await navigator.serviceWorker.ready

      const subscription = await reg.pushManager.subscribe({
        // Chrome refuse tout abonnement non chiffre depuis longtemps ; ce flag
        // doit rester true.
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToArrayBuffer(data.key),
      })

      const json = subscription.toJSON() as {
        endpoint: string
        keys: { p256dh: string; auth: string }
      }

      await http.post('/push-subscriptions', {
        endpoint: json.endpoint,
        keys: json.keys,
        content_encoding: (PushManager as unknown as { supportedContentEncodings?: string[] })
          .supportedContentEncodings?.[0] ?? 'aesgcm',
      })

      enabled.value = true
      toast.success('Notifications activees sur cet appareil.')
    } catch {
      toast.error("Impossible d'activer les notifications sur cet appareil.")
    } finally {
      busy.value = false
    }
  }

  async function disable(): Promise<void> {
    if (busy.value) return
    busy.value = true

    try {
      const reg = await navigator.serviceWorker.getRegistration('/')
      const sub = await reg?.pushManager.getSubscription()

      if (sub) {
        // Le serveur d'abord : si le desabonnement navigateur reussit mais que
        // l'appel echoue, la ligne resterait en base et le serveur pousserait
        // vers un endpoint mort.
        await http.delete('/push-subscriptions', { data: { endpoint: sub.endpoint } })
        await sub.unsubscribe()
      }

      enabled.value = false
      toast.info('Notifications desactivees sur cet appareil.')
    } catch {
      toast.error('Impossible de desactiver les notifications.')
    } finally {
      busy.value = false
    }
  }

  async function toggle(): Promise<void> {
    enabled.value ? await disable() : await enable()
  }

  onMounted(refreshState)

  return { supported, enabled, blocked, busy, enable, disable, toggle, refreshState }
}
