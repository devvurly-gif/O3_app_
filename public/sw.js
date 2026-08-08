// Service worker O3 — reception des notifications push.
//
// Volontairement minimal : pas de cache offline ici. Ajouter une strategie de
// cache dans ce fichier ferait servir aux utilisateurs un build fige de la SPA
// apres chaque deploiement, un bug beaucoup plus couteux que l'absence de mode
// hors ligne. Ce worker ne fait qu'une chose : afficher les notifications.

self.addEventListener('install', () => {
    // Prend la main immediatement au lieu d'attendre la fermeture des onglets :
    // sans cela, activer les notifications ne marche qu'au prochain redemarrage
    // du navigateur, ce que personne ne devine.
    self.skipWaiting()
})

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim())
})

self.addEventListener('push', (event) => {
    if (!event.data) return

    let payload = {}
    try {
        payload = event.data.json()
    } catch (e) {
        payload = { title: 'O3 App', body: event.data.text() }
    }

    const title = payload.title || 'O3 App'
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/favicon.ico',
        badge: payload.badge || '/favicon.ico',
        tag: payload.tag || undefined,
        requireInteraction: payload.requireInteraction || false,
        // Transporte l'URL jusqu'au handler de clic ci-dessous.
        data: payload.data || {},
        vibrate: [100, 50, 100],
    }

    event.waitUntil(self.registration.showNotification(title, options))
})

self.addEventListener('notificationclick', (event) => {
    event.notification.close()

    const url = (event.notification.data && event.notification.data.url) || '/'

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            // Si l'app est deja ouverte quelque part, on y navigue plutot que
            // d'empiler un enieme onglet a chaque notification.
            for (const client of clients) {
                if ('focus' in client) {
                    client.navigate(url)
                    return client.focus()
                }
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(url)
            }
        })
    )
})
