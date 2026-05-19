/**
 * Extract a user-facing message from an axios / API error.
 *
 * @param {unknown} error
 * @param {{ submitRateLimited?: string, submitFailed?: string }} [labels]
 */
export function getApiErrorMessage(error, labels = {}) {
  const response = error && error.response
  const status = (response && response.status) || 0
  const data = (response && response.data) || {}

  const validationErrors =
    data.errors || (data.datas && data.datas.errors) || (data.data && data.data.errors)
  if (validationErrors && typeof validationErrors === 'object') {
    const messages = Object.values(validationErrors).flat()
    const first = messages.find((v) => typeof v === 'string' && String(v).trim())
    if (first) {
      const text = String(first).trim()
      if (status === 429 || /too many attempts/i.test(text)) {
        return labels.submitRateLimited || text
      }
      return text
    }
  }

  const candidates = [
    data.message,
    data.datas && data.datas.message,
    data.data && data.data.message,
    typeof data.datas === 'string' ? data.datas : '',
    typeof data.error === 'string' ? data.error : '',
    error && error.message,
  ]

  const text = String(candidates.find((v) => typeof v === 'string' && v.trim()) || '').trim()

  if (status === 429 || /too many attempts/i.test(text)) {
    return labels.submitRateLimited || text || 'Too Many Attempts.'
  }

  if (text) return text

  return labels.submitFailed || 'Could not submit report. Please try again.'
}
