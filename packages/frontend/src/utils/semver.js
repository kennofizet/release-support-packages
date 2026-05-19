/**
 * Compare semver strings (major.minor.patch, optional leading "v").
 * @returns {-1|0|1|null}
 */
export function compareSemver(a, b) {
  const pa = parseSemver(a)
  const pb = parseSemver(b)
  if (!pa || !pb) return null
  for (let i = 0; i < 3; i++) {
    if (pa[i] < pb[i]) return -1
    if (pa[i] > pb[i]) return 1
  }
  return 0
}

export function isOutdated(current, latest) {
  const cmp = compareSemver(current, latest)
  if (cmp === null) return null
  return cmp < 0
}

export function parseSemver(version) {
  if (!version || typeof version !== 'string') return null
  const m = version.trim().replace(/^[vV]/, '').match(/^(\d+)\.(\d+)\.(\d+)/)
  if (!m) return null
  return [Number(m[1]), Number(m[2]), Number(m[3])]
}
