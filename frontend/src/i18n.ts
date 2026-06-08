import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import en from './locales/en.json'
import ar from './locales/ar.json'

// Read persisted language from Zustand storage (localStorage key: ui-store)
function getPersistedLang(): string {
  try {
    const raw = localStorage.getItem('pos-ui')
    if (raw) {
      const parsed = JSON.parse(raw) as { state?: { language?: string } }
      return parsed?.state?.language ?? 'en'
    }
  } catch {}
  return 'en'
}

const initialLang = getPersistedLang()

i18n.use(initReactI18next).init({
  resources: {
    en: { pos: en },
    ar: { pos: ar },
  },
  lng: initialLang,
  fallbackLng: 'en',
  defaultNS: 'pos',
  interpolation: { escapeValue: false },
})

// Apply dir/lang immediately so the first render is correct
document.documentElement.dir = initialLang === 'ar' ? 'rtl' : 'ltr'
document.documentElement.lang = initialLang

export default i18n
