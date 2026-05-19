import { ref } from 'vue'
import { clearCapturedLogs, getCapturedLogs, markForceOpenedOnce, hasForceOpenedOnce, pushCapturedLog, snapshotContext } from '../storage'
import { prepareCapturedLogsForSubmit, truncateCapturedLogText, MAX_CAPTURED_LOG_MESSAGE } from '../utils/capturedLogLimits'
import { filterConsoleLogs } from '../utils/consoleLogs'
import { redactLogEntry } from '../utils/logRedaction'

const started = ref(false)
const bootstrapData = ref({
  force_show_reporter: false,
  capture_max_logs: 200,
  is_dev_user: false,
  latest_update: null,
  version_outdated: null,
  version_compare: null,
})
const isOpen = ref(false)

let restoreConsoleError = null
let restoreOnError = null
let restoreUnhandled = null

function toSafeString(v) {
  if (v == null) return ''
  if (typeof v === 'string') return v
  try {
    return JSON.stringify(v)
  } catch (_) {
    return String(v)
  }
}

function buildLog(type, message, extra = {}) {
  return redactLogEntry({
    type,
    message: truncateCapturedLogText(String(message || ''), MAX_CAPTURED_LOG_MESSAGE),
    extra,
    at: new Date().toISOString(),
  })
}

function startCapture(maxLogs = 200) {
  if (started.value) return
  started.value = true

  const originalConsoleError = console.error
  console.error = (...args) => {
    pushCapturedLog(buildLog('console_error', args.map(toSafeString).join(' ')), maxLogs)
    originalConsoleError(...args)
  }
  restoreConsoleError = () => { console.error = originalConsoleError }

  const originalOnError = window.onerror
  window.onerror = function (message, source, lineno, colno, error) {
    pushCapturedLog(
      buildLog('window_error', toSafeString(message), {
        source: source || '',
        line: lineno || 0,
        col: colno || 0,
        stack: error?.stack || '',
      }),
      maxLogs
    )
    if (typeof originalOnError === 'function') return originalOnError.apply(window, arguments)
    return false
  }
  restoreOnError = () => { window.onerror = originalOnError }

  const unhandled = (event) => {
    const reason = event?.reason
    pushCapturedLog(buildLog('unhandled_rejection', toSafeString(reason), { reason }), maxLogs)
  }
  window.addEventListener('unhandledrejection', unhandled)
  restoreUnhandled = () => window.removeEventListener('unhandledrejection', unhandled)
}

function stopCapture() {
  if (!started.value) return
  started.value = false
  if (restoreConsoleError) restoreConsoleError()
  if (restoreOnError) restoreOnError()
  if (restoreUnhandled) restoreUnhandled()
  restoreConsoleError = null
  restoreOnError = null
  restoreUnhandled = null
}

export function useReleaseSupportTracker() {
  return {
    isOpen,
    bootstrapData,
    started,
    startCapture,
    stopCapture,
    openReporter: () => { isOpen.value = true },
    closeReporter: () => { isOpen.value = false },
    setBootstrapData(data) {
      bootstrapData.value = {
        force_show_reporter: !!data?.force_show_reporter,
        capture_max_logs: Number(data?.capture_max_logs || 200),
        is_dev_user: !!data?.is_dev_user,
        latest_update: data?.latest_update || null,
        version_outdated: data?.version_outdated ?? null,
        version_compare: data?.version_compare ?? null,
      }
    },
    shouldForceOpenNow() {
      return bootstrapData.value.force_show_reporter && !hasForceOpenedOnce()
    },
    markForceOpenHandled() {
      markForceOpenedOnce()
    },
    getPayloadParts() {
      const maxLogs = Number(bootstrapData.value.capture_max_logs || 200)
      const logs = prepareCapturedLogsForSubmit(
        filterConsoleLogs(getCapturedLogs()).slice(-maxLogs),
      )
      return {
        captured_logs: logs,
        captured_context: snapshotContext(),
      }
    },
    clearCapturedLogs,
  }
}
