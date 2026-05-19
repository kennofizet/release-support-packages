const LOGS_KEY = 'release_support_logs'
const FORCE_OPEN_KEY = 'release_support_force_opened_once'

function parseJson(raw, fallback) {
  try {
    return JSON.parse(raw)
  } catch (_) {
    return fallback
  }
}

export function getCapturedLogs() {
  const raw = localStorage.getItem(LOGS_KEY)
  if (!raw) return []
  const parsed = parseJson(raw, [])
  return Array.isArray(parsed) ? parsed : []
}

export function setCapturedLogs(logs) {
  localStorage.setItem(LOGS_KEY, JSON.stringify(Array.isArray(logs) ? logs : []))
}

export function pushCapturedLog(item, maxLogs = 200) {
  const current = getCapturedLogs()
  current.push(item)
  if (current.length > maxLogs) {
    current.splice(0, current.length - maxLogs)
  }
  setCapturedLogs(current)
}

export function clearCapturedLogs() {
  localStorage.removeItem(LOGS_KEY)
}

export function hasForceOpenedOnce() {
  return localStorage.getItem(FORCE_OPEN_KEY) === '1'
}

export function markForceOpenedOnce() {
  localStorage.setItem(FORCE_OPEN_KEY, '1')
}

export function snapshotContext() {
  return {
    href: window.location.href,
    pathname: window.location.pathname,
    user_agent: navigator.userAgent,
    viewport: `${window.innerWidth}x${window.innerHeight}`,
    captured_at: new Date().toISOString(),
  }
}
