import { useI18n } from 'vue-i18n'

/**
 * Libelles et montants des formules, partages par l'espace client et le
 * back-office central.
 *
 * Les capacites arrivent du serveur sous forme de slugs (`pos`, `ocr_import`,
 * `multi_warehouse`…) parce que le serveur n'a pas a connaitre la langue de
 * l'utilisateur. Leur traduction vit donc ici, en un seul endroit : trois
 * pages affichent la meme liste, et trois traductions divergentes de « Caisse »
 * se remarqueraient immediatement.
 */
export function usePlanLabels() {
  const { t } = useI18n()

  function featureLabel(feature: string): string {
    const key = `subscription.features.${feature}`
    const label = t(key)

    // vue-i18n renvoie la cle quand la traduction manque : plutot que
    // d'afficher « subscription.features.xyz », on retombe sur le slug brut.
    return label === key ? feature : label
  }

  /** Centimes de dirham → montant lisible, sans decimales inutiles. */
  function formatMad(cents: number, withCurrency = true): string {
    const amount = (cents / 100).toLocaleString('fr-MA', { maximumFractionDigits: 2 })

    return withCurrency ? `${amount} MAD` : amount
  }

  return { featureLabel, formatMad }
}
