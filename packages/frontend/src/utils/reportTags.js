export const REPORT_TAG_IDS = ['bug', 'feature', 'question', 'improvement', 'other']

/** Matches ReleaseSupportReport::inactiveStatuses() on the backend. */
export const INACTIVE_STATUSES = new Set(['resolved', 'closed', 'cancelled'])

const byNewestId = (a, b) => Number(b.id) - Number(a.id)

export function getReportTag(report) {
  const tag = report?.tag ?? report?.meta?.tag
  if (typeof tag === 'string' && tag.trim()) return tag.trim().toLowerCase()
  return 'other'
}

export function isInactiveReport(report) {
  return INACTIVE_STATUSES.has(String(report?.status || '').toLowerCase())
}

/** @deprecated Use isInactiveReport */
export function isClosedReport(report) {
  return isInactiveReport(report)
}

/** Active (open / in_progress) first, inactive statuses at the bottom; newest id within each group. */
export function sortReportsActiveFirst(reports) {
  const active = []
  const inactive = []
  for (const r of reports || []) {
    if (isInactiveReport(r)) inactive.push(r)
    else active.push(r)
  }
  active.sort(byNewestId)
  inactive.sort(byNewestId)
  return [...active, ...inactive]
}

export function partitionReports(reports) {
  const active = []
  const inactive = []
  for (const r of reports || []) {
    if (isInactiveReport(r)) inactive.push(r)
    else active.push(r)
  }
  active.sort(byNewestId)
  inactive.sort(byNewestId)
  return { active, closed: inactive }
}

export function filterReports(reports, { query = '', tag = '' } = {}) {
  const q = String(query || '').trim().toLowerCase()
  const tagFilter = String(tag || '').trim().toLowerCase()
  return (reports || []).filter((r) => {
    if (tagFilter && tagFilter !== 'all' && getReportTag(r) !== tagFilter) return false
    if (!q) return true
    const hay = [
      r.title,
      r.description,
      r.app_version,
      String(r.id),
      getReportTag(r),
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
    return hay.includes(q)
  })
}
