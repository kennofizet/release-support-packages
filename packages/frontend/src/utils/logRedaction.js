const SENSITIVE_KEY = /password|passwd|token|secret|authorization|cookie|api[_-]?key|bearer|credential/i

const SENSITIVE_VALUE_PATTERNS = [
  [/\bBearer\s+[A-Za-z0-9._\-+/=]{8,}\b/gi, 'Bearer [redacted]'],
  [/\b(token|password|secret|api[_-]?key)\s*[=:]\s*[^\s&,"']+/gi, '$1=[redacted]'],
]

export function redactString(value) {
  if (value == null || value === '') return ''
  let out = String(value)
  for (const [pattern, replacement] of SENSITIVE_VALUE_PATTERNS) {
    out = out.replace(pattern, replacement)
  }
  return out
}

export function redactObject(data, { maxDepth = 3, maxKeys = 20 } = {}) {
  if (data == null || maxDepth < 0) return data
  if (typeof data !== 'object') return redactString(data)
  if (Array.isArray(data)) {
    return data.slice(0, maxKeys).map((item) => redactObject(item, { maxDepth: maxDepth - 1, maxKeys: 12 }))
  }
  const out = {}
  let count = 0
  for (const [key, value] of Object.entries(data)) {
    if (count >= maxKeys) break
    if (SENSITIVE_KEY.test(key)) {
      out[key] = '[redacted]'
    } else if (value != null && typeof value === 'object') {
      out[key] = redactObject(value, { maxDepth: maxDepth - 1, maxKeys: 12 })
    } else {
      out[key] = redactString(value)
    }
    count++
  }
  return out
}

export function redactLogEntry(entry) {
  if (!entry || typeof entry !== 'object') return entry
  return {
    type: entry.type,
    message: redactString(entry.message),
    at: entry.at,
    ...(entry.extra ? { extra: redactObject(entry.extra) } : {}),
  }
}

export function redactApiErrorExtra(extra) {
  if (!extra || typeof extra !== 'object') return extra
  const safe = { ...extra }
  if (safe.data != null) {
    safe.data = redactObject(safe.data)
  }
  if (safe.url) {
    safe.url = redactString(String(safe.url).split('?')[0])
  }
  return safe
}
