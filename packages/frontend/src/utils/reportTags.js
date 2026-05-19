export const REPORT_TAG_IDS = ['bug', 'feature', 'question', 'improvement', 'other']

const CLOSED_STATUSES = new Set(['closed', 'resolved'])

export function getReportTag(report) {
  const tag = report?.tag ?? report?.meta?.tag
  if (typeof tag === 'string' && tag.trim()) return tag.trim().toLowerCase()
  return 'other'
}

export function isClosedReport(report) {
  return CLOSED_STATUSES.has(String(report?.status || '').toLowerCase())
}

export function partitionReports(reports) {
  const active = []
  const closed = []
  for (const r of reports || []) {
    if (isClosedReport(r)) closed.push(r)
    else active.push(r)
  }
  const byNewest = (a, b) => Number(b.id) - Number(a.id)
  active.sort(byNewest)
  closed.sort(byNewest)
  return { active, closed }
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
