import { describe, expect, it } from 'vitest'
import { changedFields, isAnthropicKeyFormat } from './settingsForm'

describe('changedFields', () => {
  it("n'envoie rien quand rien n'a changé", () => {
    expect(
      changedFields({ ai_enabled: 'false', sms_from: 'JADEMA' }, { ai_enabled: 'false', sms_from: 'JADEMA' }),
    ).toEqual({})
  })

  it('ne renvoie que les champs modifiés', () => {
    expect(
      changedFields(
        { ai_enabled: 'false', sms_from: 'JADEMA', ai_model: '' },
        { ai_enabled: 'false', sms_from: 'MAGASIN', ai_model: '' },
      ),
    ).toEqual({ sms_from: 'MAGASIN' })
  })

  it('laisse intact un champ non touché, même si la valeur du serveur a changé depuis le chargement (page périmée)', () => {
    // Le formulaire a été chargé avec ai_enabled = 'false' ; entre-temps le serveur l'a mis à autre chose.
    // L'utilisateur n'a touché qu'à la clé : l'interrupteur ne doit pas être renvoyé.
    const baselineAuChargement = { ai_enabled: 'false', ai_model: '' }
    const formulaire = { ai_enabled: 'false', ai_model: '', anthropic_api_key: 'sk-ant-x' }
    expect(Object.keys(changedFields(baselineAuChargement, formulaire))).toEqual(['anthropic_api_key'])
  })

  it("envoie un champ que l'utilisateur a volontairement changé", () => {
    expect(changedFields({ ai_enabled: 'false' }, { ai_enabled: 'true' })).toEqual({ ai_enabled: 'true' })
  })

  it('compare des valeurs de types différents comme du texte, et null comme vide', () => {
    expect(changedFields({ port: '587', note: null, on: true }, { port: 587, note: '', on: 'true' })).toEqual({})
    expect(changedFields({ port: '587' }, { port: 25 })).toEqual({ port: '25' })
  })

  it('traite un champ absent de la base comme vide', () => {
    expect(changedFields(undefined, { sms_from: '' })).toEqual({})
    expect(changedFields(undefined, { sms_from: 'JADEMA' })).toEqual({ sms_from: 'JADEMA' })
    expect(changedFields({}, { sms_from: 'JADEMA' })).toEqual({ sms_from: 'JADEMA' })
  })

  it('permet de vider volontairement un champ', () => {
    expect(changedFields({ sms_from: 'JADEMA' }, { sms_from: '' })).toEqual({ sms_from: '' })
  })
})

describe('isAnthropicKeyFormat', () => {
  const valid = 'sk-ant-api03-AbCdEfGhIjKlMnOpQrStUvWxYz0123456789_-AbCdEfGhIjKlMnOpQrStUvWxYz0123456789AA'

  it('accepte une clé de la bonne forme, avec des espaces autour', () => {
    expect(isAnthropicKeyFormat(valid)).toBe(true)
    expect(isAnthropicKeyFormat(`  ${valid}\n`)).toBe(true)
  })

  it.each([
    ["clé d'un autre service", 'AIzaSyA-1234567890abcdefghijklmnopqrstu'],
    ['préfixe seul', 'sk-ant-'],
    ['tronquée', 'sk-ant-api03-AbCdEf'],
    ["espace à l'intérieur", 'sk-ant-api03-AbCdEfGhIjKlMnOpQrSt UvWxYz0123456789_-AbCd'],
    ['guillemets', '"sk-ant-api03-AbCdEfGhIjKlMnOpQrStUvWxYz0123456789_-AbCd"'],
    ['points de suspension', 'sk-ant-api03-AbCdEfGhIjKlMnOpQrStUvWxYz0123…'],
    ['vide', ''],
  ])('refuse : %s', (_label, key) => {
    expect(isAnthropicKeyFormat(key)).toBe(false)
  })
})
