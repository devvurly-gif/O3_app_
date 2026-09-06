import { beforeEach, describe, expect, it, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// `vi.mock` est hisse en tete de fichier : la double est donc creee par
// `vi.hoisted`, sans quoi la constante n'existe pas encore quand le mock joue.
const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))
vi.mock('@/services/http', () => ({ default: http }))

import { useProductVariants } from './useProductVariants'

/**
 * `POST /products/{id}/variants/sync` supprime cote serveur toute declinaison
 * absente de la requete (ProductVariantController::sync, `whereNotIn(...)
 * ->delete()`). Envoyer une liste qu'on n'a pas chargee efface donc les
 * declinaisons existantes — c'est exactement ce qui arrivait tant que
 * `load()` n'etait appele nulle part.
 *
 * Ces tests gardent la porte fermee.
 */

const actif = () => true

describe('useProductVariants', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    http.get.mockReset()
    http.post.mockReset()
  })

  it('charge les declinaisons existantes', async () => {
    http.get.mockResolvedValue({ data: [{ id: 1, label: 'Blanc' }] })
    const v = useProductVariants(actif)
    await v.load(101)
    expect(v.variants.value).toEqual([{ id: 1, label: 'Blanc' }])
    expect(v.loaded.value).toBe(true)
  })

  it('ne synchronise rien tant que personne n a touche a la liste', async () => {
    http.get.mockResolvedValue({ data: [{ id: 1, label: 'Blanc' }] })
    const v = useProductVariants(actif)
    await v.load(101)
    await v.save(101)
    expect(http.post).not.toHaveBeenCalled()
  })

  it('synchronise apres modification, une fois l etat serveur lu', async () => {
    http.get.mockResolvedValue({ data: [{ id: 1, label: 'Blanc' }] })
    http.post.mockResolvedValue({ data: {} })
    const v = useProductVariants(actif)
    await v.load(101)
    v.addRow()
    await v.save(101)
    expect(http.post).toHaveBeenCalledOnce()
    // La declinaison deja enregistree part avec son id : le serveur la garde.
    const envoye = http.post.mock.calls[0][1].variants
    expect(envoye[0]).toMatchObject({ id: 1, label: 'Blanc' })
    expect(envoye).toHaveLength(2)
    expect(v.dirty.value).toBe(false)
  })

  it('refuse de synchroniser quand le chargement a echoue', async () => {
    // Sans ce garde, la liste vide partirait au serveur et effacerait tout.
    http.get.mockRejectedValue(new Error('reseau'))
    const v = useProductVariants(actif)
    await v.load(101)
    expect(v.loaded.value).toBe(false)
    v.addRow()
    await v.save(101)
    expect(http.post).not.toHaveBeenCalled()
  })

  it('laisse creer les declinaisons d un produit neuf', async () => {
    // En creation il n y a rien a lire : l ensemble vide est le bon etat.
    http.post.mockResolvedValue({ data: {} })
    const v = useProductVariants(actif)
    v.reset()
    expect(v.loaded.value).toBe(true)
    v.addRow()
    await v.save(500)
    expect(http.post).toHaveBeenCalledOnce()
  })

  it('repart de zero a l ouverture d une autre fiche', async () => {
    http.get.mockResolvedValue({ data: [{ id: 1, label: 'Blanc' }] })
    const v = useProductVariants(actif)
    await v.load(101)
    v.addRow()
    v.reset({ id: 102 })
    expect(v.variants.value).toEqual([])
    expect(v.dirty.value).toBe(false)
    // Une fiche existante non encore lue : surtout pas synchronisable.
    expect(v.loaded.value).toBe(false)
  })

  it('ne fait rien quand le module variantes est eteint', async () => {
    const v = useProductVariants(() => false)
    await v.load(101)
    expect(http.get).not.toHaveBeenCalled()
    v.addRow()
    await v.save(101)
    expect(http.post).not.toHaveBeenCalled()
  })
})
