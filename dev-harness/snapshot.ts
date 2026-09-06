/**
 * Outillage de comparaison avant / apres, expose sur `window`.
 *
 * Un decoupage de gabarit ne doit rien changer au DOM rendu. Ces fonctions
 * capturent l'etat de chaque modale sous une forme normalisee, la rangent
 * dans `localStorage` — qui survit aux rechargements — puis comparent les
 * deux campagnes et ne rapportent que ce qui a bouge.
 *
 * Depuis la console du banc :
 *
 *   await $captureAll('__before')   // avant le decoupage
 *   await $captureAll('__after')    // apres
 *   $diff('__before', '__after')
 */

/** Ce qu'on ignore : ce qui varie sans rien dire du rendu. */
function normalise(html: string): string {
  return html
    .replace(/<!--[\s\S]*?-->/g, '') // ancres de v-if posees par Vue
    .replace(/\s+/g, ' ')
    .replace(/> </g, '><')
    .replace(/ id="[^"]*"/g, ' id="#"')
    .replace(/ for="[^"]*"/g, ' for="#"')
    .replace(/ aria-labelledby="[^"]*"/g, ' aria-labelledby="#"')
    .trim()
}

/** Vue applique ses mises a jour en microtaches : on les laisse s'ecouler. */
async function settle(): Promise<void> {
  for (let i = 0; i < 24; i++) await Promise.resolve()
}

const openModal = () => document.querySelector('.fixed.inset-0')

function snap(name: string, slot: string): string {
  const modal = openModal()
  const html = modal ? normalise(modal.innerHTML) : '(aucune modale)'
  const store = JSON.parse(localStorage.getItem(slot) ?? '{}')
  store[name] = html
  localStorage.setItem(slot, JSON.stringify(store))
  return `${name} (${html.length})`
}

async function close(): Promise<string> {
  for (let i = 0; i < 8 && openModal(); i++) {
    const modal = openModal()!
    const button =
      [...modal.querySelectorAll('button')].find((b) => /^(Annuler|Fermer)$/.test(b.textContent!.trim())) ??
      modal.querySelector('button')
    button?.click()
    await settle()
  }
  return openModal() ? 'BLOQUEE' : 'fermee'
}

async function rowAction(title: string, index = 0): Promise<string> {
  const button = [...document.querySelectorAll<HTMLButtonElement>(`button[title="${title}"]`)][index]
  if (!button) return `introuvable ${title}[${index}]`
  button.click()
  await settle()
  return `ouvert ${title}[${index}]`
}

async function clickText(text: string): Promise<string> {
  const button = [...document.querySelectorAll('button')].find((b) => b.textContent!.includes(text))
  if (!button) return `introuvable ${text}`
  button.click()
  await settle()
  return `clic ${text}`
}

async function tab(label: string): Promise<string> {
  const modal = openModal()
  if (!modal) return 'pas de modale'
  const button = [...modal.querySelectorAll('button')].find((b) => b.textContent!.trim().startsWith(label))
  if (!button) return `onglet absent ${label}`
  button.click()
  await settle()
  return `onglet ${label}`
}

const TABS = ['Info', 'Fiscal', 'Crédit', 'Documents', 'Paiements', 'Statistiques']

async function snapTabs(prefix: string, slot: string): Promise<string[]> {
  const out: string[] = []
  for (const label of TABS) {
    const r = await tab(label)
    out.push(r.startsWith('onglet absent') ? r : snap(`${prefix}:${label}`, slot))
  }
  return out
}

/** Tout `for=` doit designer un champ qui existe : la normalisation masque les ids. */
function labelsResolve(): string {
  const modal = openModal()
  if (!modal) return 'pas de modale'
  const orphans = [...modal.querySelectorAll('label[for]')]
    .map((l) => l.getAttribute('for')!)
    .filter((id) => !modal.querySelector(`#${CSS.escape(id)}`))
  return orphans.length ? `for orphelins: ${orphans.join(', ')}` : 'tous les for resolvent'
}

/** Parcourt les deux ecrans et capture chaque etat de modale. */
async function captureAll(slot: string): Promise<string[]> {
  localStorage.removeItem(slot)
  const log: string[] = []

  // ── Fournisseurs ────────────────────────────────────────────────────────
  log.push(await clickText('Fournisseurs'))
  log.push(await rowAction('Modifier', 0))
  log.push(...(await snapTabs('sup.edit', slot)), labelsResolve())
  log.push(await close())

  log.push(await rowAction('Voir', 0))
  log.push(...(await snapTabs('sup.show', slot)))
  log.push(await close())

  log.push(await rowAction('Enregistrer un paiement', 0))
  log.push(snap('sup.payment', slot), labelsResolve())
  log.push(await close())

  // Le troisieme fournisseur n'a aucun document : etat vide.
  log.push(await rowAction('Enregistrer un paiement', 2))
  log.push(snap('sup.payment.vide', slot))
  log.push(await close())

  log.push(await rowAction('Supprimer', 0))
  log.push(snap('sup.delete', slot))
  log.push(await close())

  log.push(await clickText('Ajouter un fournisseur'))
  log.push(snap('sup.create', slot))
  log.push(await close())

  // Deuxieme fournisseur : plafond de credit depasse.
  log.push(await rowAction('Modifier', 1))
  log.push(await tab('Crédit'))
  log.push(snap('sup.edit2.credit', slot))
  log.push(await close())

  return log
}

/** Deuxieme moitie : l'ecran Clients. Separee pour tenir dans un appel. */
async function captureCustomers(slot: string): Promise<string[]> {
  const log: string[] = []
  log.push(await clickText('Clients'))

  log.push(await rowAction('Modifier', 0))
  log.push(...(await snapTabs('cus.edit', slot)), labelsResolve())
  log.push(await close())

  log.push(await rowAction('Voir', 0))
  log.push(snap('cus.show', slot))
  log.push(await close())

  // Client 1 : des documents restent dus — reglement groupe.
  log.push(await rowAction('Enregistrer un paiement', 0))
  log.push(snap('cus.payment', slot), labelsResolve())
  log.push(await close())

  // Client 2 : tout est solde — repli sur le reglement a l'unite.
  log.push(await rowAction('Enregistrer un paiement', 1))
  log.push(snap('cus.payment.solde', slot), labelsResolve())
  log.push(await close())

  // Client 3 : aucun document.
  log.push(await rowAction('Enregistrer un paiement', 2))
  log.push(snap('cus.payment.vide', slot))
  log.push(await close())

  log.push(await rowAction('Supprimer', 0))
  log.push(snap('cus.delete', slot))
  log.push(await close())

  log.push(await clickText('Ajouter un client'))
  log.push(snap('cus.create', slot))
  log.push(await close())

  return log
}

/**
 * Capture la page elle-meme, hors modale : la liste, ses filtres, le menu des
 * colonnes. Produits porte beaucoup d'etat visible avant qu'une modale s'ouvre.
 */
function snapMain(name: string, slot: string): string {
  const main = document.querySelector('main')
  const html = main ? normalise(main.innerHTML) : '(pas de main)'
  const store = JSON.parse(localStorage.getItem(slot) ?? '{}')
  store[name] = html
  localStorage.setItem(slot, JSON.stringify(store))
  return `${name} (${html.length})`
}

const PRODUCT_TABS = ['Infos', 'Tarifs', 'Stock', 'Statistiques', 'Médias', 'Variantes']

async function snapProductTabs(prefix: string, slot: string): Promise<string[]> {
  const out: string[] = []
  for (const label of PRODUCT_TABS) {
    const r = await tab(label)
    out.push(r.startsWith('onglet absent') ? r : snap(`${prefix}:${label}`, slot))
  }
  return out
}

const clickTitle = async (title: string) => {
  document.querySelector<HTMLButtonElement>(`button[title="${title}"]`)?.click()
  await settle()
}

/** L'ecran Produits : vues, colonnes, et les six onglets de la fiche. */
async function captureProducts(slot: string): Promise<string[]> {
  const log: string[] = []
  log.push(await clickText('Produits'))
  await settle()

  log.push(snapMain('prod.grille', slot))
  await clickTitle('Vue liste')
  log.push(snapMain('prod.liste', slot))

  await clickTitle('Afficher / masquer des colonnes')
  log.push(snapMain('prod.colonnes', slot))
  await clickTitle('Afficher / masquer des colonnes')

  // Fiche riche (produit 1) : les six onglets
  log.push(await rowAction('Modifier', 0))
  log.push(...(await snapProductTabs('prod.edit', slot)), labelsResolve())
  log.push(await close())

  // Fiche depouillee (produit 3) : ni image, ni marque, inactif
  log.push(await rowAction('Modifier', 2))
  await tab('Médias')
  log.push(snap('prod.edit3.medias', slot))
  await tab('Statistiques')
  log.push(snap('prod.edit3.stats', slot))
  log.push(await close())

  log.push(await clickText('Ajouter un produit'))
  log.push(snap('prod.create', slot), labelsResolve())
  log.push(await close())

  log.push(await rowAction('Supprimer', 0))
  log.push(snap('prod.delete', slot))
  log.push(await close())

  return log
}

/** Ne rapporte que ce qui a bouge, avec le premier ecart en clair. */
function diff(before = '__before', after = '__after') {
  const a = JSON.parse(localStorage.getItem(before) ?? '{}')
  const b = JSON.parse(localStorage.getItem(after) ?? '{}')
  const names = [...new Set([...Object.keys(a), ...Object.keys(b)])].sort()
  const changed: unknown[] = []

  for (const name of names) {
    if (a[name] === b[name]) continue
    if (a[name] === undefined || b[name] === undefined) {
      changed.push({ name, etat: a[name] === undefined ? 'absent avant' : 'absent apres' })
      continue
    }
    let i = 0
    while (i < a[name].length && i < b[name].length && a[name][i] === b[name][i]) i++
    changed.push({
      name,
      avantLen: a[name].length,
      apresLen: b[name].length,
      position: i,
      avant: a[name].slice(Math.max(0, i - 60), i + 180),
      apres: b[name].slice(Math.max(0, i - 60), i + 180),
    })
  }

  return { compares: names.length, identiques: names.length - changed.length, changed }
}

declare global {
  interface Window {
    $captureAll: typeof captureAll
    $captureCustomers: typeof captureCustomers
    $captureProducts: typeof captureProducts
    $diff: typeof diff
    $snap: typeof snap
    $snapMain: typeof snapMain
    $close: typeof close
    $labelsResolve: typeof labelsResolve
  }
}

export function installSnapshotTools(): void {
  window.$captureAll = captureAll
  window.$captureCustomers = captureCustomers
  window.$captureProducts = captureProducts
  window.$diff = diff
  window.$snap = snap
  window.$snapMain = snapMain
  window.$close = close
  window.$labelsResolve = labelsResolve
}
