import './styles/rs-theme.css'
import { reactive } from 'vue'
import { createReleaseSupportApi } from './api'
import ReleaseSupportWidget from './components/ReleaseSupportWidget.vue'
import ReleaseSupportList from './components/ReleaseSupportList.vue'
import ReleaseSupportListPanel from './components/ReleaseSupportListPanel.vue'
import { useReleaseSupportTracker } from './composables/useReleaseSupportTracker'

const installedApps = new WeakMap()

function buildOptions(options = {}) {
  return {
    appVersion: options.appVersion || '',
    language: options.language || 'vi',
    successRedirectUrl: options.successRedirectUrl || '',
    successMessage: options.successMessage || '',
    successTarget: options.successTarget || 'body',
  }
}

function createApiFacade() {
  let impl = null
  return {
    setImpl(nextImpl) {
      impl = nextImpl
    },
    bootstrap: (...args) => impl?.bootstrap(...args),
    submitReport: (...args) => impl?.submitReport(...args),
    myReports: (...args) => impl?.myReports(...args),
    reportDetail: (...args) => impl?.reportDetail(...args),
    fetchDrawing: (...args) => impl?.fetchDrawing(...args),
    devReports: (...args) => impl?.devReports(...args),
    devUpdateStatus: (...args) => impl?.devUpdateStatus(...args),
    devAddComment: (...args) => impl?.devAddComment(...args),
    devVersionUpdates: (...args) => impl?.devVersionUpdates(...args),
    devCreateVersionUpdate: (...args) => impl?.devCreateVersionUpdate(...args),
    devUpdateVersionUpdate: (...args) => impl?.devUpdateVersionUpdate(...args),
    devMetrics: (...args) => impl?.devMetrics(...args),
  }
}

function applyConfig(store, options = {}) {
  const opts = buildOptions(options)
  const api = createReleaseSupportApi(options.backendUrl || '', options.token || '')
  store.facade.setImpl(api)
  Object.assign(store.options, opts)
  store.app.config.globalProperties.$releaseSupportApi = store.facade
}

/**
 * Install release-support frontend module (safe to call once per app).
 * Calling again on the same app updates API token/URL and options only (no Vue warnings).
 */
export function installReleaseSupportModule(app, options = {}) {
  let store = installedApps.get(app)

  if (store) {
    applyConfig(store, options)
    return store.facade
  }

  const facade = createApiFacade()
  const moduleOptions = reactive(buildOptions(options))

  store = { app, facade, options: moduleOptions }
  installedApps.set(app, store)

  applyConfig(store, options)

  app.provide('releaseSupportApi', facade)
  app.provide('releaseSupportOptions', moduleOptions)

  const tracker = useReleaseSupportTracker()
  if (options.autoStartCapture !== false) {
    tracker.startCapture(Number(options.captureMaxLogs || 200))
  }

  app.component('ReleaseSupportWidget', ReleaseSupportWidget)
  app.component('ReleaseSupportList', ReleaseSupportList)

  return facade
}

/** Update token/URL/options after login without re-registering components. */
export function configureReleaseSupportModule(app, options = {}) {
  return installReleaseSupportModule(app, options)
}

export function isReleaseSupportModuleInstalled(app) {
  return installedApps.has(app)
}

export {
  createReleaseSupportApi,
  ReleaseSupportWidget,
  ReleaseSupportList,
  ReleaseSupportListPanel,
  useReleaseSupportTracker,
}
export { compareSemver, isOutdated, parseSemver } from './utils/semver'
export { t, createTranslator, formatMessage, translations } from './i18n'

export default {
  install: installReleaseSupportModule,
}
