/**
 * Aides de l'écran Réglages.
 *
 * changedFields : un formulaire ne doit envoyer que les champs que l'utilisateur a
 * réellement modifiés. Renvoyer tout le formulaire fait qu'une page chargée avant un
 * changement fait ailleurs (ou par un autre administrateur) l'écrase en silence : c'est
 * ainsi qu'un interrupteur éteint a été rallumé par un simple enregistrement de la clé.
 *
 * isAnthropicKeyFormat : même contrôle que le serveur (SettingController::SECRET_FORMATS),
 * pour répondre tout de suite sans attendre l'aller-retour.
 */

/** Champs dont la valeur diffère de la base de départ (ce que le serveur avait au chargement). */
export function changedFields(
  baseline: Record<string, unknown> | undefined,
  values: Record<string, unknown>,
): Record<string, string> {
  const base = baseline ?? {}
  const changed: Record<string, string> = {}

  for (const [key, value] of Object.entries(values)) {
    const now = value == null ? '' : String(value)
    const before = base[key] == null ? '' : String(base[key])
    if (now !== before) changed[key] = now
  }

  return changed
}

const ANTHROPIC_KEY_PATTERN = /^sk-ant-[A-Za-z0-9_-]{30,}$/

/** Une clé Anthropic commence par « sk-ant- » et ne contient que lettres, chiffres, tirets et soulignés. */
export function isAnthropicKeyFormat(key: string): boolean {
  return ANTHROPIC_KEY_PATTERN.test(key.trim())
}

export const ANTHROPIC_KEY_HELP =
  "La clé Anthropic n'a pas la bonne forme : elle commence par « sk-ant- », ne contient ni espace ni guillemet et fait plusieurs dizaines de caractères. Copiez la clé complète depuis la console Anthropic (API keys)."
