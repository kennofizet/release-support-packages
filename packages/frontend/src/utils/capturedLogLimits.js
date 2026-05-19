import { redactLogEntry, redactObject, redactString } from './logRedaction'

/** Must match backend `captured_logs.*.message` max rule. */
export const MAX_CAPTURED_LOG_MESSAGE = 4000
export const MAX_CAPTURED_LOG_TYPE = 64
export const MAX_CAPTURED_LOG_AT = 64
export const MAX_CAPTURED_LOG_EXTRA_KEYS = 12
export const MAX_CAPTURED_LOG_EXTRA_STRING = 512

export function truncateCapturedLogText(value, max) {
  const text = String(value ?? '')
  if (text.length <= max) return text
  return text.slice(0, max)
}

/**
 * Redact and enforce API size limits before submit.
 *
 * @param {unknown} entry
 */
export function prepareLogEntryForSubmit(entry) {
  if (!entry || typeof entry !== 'object') return entry

  const base = redactLogEntry(entry)
  const out = {
    type: truncateCapturedLogText(base.type, MAX_CAPTURED_LOG_TYPE),
    message: truncateCapturedLogText(base.message, MAX_CAPTURED_LOG_MESSAGE),
    at: truncateCapturedLogText(base.at, MAX_CAPTURED_LOG_AT),
  }

  if (base.extra && typeof base.extra === 'object' && !Array.isArray(base.extra)) {
    const extra = {}
    let count = 0
    for (const [key, value] of Object.entries(base.extra)) {
      if (count >= MAX_CAPTURED_LOG_EXTRA_KEYS) break
      if (value != null && typeof value === 'object') {
        extra[key] = redactObject(value, { maxDepth: 2, maxKeys: 8 })
      } else {
        extra[key] = truncateCapturedLogText(redactString(value), MAX_CAPTURED_LOG_EXTRA_STRING)
      }
      count++
    }
    if (Object.keys(extra).length) {
      out.extra = extra
    }
  }

  return out
}

/**
 * @param {unknown[]} logs
 */
export function prepareCapturedLogsForSubmit(logs) {
  return (Array.isArray(logs) ? logs : []).map(prepareLogEntryForSubmit)
}
