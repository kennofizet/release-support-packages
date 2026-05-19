/** Log types captured from the browser console / runtime only (not server file logs). */
const CONSOLE_LOG_TYPES = new Set([
  'console_error',
  'window_error',
  'unhandled_rejection',
  'api_error',
])

export function isConsoleLogEntry(entry) {
  return CONSOLE_LOG_TYPES.has(entry?.type)
}

export function filterConsoleLogs(logs) {
  return (Array.isArray(logs) ? logs : []).filter(isConsoleLogEntry)
}

export function formatConsoleLogLine(entry) {
  const time = entry?.at ? new Date(entry.at).toLocaleTimeString() : ''
  const type = entry?.type || 'log'
  const msg = entry?.message || ''
  return `[${time}] ${type}: ${msg}`
}
