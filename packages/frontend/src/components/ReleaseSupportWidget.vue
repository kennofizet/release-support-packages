<template>
  <div class="rs-widget rs-root rs-ignore-capture" :class="{ 'rs-widget--dark': effectiveDarkMode, 'rs-root--dark': effectiveDarkMode }">
    <ReleaseSupportFabHandle
      v-show="!screenDrawing && !formOpen"
      :label="t.fab"
      :dark-mode="effectiveDarkMode"
      @open="openForm"
    />

    <ReleaseSupportDrawOverlay
      :open="screenDrawing"
      :dark-mode="effectiveDarkMode"
      :labels="drawLabels"
      :scroll-state="drawScrollState"
      :initial-snapshot="drawBackgroundSnapshot"
      @save="onDrawSaved"
      @cancel="onDrawCancel"
    />

    <ReleaseSupportFormModal
      :open="formOpen"
      :dark-mode="effectiveDarkMode"
      :labels="t"
      :form="form"
      :drawings="drawings"
      :submitting="submitting"
      :submit-error="submitError"
      :version-outdated="versionOutdated"
      :latest-update="bootstrapData.latest_update"
      :version-banner-text="versionBannerText"
      :latest-update-text="latestUpdateText"
      :tag-options="tagOptions"
      :selected-tag="form.tag"
      :form-title="formTitle"
      :form-subtitle="formSubtitle"
      :title-placeholder="formTitlePlaceholder"
      @update:tag="form.tag = $event"
      @close="closeForm"
      @submit="submit"
      @open-draw="openScreenDraw"
    />

    <ReleaseSupportSuccessBanner
      :open="successOpen"
      :message="successMessageText"
      :dark-mode="effectiveDarkMode"
      :target="successTarget"
      :dismiss-label="t.submitSuccessDismiss"
      :auto-close-ms="successRedirectUrl ? 2500 : 6000"
      @close="onSuccessDismiss"
    />
  </div>
</template>

<script setup>
import { computed, inject, isRef, onMounted, ref } from 'vue'
import { useReleaseSupportTracker } from '../composables/useReleaseSupportTracker'
import { useReleaseSupportLabels } from '../composables/useReleaseSupportLabels'
import { isOutdated } from '../utils/semver'
import { getApiErrorMessage } from '../utils/apiErrorMessage'
import { capturePageScreenshot, captureScrollState } from '../utils/screenCapture'
import ReleaseSupportFabHandle from './ReleaseSupportFabHandle.vue'
import ReleaseSupportDrawOverlay from './ReleaseSupportDrawOverlay.vue'
import ReleaseSupportFormModal from './ReleaseSupportFormModal.vue'
import ReleaseSupportSuccessBanner from './ReleaseSupportSuccessBanner.vue'

const props = defineProps({
  language: { type: [String, Object], default: 'vi' },
  darkMode: { type: [Boolean, Object], default: false },
  successRedirectUrl: { type: String, default: '' },
  successMessage: { type: String, default: '' },
  successTarget: { type: String, default: '' },
})

const releaseSupportApi = inject('releaseSupportApi', null)
const releaseSupportOptions = inject('releaseSupportOptions', null)
const tracker = useReleaseSupportTracker()
const { bootstrapData } = tracker

const effectiveLanguage = computed(() => (isRef(props.language) ? props.language.value : props.language))
const effectiveDarkMode = computed(() => (isRef(props.darkMode) ? props.darkMode.value : props.darkMode))

const successRedirectUrl = computed(
  () => props.successRedirectUrl || releaseSupportOptions?.successRedirectUrl || '',
)
const successTarget = computed(
  () => props.successTarget || releaseSupportOptions?.successTarget || 'body',
)
const successMessageOverride = computed(
  () => props.successMessage || releaseSupportOptions?.successMessage || '',
)

const {
  t,
  tagOptions,
  drawLabels,
  titleForTag,
  subtitleForTag,
  titlePlaceholderForTag,
  versionBannerText: fmtVersionBanner,
  latestUpdateText: fmtLatestUpdate,
} = useReleaseSupportLabels(effectiveLanguage)

const formTitle = computed(() => titleForTag(form.value.tag))
const formSubtitle = computed(() => subtitleForTag(form.value.tag))
const formTitlePlaceholder = computed(() => titlePlaceholderForTag(form.value.tag))

const versionBannerText = computed(() => fmtVersionBanner(bootstrapData.value.latest_update?.version))
const latestUpdateText = computed(() => fmtLatestUpdate(bootstrapData.value.latest_update?.version))

const formOpen = ref(false)
const screenDrawing = ref(false)
const drawScrollState = ref(null)
const drawBackgroundSnapshot = ref(null)
const drawCaptureLoading = ref(false)
const submitting = ref(false)
const submitError = ref('')
const successOpen = ref(false)
const drawings = ref([])
const form = ref({
  title: '',
  description: '',
  app_version: releaseSupportOptions?.appVersion || '',
  tag: 'bug',
})

const successMessageText = computed(() => successMessageOverride.value || t.value.submitSuccess)

const versionOutdated = computed(() => {
  if (bootstrapData.value.version_outdated !== null) return !!bootstrapData.value.version_outdated
  const latest = bootstrapData.value.latest_update?.version
  const current = form.value.app_version
  if (!latest || !current) return false
  return isOutdated(current, latest) === true
})

function openForm() {
  submitError.value = ''
  formOpen.value = true
}

function closeForm() {
  submitError.value = ''
  formOpen.value = false
}

async function openScreenDraw() {
  submitError.value = ''
  drawScrollState.value = captureScrollState()
  drawBackgroundSnapshot.value = null
  drawCaptureLoading.value = true
  formOpen.value = false

  try {
    drawBackgroundSnapshot.value = await capturePageScreenshot('.rs-ignore-capture', drawScrollState.value)
  } catch {
    drawBackgroundSnapshot.value = null
  } finally {
    drawCaptureLoading.value = false
    screenDrawing.value = true
  }
}

function onDrawCancel() {
  screenDrawing.value = false
  drawScrollState.value = null
  drawBackgroundSnapshot.value = null
}

function onDrawSaved(dataUrl) {
  if (dataUrl) drawings.value.push(dataUrl)
  screenDrawing.value = false
  drawScrollState.value = null
  drawBackgroundSnapshot.value = null
  formOpen.value = true
}

function onSuccessDismiss() {
  successOpen.value = false
  const url = successRedirectUrl.value
  if (url) window.location.href = url
}

function parsePayload(res) {
  return res?.data?.datas ?? res?.data?.data ?? res?.data ?? {}
}

async function submit() {
  if (!releaseSupportApi?.submitReport || !form.value.title.trim()) return
  submitting.value = true
  submitError.value = ''
  try {
    const payload = tracker.getPayloadParts()
    const tag = form.value.tag || 'bug'
    await releaseSupportApi.submitReport({
      title: form.value.title.trim(),
      description: form.value.description,
      app_version: form.value.app_version,
      tag,
      captured_logs: payload.captured_logs,
      captured_context: payload.captured_context,
      drawings: drawings.value,
      meta: { source: 'release-support-widget', tag },
    })
    tracker.clearCapturedLogs()
    form.value = { title: '', description: '', app_version: form.value.app_version, tag: form.value.tag || 'bug' }
    drawings.value = []
    formOpen.value = false
    successOpen.value = true
    if (successRedirectUrl.value) {
      setTimeout(() => {
        if (successOpen.value) onSuccessDismiss()
      }, 2500)
    }
  } catch (e) {
    console.error('Submit report failed', e)
    submitError.value = getApiErrorMessage(e, {
      submitRateLimited: t.value.submitRateLimited,
      submitFailed: t.value.submitFailed,
    })
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  if (!releaseSupportApi?.bootstrap) return
  try {
    const appVer = releaseSupportOptions?.appVersion || form.value.app_version
    const res = await releaseSupportApi.bootstrap(appVer || null)
    const payload = parsePayload(res)
    tracker.setBootstrapData(payload)
    if (!form.value.app_version && payload?.latest_update?.version) {
      form.value.app_version = payload.latest_update.version
    }
    if (tracker.shouldForceOpenNow()) {
      formOpen.value = true
      tracker.markForceOpenHandled()
    }
  } catch (e) {
    console.error('Bootstrap release support failed', e)
  }
})
</script>
