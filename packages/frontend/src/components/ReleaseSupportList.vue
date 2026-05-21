<template>
  <div
    class="rs-list-root rs-root"
    :class="{ 'rs-list-root--dark': effectiveDarkMode, 'rs-root--dark': effectiveDarkMode }"
  >
    <header v-if="showHeader" class="rs-list-root__head">
      <div>
        <h2 class="rs-list-root__title">{{ pageTitle }}</h2>
        <p class="rs-list-root__subtitle">{{ pageSubtitle }}</p>
      </div>
      <button v-if="showNewReport" type="button" class="rs-btn rs-btn--primary rs-btn--sm" @click="$emit('new-report')">
        + {{ t.newReport }}
      </button>
    </header>

    <ReleaseSupportListPanel
      :mode="listMode"
      :dark-mode="effectiveDarkMode"
      :labels="t"
      :tag-options="tagOptions"
      :tag-label-for="tagLabelFor"
      :status-label-for="statusLabelFor"
      :view="view"
      :my-reports="myReports"
      :my-reports-pagination="myReportsPagination"
      :my-reports-loading="myReportsLoading"
      :dev-reports="devReports"
      :dev-reports-pagination="devReportsPagination"
      :dev-reports-loading="devReportsLoading"
      :dev-status-map="devStatus"
      :dev-comment-map="devCommentByReport"
      :report="selectedReport"
      :detail-loading="detailLoading"
      :timeline-label-fn="timelineLabel"
      :dev-tab="devTab"
      :dev-metrics="devMetrics"
      :dev-metrics-loading="devMetricsLoading"
      :release-preview="releasePreview"
      :release-preview-loading="releasePreviewLoading"
      :version-updates="versionUpdates"
      :version-updates-loading="versionUpdatesLoading"
      :version-saving="versionSaving"
      :selected-version="selectedVersion"
      :version-detail-loading="versionDetailLoading"
      :create-version-release="createVersionRelease"
      :update-version="updateVersionUpdate"
      :load-version-detail="loadVersionDetail"
      :version-pagination="versionUpdatesPagination"
      :user-tab="userTab"
      :user-version-items="userVersionUpdates"
      :user-version-pagination="userVersionUpdatesPagination"
      :user-version-updates-loading="userVersionUpdatesLoading"
      :selected-user-version="selectedUserVersion"
      :user-version-detail-loading="userVersionDetailLoading"
      :load-user-version-detail="loadUserVersionDetail"
      @update:dev-tab="devTab = $event"
      @update:user-tab="onUserTab"
      @update:reports-page="onReportsPage"
      @update:versions-page="onVersionsPage"
      @update:user-versions-page="onUserVersionsPage"
      @navigate="onNavigate"
      @open-detail="openDetail"
      @update-status="onUpdateStatus"
      @add-comment="onAddComment"
    />
  </div>
</template>

<script setup>
import { computed, inject, isRef, onMounted, ref, watch } from 'vue'
import { useReleaseSupportTracker } from '../composables/useReleaseSupportTracker'
import { useReleaseSupportLabels } from '../composables/useReleaseSupportLabels'
import { useReleaseSupportReports } from '../composables/useReleaseSupportReports'
import ReleaseSupportListPanel from './ReleaseSupportListPanel.vue'

const props = defineProps({
  language: { type: [String, Object], default: 'vi' },
  darkMode: { type: [Boolean, Object], default: false },
  showHeader: { type: Boolean, default: true },
  showNewReport: { type: Boolean, default: false },
  headerTitle: { type: String, default: '' },
})

defineEmits(['new-report'])

const releaseSupportApi = inject('releaseSupportApi', null)
const releaseSupportOptions = inject('releaseSupportOptions', null)
const tracker = useReleaseSupportTracker()
const {
  isDevUser,
  myReports,
  myReportsPagination,
  myReportsLoading,
  devReports,
  devReportsPagination,
  devReportsLoading,
  devStatus,
  devCommentByReport,
  selectedReport,
  detailLoading,
  setIsDevUser,
  refreshMyReports,
  openReportDetail,
  devMetrics,
  devMetricsLoading,
  releasePreview,
  releasePreviewLoading,
  versionUpdates,
  versionUpdatesPagination,
  versionUpdatesLoading,
  versionSaving,
  selectedVersion,
  versionDetailLoading,
  userVersionUpdates,
  userVersionUpdatesPagination,
  userVersionUpdatesLoading,
  selectedUserVersion,
  userVersionDetailLoading,
  loadDevReports,
  loadDevMetrics,
  loadVersionUpdates,
  loadReleasePreview,
  loadVersionDetail,
  loadUserVersionUpdates,
  loadUserVersionDetail,
  createVersionRelease,
  updateVersionUpdate,
  updateStatus,
  submitComment,
} = useReleaseSupportReports()

const devTab = ref('reports')
const userTab = ref('reports')

const effectiveLanguage = computed(() => (isRef(props.language) ? props.language.value : props.language))
const effectiveDarkMode = computed(() => (isRef(props.darkMode) ? props.darkMode.value : props.darkMode))

const { t, tagOptions, tagLabelFor, statusLabelFor, timelineLabel } = useReleaseSupportLabels(effectiveLanguage)

const view = ref('list')

const listMode = computed(() => (isDevUser.value ? 'dev' : 'user'))

const pageTitle = computed(() => {
  if (props.headerTitle) return props.headerTitle
  if (view.value === 'detail') return t.value.titleDetail
  return listMode.value === 'dev' ? t.value.titleDev : t.value.titleHub
})

const pageSubtitle = computed(() => {
  if (view.value === 'detail') return ''
  return listMode.value === 'dev' ? t.value.devMode : t.value.userMode
})

function parsePayload(res) {
  return res?.data?.datas ?? res?.data?.data ?? res?.data ?? {}
}

async function loadDevOpsData() {
  await Promise.all([loadDevMetrics(30), loadVersionUpdates(), loadReleasePreview()])
}

async function loadListData() {
  if (listMode.value === 'dev') {
    await loadDevReports(devReportsPagination.value.current_page)
    await loadDevOpsData()
  } else {
    await Promise.all([
      refreshMyReports(myReportsPagination.value.current_page),
      loadUserVersionUpdates(userVersionUpdatesPagination.value.current_page),
    ])
  }
}

function onUserTab(tab) {
  userTab.value = tab
  if (tab === 'updates') {
    loadUserVersionUpdates(userVersionUpdatesPagination.value.current_page)
  }
}

async function onUserVersionsPage(page) {
  await loadUserVersionUpdates(page)
}

async function onReportsPage(page) {
  if (listMode.value === 'dev') await loadDevReports(page)
  else await refreshMyReports(page)
}

async function onVersionsPage(page) {
  await loadVersionUpdates(page)
}

function onNavigate(next) {
  if (next === 'list') {
    view.value = 'list'
    selectedReport.value = null
    loadListData()
  }
}

async function openDetail(reportId) {
  view.value = 'detail'
  await openReportDetail(reportId)
}

async function onUpdateStatus(reportId, status) {
  const onDetail = view.value === 'detail' && selectedReport.value?.id === reportId
  await updateStatus(reportId, status, { onDetail })
  await loadListData()
}

async function onAddComment(reportId, comment) {
  const onDetail = view.value === 'detail' && selectedReport.value?.id === reportId
  await submitComment(reportId, comment, { onDetail })
}

watch(listMode, () => {
  view.value = 'list'
  devTab.value = 'reports'
  userTab.value = 'reports'
  selectedReport.value = null
  loadListData()
})

watch(devTab, (tab) => {
  if (listMode.value !== 'dev') return
  if (tab === 'metrics' && !devMetrics.value) loadDevMetrics(30)
  if (tab === 'versions') {
    loadReleasePreview()
    loadVersionUpdates(versionUpdatesPagination.value.current_page)
  }
})

onMounted(async () => {
  if (!releaseSupportApi?.bootstrap) {
    await loadListData()
    return
  }
  try {
    const appVer = releaseSupportOptions?.appVersion || null
    const res = await releaseSupportApi.bootstrap(appVer)
    const payload = parsePayload(res)
    tracker.setBootstrapData(payload)
    setIsDevUser(payload?.is_dev_user)
    await loadListData()
  } catch (e) {
    console.error('Bootstrap release support list failed', e)
  }
})

defineExpose({
  refreshMyReports,
  loadDevReports,
  view,
})
</script>

<script>
export default {
  name: 'ReleaseSupportList',
}
</script>
