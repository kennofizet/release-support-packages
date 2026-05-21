import { inject, ref } from 'vue'

const REPORTS_PER_PAGE = 20
const VERSIONS_PER_PAGE = 20

function parsePayload(res) {
  return res?.data?.datas ?? res?.data?.data ?? res?.data ?? {}
}

function emptyPaginationMeta(perPage = 20) {
  return {
    current_page: 1,
    last_page: 1,
    per_page: perPage,
    total: 0,
  }
}

function normalizePaginationMeta(meta, perPage) {
  const m = meta && typeof meta === 'object' ? meta : {}
  const current = Math.max(1, Number(m.current_page) || 1)
  const last = Math.max(1, Number(m.last_page) || 1)
  const total = Math.max(0, Number(m.total) || 0)
  const size = Math.max(1, Number(m.per_page) || perPage)

  return {
    current_page: current,
    last_page: last,
    per_page: size,
    total,
  }
}

export function useReleaseSupportReports() {
  const releaseSupportApi = inject('releaseSupportApi', null)

  const myReports = ref([])
  const myReportsPagination = ref(emptyPaginationMeta(REPORTS_PER_PAGE))
  const myReportsLoading = ref(false)

  const devReports = ref([])
  const devReportsPagination = ref(emptyPaginationMeta(REPORTS_PER_PAGE))
  const devReportsLoading = ref(false)

  const devStatus = ref({})
  const devCommentByReport = ref({})
  const devMetrics = ref(null)
  const devMetricsLoading = ref(false)

  const versionUpdates = ref([])
  const versionUpdatesPagination = ref(emptyPaginationMeta(VERSIONS_PER_PAGE))
  const versionUpdatesLoading = ref(false)
  const versionSaving = ref(false)

  const userVersionUpdates = ref([])
  const userVersionUpdatesPagination = ref(emptyPaginationMeta(VERSIONS_PER_PAGE))
  const userVersionUpdatesLoading = ref(false)
  const selectedUserVersion = ref(null)
  const userVersionDetailLoading = ref(false)

  const releasePreview = ref(null)
  const releasePreviewLoading = ref(false)
  const selectedVersion = ref(null)
  const versionDetailLoading = ref(false)
  const selectedReport = ref(null)
  const detailLoading = ref(false)
  const isDevUser = ref(false)

  async function refreshMyReports(page = myReportsPagination.value.current_page) {
    if (!releaseSupportApi?.myReports) return
    myReportsLoading.value = true
    try {
      const res = await releaseSupportApi.myReports({
        page: Math.max(1, page),
        per_page: REPORTS_PER_PAGE,
      })
      const payload = parsePayload(res)
      myReports.value = Array.isArray(payload.items) ? payload.items : []
      myReportsPagination.value = normalizePaginationMeta(payload.meta, REPORTS_PER_PAGE)
    } catch (e) {
      console.error('Load my reports failed', e)
      myReports.value = []
      myReportsPagination.value = emptyPaginationMeta(REPORTS_PER_PAGE)
    } finally {
      myReportsLoading.value = false
    }
  }

  async function openReportDetail(reportId) {
    if (!releaseSupportApi?.reportDetail) return
    detailLoading.value = true
    try {
      const res = await releaseSupportApi.reportDetail(reportId)
      const payload = parsePayload(res)
      selectedReport.value = payload.report || null
      if (selectedReport.value) devStatus.value[reportId] = selectedReport.value.status || 'open'
    } catch (e) {
      console.error('Load report detail failed', e)
    } finally {
      detailLoading.value = false
    }
  }

  async function loadDevReports(page = devReportsPagination.value.current_page) {
    if (!releaseSupportApi?.devReports) return
    devReportsLoading.value = true
    try {
      const res = await releaseSupportApi.devReports({
        page: Math.max(1, page),
        per_page: REPORTS_PER_PAGE,
      })
      const payload = parsePayload(res)
      devReports.value = Array.isArray(payload.items) ? payload.items : []
      devReportsPagination.value = normalizePaginationMeta(payload.meta, REPORTS_PER_PAGE)
      for (const item of devReports.value) {
        devStatus.value[item.id] = item.status || 'open'
        if (!devCommentByReport.value[item.id]) devCommentByReport.value[item.id] = ''
      }
    } catch (e) {
      console.error('Load dev reports failed', e)
      devReports.value = []
      devReportsPagination.value = emptyPaginationMeta(REPORTS_PER_PAGE)
    } finally {
      devReportsLoading.value = false
    }
  }

  function normalizeVersionItem(item) {
    if (!item || typeof item !== 'object') return null
    return {
      id: Number(item.id),
      version: String(item.version || ''),
      title: String(item.title || ''),
      content: String(item.content || ''),
      is_force: !!item.is_force,
      is_active: item.is_active !== false,
      merges_count: Number(item.merges_count || 0),
      merges: Array.isArray(item.merges) ? item.merges : [],
      created_at: item.created_at || null,
    }
  }

  async function loadReleasePreview() {
    if (!releaseSupportApi?.devReleasePreview) return
    releasePreviewLoading.value = true
    try {
      const res = await releaseSupportApi.devReleasePreview()
      releasePreview.value = parsePayload(res)
    } catch (e) {
      console.error('Load release preview failed', e)
      releasePreview.value = null
    } finally {
      releasePreviewLoading.value = false
    }
  }

  function normalizeUserVersionItem(item) {
    if (!item || typeof item !== 'object') return null
    return {
      id: Number(item.id),
      version: String(item.version || ''),
      title: String(item.title || ''),
      excerpt: String(item.excerpt || ''),
      content: String(item.content || ''),
      created_at: item.created_at || null,
    }
  }

  async function loadVersionDetail(id) {
    if (!releaseSupportApi?.devVersionUpdateDetail) return
    versionDetailLoading.value = true
    try {
      const res = await releaseSupportApi.devVersionUpdateDetail(id)
      const payload = parsePayload(res)
      selectedVersion.value = normalizeVersionItem(payload.item || payload)
    } catch (e) {
      console.error('Load version detail failed', e)
      selectedVersion.value = null
    } finally {
      versionDetailLoading.value = false
    }
  }

  async function loadUserVersionUpdates(page = userVersionUpdatesPagination.value.current_page) {
    if (!releaseSupportApi?.versionUpdates) return
    userVersionUpdatesLoading.value = true
    try {
      const res = await releaseSupportApi.versionUpdates({
        page: Math.max(1, page),
        per_page: VERSIONS_PER_PAGE,
      })
      const payload = parsePayload(res)
      const items = Array.isArray(payload.items) ? payload.items : []
      userVersionUpdates.value = items.map(normalizeUserVersionItem).filter(Boolean)
      userVersionUpdatesPagination.value = normalizePaginationMeta(payload.meta, VERSIONS_PER_PAGE)
    } catch (e) {
      console.error('Load user version updates failed', e)
      userVersionUpdates.value = []
      userVersionUpdatesPagination.value = emptyPaginationMeta(VERSIONS_PER_PAGE)
    } finally {
      userVersionUpdatesLoading.value = false
    }
  }

  async function loadUserVersionDetail(id) {
    if (!releaseSupportApi?.versionUpdateDetail) return
    userVersionDetailLoading.value = true
    try {
      const res = await releaseSupportApi.versionUpdateDetail(id)
      const payload = parsePayload(res)
      selectedUserVersion.value = normalizeUserVersionItem(payload.item || payload)
    } catch (e) {
      console.error('Load user version detail failed', e)
      selectedUserVersion.value = null
    } finally {
      userVersionDetailLoading.value = false
    }
  }

  async function loadDevMetrics(days = 30) {
    if (!releaseSupportApi?.devMetrics) return
    devMetricsLoading.value = true
    try {
      const res = await releaseSupportApi.devMetrics(days)
      devMetrics.value = parsePayload(res)
    } catch (e) {
      console.error('Load dev metrics failed', e)
      devMetrics.value = null
    } finally {
      devMetricsLoading.value = false
    }
  }

  async function loadVersionUpdates(page = versionUpdatesPagination.value.current_page) {
    if (!releaseSupportApi?.devVersionUpdates) return
    versionUpdatesLoading.value = true
    try {
      const res = await releaseSupportApi.devVersionUpdates({
        page: Math.max(1, page),
        per_page: VERSIONS_PER_PAGE,
      })
      const payload = parsePayload(res)
      const items = Array.isArray(payload.items) ? payload.items : []
      versionUpdates.value = items.map(normalizeVersionItem).filter(Boolean)
      versionUpdatesPagination.value = normalizePaginationMeta(payload.meta, VERSIONS_PER_PAGE)
    } catch (e) {
      console.error('Load version updates failed', e)
      versionUpdates.value = []
      versionUpdatesPagination.value = emptyPaginationMeta(VERSIONS_PER_PAGE)
    } finally {
      versionUpdatesLoading.value = false
    }
  }

  async function createVersionRelease(payload) {
    if (!releaseSupportApi?.devCreateVersionUpdate) return false
    versionSaving.value = true
    try {
      const res = await releaseSupportApi.devCreateVersionUpdate(payload)
      const item = parsePayload(res)?.item
      versionUpdatesPagination.value = { ...versionUpdatesPagination.value, current_page: 1 }
      await Promise.all([
        loadVersionUpdates(1),
        loadReleasePreview(),
        loadDevReports(devReportsPagination.value.current_page),
      ])
      if (item?.id) {
        selectedVersion.value = normalizeVersionItem(item)
      }
      return true
    } catch (e) {
      console.error('Create version release failed', e)
      return false
    } finally {
      versionSaving.value = false
    }
  }

  async function updateVersionUpdate(id, payload) {
    if (!releaseSupportApi?.devUpdateVersionUpdate) return false
    versionSaving.value = true
    try {
      await releaseSupportApi.devUpdateVersionUpdate(id, payload)
      await Promise.all([
        loadVersionUpdates(versionUpdatesPagination.value.current_page),
        loadVersionDetail(id),
      ])
      return true
    } catch (e) {
      console.error('Update version update failed', e)
      return false
    } finally {
      versionSaving.value = false
    }
  }

  async function updateStatus(reportId, status, { onDetail } = {}) {
    try {
      await releaseSupportApi.devUpdateStatus(reportId, status || 'open')
      if (onDetail) await openReportDetail(reportId)
      else await loadDevReports(devReportsPagination.value.current_page)
      await refreshMyReports(myReportsPagination.value.current_page)
    } catch (e) {
      console.error('Update status failed', e)
    }
  }

  async function submitComment(reportId, comment, { onDetail } = {}) {
    const text = String(comment || '').trim()
    if (!text) return
    try {
      await releaseSupportApi.devAddComment(reportId, text)
      devCommentByReport.value[reportId] = ''
      if (onDetail) await openReportDetail(reportId)
    } catch (e) {
      console.error('Comment failed', e)
    }
  }

  function setIsDevUser(value) {
    isDevUser.value = !!value
  }

  return {
    myReports,
    myReportsPagination,
    myReportsLoading,
    devReports,
    devReportsPagination,
    devReportsLoading,
    devStatus,
    devCommentByReport,
    devMetrics,
    devMetricsLoading,
    versionUpdates,
    versionUpdatesPagination,
    versionUpdatesLoading,
    versionSaving,
    userVersionUpdates,
    userVersionUpdatesPagination,
    userVersionUpdatesLoading,
    selectedUserVersion,
    userVersionDetailLoading,
    releasePreview,
    releasePreviewLoading,
    selectedVersion,
    versionDetailLoading,
    selectedReport,
    detailLoading,
    isDevUser,
    setIsDevUser,
    refreshMyReports,
    openReportDetail,
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
  }
}

