/** Build release notes markdown from merge queue rows (preview API shape). */
export function buildReleaseNotesFromRows(reports) {
  if (!reports?.length) return ''
  const lines = ['## Merged reports', '']
  for (const row of reports) {
    const id = Number(row.id) || 0
    const title = String(row.title || '').trim() || 'Untitled'
    const tag = String(row.tag || 'other').trim() || 'other'
    lines.push(`- [#${id}] ${title} (${tag})`)
  }
  return lines.join('\n')
}

export function buildReleaseTitleFromVersion(version, count) {
  const n = Number(count) || 0
  return `Release ${version} — ${n} merged report${n === 1 ? '' : 's'}`
}
