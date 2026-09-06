import { h } from 'vue'
import type { FunctionalComponent } from 'vue'

/**
 * Icones des onglets des fiches tiers (client / fournisseur).
 *
 * Les deux ecrans Clients et Fournisseurs portaient la meme cinquantaine de
 * lignes de `h('svg', ...)` chacun, au caractere pres. Un trace corrige d'un
 * cote ne l'etait jamais de l'autre : les voici en un seul endroit.
 *
 * Ce sont des composants fonctionnels, destines a `<component :is="tab.icon" />`.
 */

/** Attributs communs a tous les traces : contour 2px, 24x24, couleur heritee. */
const SVG_ATTRS = {
  class: 'w-4 h-4',
  fill: 'none',
  stroke: 'currentColor',
  'stroke-width': '2',
  viewBox: '0 0 24 24',
} as const

const PATH_ATTRS = {
  'stroke-linecap': 'round',
  'stroke-linejoin': 'round',
} as const

/**
 * Fabrique une icone a partir de son seul trace.
 *
 * Les objets d'attributs sont reconstruits a chaque rendu : `h()` normalise
 * ce qu'on lui passe (la `class`, notamment), et partager une constante entre
 * tous les rendus l'exposerait a cette normalisation.
 */
function tabIcon(d: string): FunctionalComponent {
  const icon: FunctionalComponent = () => h('svg', { ...SVG_ATTRS }, [h('path', { ...PATH_ATTRS, d })])
  return icon
}

export const IconInfo = tabIcon('M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z')

export const IconCredit = tabIcon('M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z')

export const IconInvoice = tabIcon(
  'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
)

export const IconPayment = tabIcon(
  'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
)

export const IconFiscal = tabIcon(
  'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
)

export const IconStats = tabIcon(
  'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
)
