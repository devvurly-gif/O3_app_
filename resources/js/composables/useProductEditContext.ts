import { inject, provide } from 'vue'
import type { InjectionKey } from 'vue'

/**
 * Le formulaire de la fiche produit, partage avec les onglets qui l'editent.
 *
 * Pourquoi une injection plutot qu'une prop : les onglets Infos et Tarifs
 * ecrivent dans ce formulaire par `v-model`. Passe en prop, chaque champ
 * serait une mutation de prop — ce que `vue/no-mutating-props` refuse, a
 * juste titre. Un `defineModel` par champ ferait treize modeles pour le seul
 * onglet Infos.
 *
 * L'injection garde le formulaire la ou il vit — dans la page, qui l'envoie
 * au serveur — tout en laissant les gabarits ecrire `form.p_title` comme
 * avant. Aucune liaison n'a eu a etre reecrite.
 */
export interface NewTierForm {
  price_list_id: number | null
  min_qty: number
  price_ht: number
}

export interface ProductEditContext {
  /** Le formulaire de la fiche. Forme libre : c'est `emptyForm()` de l'ecran. */
  form: Record<string, any>
  /** Le formulaire d'ajout d'un palier tarifaire, dans l'onglet Tarifs. */
  newTier: NewTierForm
}

export const PRODUCT_EDIT = Symbol('product-edit') as InjectionKey<ProductEditContext>

export function provideProductEdit(context: ProductEditContext): void {
  provide(PRODUCT_EDIT, context)
}

export function useProductEdit(): ProductEditContext {
  const context = inject(PRODUCT_EDIT)
  if (!context) {
    throw new Error("useProductEdit() appele hors de l'ecran Produits : provideProductEdit() manque.")
  }
  return context
}
