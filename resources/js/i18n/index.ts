import { createI18n } from 'vue-i18n'
import en from './en.js'
import fr from './fr.js'

const savedLocale = localStorage.getItem('locale') ?? 'en'

const i18n = createI18n({
  legacy: false,
  locale: savedLocale,
  fallbackLocale: 'en',
  messages: { en, fr },
})

export default i18n

export function setLocale(lang: string): void {
  i18n.global.locale.value = lang as 'en' | 'fr'
  localStorage.setItem('locale', lang)
  document.documentElement.lang = lang
}
