import enTranslations from './translations/en.js'
import viTranslations from './translations/vi.js'

export const translations = {
  en: enTranslations,
  vi: viTranslations,
}

/**
 * @param {string} lang
 * @param {string} key dot path e.g. widget.fab
 * @param {string} fallback
 */
export function t(lang = 'vi', key, fallback = '') {
  const keys = key.split('.')
  let value = translations[lang] || translations.vi

  for (const k of keys) {
    if (value && typeof value === 'object' && k in value) {
      value = value[k]
    } else {
      return fallback || key
    }
  }

  return typeof value === 'string' ? value : fallback || key
}

/**
 * @param {string} lang
 * @returns {(key: string, fallback?: string) => string}
 */
export function createTranslator(lang = 'vi') {
  const code = lang === 'en' ? 'en' : 'vi'
  return (key, fallback = '') => t(code, key, fallback)
}

/**
 * Replace {name} placeholders in translation string.
 */
export function formatMessage(template, params = {}) {
  if (!template || typeof template !== 'string') return ''
  return template.replace(/\{(\w+)\}/g, (_, name) => {
    return params[name] != null ? String(params[name]) : ''
  })
}
