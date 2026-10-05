/** Fichiers que l'on peut déposer dans la conversation avec l'orchestrateur (mêmes limites que le serveur). */
export const MAX_FILES = 3
export const MAX_FILE_BYTES = 10 * 1024 * 1024
export const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf']

type FileLike = Pick<File, 'name' | 'type' | 'size'>

/**
 * Ajoute les fichiers déposés à ceux déjà en attente en écartant ce que le serveur refuserait, et dit pourquoi.
 * Le serveur revérifie tout : ceci évite seulement un envoi inutile.
 */
export function addFiles<T extends FileLike>(current: T[], incoming: T[]): { files: T[]; error: string } {
  const files = [...current]
  const problems: string[] = []

  for (const f of incoming) {
    if (!ACCEPTED_TYPES.includes(f.type)) {
      problems.push(`« ${f.name} » : seules les photos (JPEG, PNG, WebP, GIF) et les PDF sont acceptés`)
    } else if (f.size > MAX_FILE_BYTES) {
      problems.push(`« ${f.name} » dépasse 10 Mo`)
    } else if (files.length >= MAX_FILES) {
      problems.push(`« ${f.name} » : ${MAX_FILES} fichiers au maximum à la fois`)
    } else {
      files.push(f)
    }
  }

  return { files, error: problems.join(' ; ') }
}
