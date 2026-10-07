import { describe, expect, it } from 'vitest'
import { addFiles, MAX_FILE_BYTES } from './orchestratorFiles'

const f = (name: string, type: string, size = 1000) => ({ name, type, size })

describe('addFiles', () => {
  it('accepte les photos et les PDF', () => {
    const r = addFiles([], [f('a.jpg', 'image/jpeg'), f('b.pdf', 'application/pdf')])
    expect(r.files.map((x) => x.name)).toEqual(['a.jpg', 'b.pdf'])
    expect(r.error).toBe('')
  })

  it('accepte un relevé Excel ou CSV, quel que soit le type annoncé par le navigateur', () => {
    const r = addFiles([], [f('releve.csv', 'application/vnd.ms-excel'), f('releve.xlsx', ''), f('RELEVE.XLS', 'application/octet-stream')])
    expect(r.files.map((x) => x.name)).toEqual(['releve.csv', 'releve.xlsx', 'RELEVE.XLS'])
    expect(r.error).toBe('')
  })

  it('refuse un autre type et le dit', () => {
    const r = addFiles([], [f('notes.txt', 'text/plain'), f('ok.png', 'image/png')])
    expect(r.files.map((x) => x.name)).toEqual(['ok.png'])
    expect(r.error).toContain('notes.txt')
  })

  it('refuse un fichier de plus de 10 Mo', () => {
    const r = addFiles(
      [],
      [f('gros.pdf', 'application/pdf', MAX_FILE_BYTES + 1), f('juste.pdf', 'application/pdf', MAX_FILE_BYTES)],
    )
    expect(r.files.map((x) => x.name)).toEqual(['juste.pdf'])
    expect(r.error).toContain('gros.pdf')
  })

  it('limite à 3 fichiers, y compris ceux déjà en attente', () => {
    const r = addFiles(
      [f('1.pdf', 'application/pdf'), f('2.pdf', 'application/pdf')],
      [f('3.pdf', 'application/pdf'), f('4.pdf', 'application/pdf')],
    )
    expect(r.files.map((x) => x.name)).toEqual(['1.pdf', '2.pdf', '3.pdf'])
    expect(r.error).toContain('4.pdf')
  })

  it("ne modifie pas la liste d'origine", () => {
    const current = [f('1.pdf', 'application/pdf')]
    addFiles(current, [f('2.pdf', 'application/pdf')])
    expect(current).toHaveLength(1)
  })
})
