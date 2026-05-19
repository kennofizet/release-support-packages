/**
 * Disk drawings are returned as API-relative paths (drawings/{reportId}/{file}).
 * Legacy rows may still be data URLs or /storage/... public URLs.
 */

export function isInlineDrawingSrc(src) {
  return typeof src === 'string' && src.startsWith('data:image')
}

/**
 * @returns {{ reportId: number, filename: string } | null}
 */
export function parseApiDrawingSrc(src) {
  if (!src || typeof src !== 'string' || isInlineDrawingSrc(src)) {
    return null
  }
  const match = String(src).match(/drawings\/(\d+)\/([^/?#]+)/i)
  if (!match) {
    return null
  }
  return {
    reportId: Number(match[1]),
    filename: decodeURIComponent(match[2]),
  }
}

/** True when the browser must fetch with X-Knf-Token (not usable as raw img src). */
export function needsAuthenticatedDrawingFetch(src) {
  return parseApiDrawingSrc(src) !== null
}
