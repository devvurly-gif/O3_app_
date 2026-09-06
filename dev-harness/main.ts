import { createApp } from 'vue'
import { createPinia } from 'pinia'
import i18n from '@/i18n/index'
import { useAuthStore } from '@/stores/authStore'
import Harness from './Harness.vue'
import './harness.css'

/*
 * Un onglet qui n'est pas peint ne recoit pas de `requestAnimationFrame`, et
 * les transitions de Vue attendent cette frame pour se resoudre : une modale
 * fermee reste alors montee, a opacite nulle, et l'on croit a un bouton qui
 * ne repond pas. Sur un banc pilote par un navigateur souvent en arriere-plan,
 * rAF est donc rebranche ici — avec `transition-duration: 0s` cote CSS, les
 * etats se posent immediatement.
 *
 * Sur une microtache plutot que sur un timer : un onglet en arriere-plan voit
 * ses `setTimeout` brides a la seconde, ce qui rendrait chaque transition
 * interminable pour un script qui pilote la page.
 */
window.requestAnimationFrame = ((cb: FrameRequestCallback) => {
  Promise.resolve().then(() => cb(performance.now()))
  return 0
}) as typeof window.requestAnimationFrame
window.cancelAnimationFrame = (() => {}) as typeof window.cancelAnimationFrame

// Le banc parle francais : c'est la langue dans laquelle les ecrans sont
// relus, et celle des libelles en dur qu'ils contiennent encore.
localStorage.setItem('locale', 'fr')
i18n.global.locale.value = 'fr'

const app = createApp(Harness)
const pinia = createPinia()
app.use(pinia)
app.use(i18n)

// Un role qui debloque le bouton d'export, garde par useExcelExport.
const auth = useAuthStore(pinia)
;(auth as any).user = { id: 1, name: 'Banc', email: 'banc@example.test', role: 'admin' }

app.mount('#app')
