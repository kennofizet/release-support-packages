import { inject, ref } from 'vue'

function parsePayload(res) {
  return res?.data?.datas ?? res?.data?.data ?? res?.data ?? {}
}

export function useReleaseSupportReports() {
  const releaseSupportApi = inject('releaseSupportApi', null)

  const myReports = ref([])
  const devReports = ref([])
  const devStatus = ref({})
  const devCommentByReport = ref({})
  const devMetrics = ref(null)
  const selectedReport = ref(null)
  const detailLoading = ref(false)
  const isDevUser = ref(false)

  async function refreshMyReports() {
    if (!releaseSupportApi?.myReports) return
    try {
      const res = await releaseSupportApi.myReports()
      const payload = parsePayload(res)
      myReports.value = Array.isArray(payload.items) ? payload.items : []
    } catch (e) {
      console.error('Load my reports failed', e)
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

  async function loadDevReports() {
    if (!releaseSupportApi?.devReports) return
    try {
      const res = await releaseSupportApi.devReports()
      const payload = parsePayload(res)
      devReports.value = Array.isArray(payload.items) ? payload.items : []
      for (const item of devReports.value) {
        devStatus.value[item.id] = item.status || 'open'
        if (!devCommentByReport.value[item.id]) devCommentByReport.value[item.id] = ''
      }
    } catch (e) {
      console.error('Load dev reports failed', e)
    }
  }

  async function loadDevMetrics() {
    if (!releaseSupportApi?.devMetrics) return
    try {
      const res = await releaseSupportApi.devMetrics(30)
      devMetrics.value = parsePayload(res)
    } catch (e) {
      console.error('Load dev metrics failed', e)
    }
  }

  async function updateStatus(reportId, status, { onDetail } = {}) {
    try {
      await releaseSupportApi.devUpdateStatus(reportId, status || 'open')
      if (onDetail) await openReportDetail(reportId)
      else await loadDevReports()
      await refreshMyReports()
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
    devReports,
    devStatus,
    devCommentByReport,
    devMetrics,
    selectedReport,
    detailLoading,
    isDevUser,
    setIsDevUser,
    refreshMyReports,
    openReportDetail,
    loadDevReports,
    loadDevMetrics,
    updateStatus,
    submitComment,
  }
}
