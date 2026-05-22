/**
 * Capture the visible viewport for annotated screenshots (release-support UI excluded).
 * Screenshot is taken before the draw overlay opens (see Widget) while scroll is natural.
 *
 * Fixed UI (e.g. sidebar with position:fixed) is captured in a second pass and composited
 * at viewport coordinates so it does not scroll with the main document capture.
 */

const CAPTURE_ROOT_ATTR = 'data-rs-capture-scroll-root'

let scrollLockRestore = null

function readWindowScroll() {
  const se = document.scrollingElement || document.documentElement
  const vv = window.visualViewport
  let scrollX = window.pageXOffset || window.scrollX || se?.scrollLeft || 0
  let scrollY = window.pageYOffset || window.scrollY || se?.scrollTop || 0

  scrollX = Math.max(scrollX, document.documentElement?.scrollLeft || 0, document.body?.scrollLeft || 0)
  scrollY = Math.max(scrollY, document.documentElement?.scrollTop || 0, document.body?.scrollTop || 0)

  if (vv) {
    if (typeof vv.pageTop === 'number') scrollY = Math.max(scrollY, vv.pageTop)
    if (typeof vv.pageLeft === 'number') scrollX = Math.max(scrollX, vv.pageLeft)
  }

  return { scrollX, scrollY, scrollingElement: se }
}

function isIgnorableCaptureNode(el) {
  return !(el instanceof HTMLElement) || Boolean(el.closest('.rs-ignore-capture'))
}

function isVerticallyScrollable(el) {
  const style = window.getComputedStyle(el)
  if (!['auto', 'scroll', 'overlay'].includes(style.overflowY)) return false
  return el.scrollHeight > el.clientHeight + 2
}

/**
 * Inner scroll container only when the window itself is not scrolled.
 */
function findInnerScrollRoot(windowScrollY) {
  if (windowScrollY > 8) {
    return null
  }

  const cx = Math.min(window.innerWidth - 1, Math.max(1, window.innerWidth / 2))
  const cy = Math.min(window.innerHeight - 1, Math.max(1, window.innerHeight / 2))
  let el = document.elementFromPoint(cx, cy)

  let best = null
  let bestTop = 0

  while (el && el !== document.documentElement) {
    if (!isIgnorableCaptureNode(el) && el instanceof HTMLElement && isVerticallyScrollable(el)) {
      if (el.scrollTop >= bestTop) {
        bestTop = el.scrollTop
        best = el
      }
    }
    el = el.parentElement
  }

  return bestTop > 8 ? best : null
}

function clearMarkedScrollRoot() {
  document.querySelectorAll(`[${CAPTURE_ROOT_ATTR}]`).forEach((node) => {
    node.removeAttribute(CAPTURE_ROOT_ATTR)
  })
}

function markScrollRoot(el) {
  clearMarkedScrollRoot()
  if (el) {
    el.setAttribute(CAPTURE_ROOT_ATTR, '1')
  }
}

function getMarkedScrollRoot() {
  const marked = document.querySelector(`[${CAPTURE_ROOT_ATTR}="1"]`)
  return marked instanceof HTMLElement ? marked : null
}

/**
 * Top-level position:fixed roots (sidebar shell), not nested fixed children.
 * @returns {HTMLElement[]}
 */
function collectFixedRoots(ignoreSelector) {
  /** @type {HTMLElement[]} */
  const roots = []

  for (const el of document.querySelectorAll('body *')) {
    if (!(el instanceof HTMLElement) || isIgnorableCaptureNode(el)) continue
    if (window.getComputedStyle(el).position !== 'fixed') continue
    if (el.parentElement && window.getComputedStyle(el.parentElement).position === 'fixed') {
      continue
    }
    roots.push(el)
  }

  return roots
}

function isInsideFixedRoot(el, fixedRoots) {
  return fixedRoots.some((root) => root === el || root.contains(el))
}

/**
 * @param {HTMLElement} el
 */
function isFixedRootVisible(el) {
  const rect = el.getBoundingClientRect()
  if (rect.width < 2 || rect.height < 2) return false
  if (rect.bottom < 0 || rect.right < 0) return false
  if (rect.top >= window.innerHeight || rect.left >= window.innerWidth) return false
  return true
}

/**
 * Call immediately before capture / opening the draw overlay.
 * @returns {ScrollCaptureState}
 */
export function captureScrollState() {
  const { scrollX, scrollY } = readWindowScroll()
  const vv = window.visualViewport
  const inner = findInnerScrollRoot(scrollY)

  markScrollRoot(inner)

  const useInner = Boolean(inner)

  return {
    scrollX: useInner ? inner.scrollLeft : scrollX,
    scrollY: useInner ? inner.scrollTop : scrollY,
    windowScrollX: scrollX,
    windowScrollY: scrollY,
    viewportWidth: Math.round(vv?.width ?? window.innerWidth),
    viewportHeight: Math.round(vv?.height ?? window.innerHeight),
    useInnerRoot: useInner,
  }
}

/**
 * Prevent scroll while drawing — does not use body position:fixed (avoids ghost/double layout).
 * @param {ScrollCaptureState | null | undefined} state
 */
export function lockPageScrollForCapture(state) {
  unlockPageScrollForCapture()

  const inner = getMarkedScrollRoot()
  const useInner = Boolean(state?.useInnerRoot && inner)

  scrollLockRestore = {
    windowScrollX: state?.windowScrollX ?? window.scrollX,
    windowScrollY: state?.windowScrollY ?? window.scrollY,
    bodyOverflow: document.body.style.overflow,
    htmlOverflow: document.documentElement.style.overflow,
    bodyOverscroll: document.body.style.overscrollBehavior,
    htmlOverscroll: document.documentElement.style.overscrollBehavior,
    inner: null,
  }

  document.body.style.overflow = 'hidden'
  document.documentElement.style.overflow = 'hidden'
  document.body.style.overscrollBehavior = 'none'
  document.documentElement.style.overscrollBehavior = 'none'

  if (useInner && inner) {
    scrollLockRestore.inner = {
      el: inner,
      overflow: inner.style.overflow,
      overscrollBehavior: inner.style.overscrollBehavior,
      scrollTop: inner.scrollTop,
      scrollLeft: inner.scrollLeft,
    }
    inner.style.overflow = 'hidden'
    inner.style.overscrollBehavior = 'none'
  }

  window.scrollTo(scrollLockRestore.windowScrollX, scrollLockRestore.windowScrollY)
}

export function unlockPageScrollForCapture() {
  if (!scrollLockRestore) return

  const restore = scrollLockRestore

  if (restore.inner?.el) {
    const { el, overflow, overscrollBehavior, scrollTop, scrollLeft } = restore.inner
    el.style.overflow = overflow
    el.style.overscrollBehavior = overscrollBehavior
    el.scrollTop = scrollTop
    el.scrollLeft = scrollLeft
  }

  document.body.style.overflow = restore.bodyOverflow || ''
  document.documentElement.style.overflow = restore.htmlOverflow || ''
  document.body.style.overscrollBehavior = restore.bodyOverscroll || ''
  document.documentElement.style.overscrollBehavior = restore.htmlOverscroll || ''

  window.scrollTo(restore.windowScrollX, restore.windowScrollY)
  clearMarkedScrollRoot()
  scrollLockRestore = null
}

/**
 * @typedef {object} ScrollCaptureState
 * @property {number} scrollX
 * @property {number} scrollY
 * @property {number} windowScrollX
 * @property {number} windowScrollY
 * @property {number} viewportWidth
 * @property {number} viewportHeight
 * @property {boolean} useInnerRoot
 */

function buildHtml2canvasOptions(ignoreSelector, fixedRoots, extra = {}) {
  const scale = Math.min(1.25, window.devicePixelRatio || 1)

  return {
    ignoreElements: (el) => {
      if (!(el instanceof Element)) return false
      if (el.closest(ignoreSelector)) return true
      if (fixedRoots.length && isInsideFixedRoot(el, fixedRoots)) return true
      return false
    },
    useCORS: true,
    allowTaint: true,
    logging: false,
    foreignObjectRendering: false,
    scale,
    backgroundColor: '#0f172a',
    removeContainer: true,
    ...extra,
  }
}

/**
 * Composite fixed layers (sidebar) onto the scrolled viewport capture.
 * @param {HTMLCanvasElement} base
 * @param {HTMLElement[]} fixedRoots
 * @param {string} ignoreSelector
 */
async function compositeFixedLayers(base, fixedRoots, ignoreSelector) {
  const { default: html2canvas } = await import('html2canvas')
  const scale = Math.min(1.25, window.devicePixelRatio || 1)

  const out = document.createElement('canvas')
  out.width = base.width
  out.height = base.height
  const ctx = out.getContext('2d')
  if (!ctx) return base

  ctx.drawImage(base, 0, 0)

  for (const el of fixedRoots) {
    if (!isFixedRootVisible(el)) continue

    const rect = el.getBoundingClientRect()
    const width = Math.max(1, Math.ceil(rect.width))
    const height = Math.max(1, Math.ceil(rect.height))

    try {
      const layer = await html2canvas(el, buildHtml2canvasOptions(ignoreSelector, [], {
        scrollX: 0,
        scrollY: 0,
        width,
        height,
        windowWidth: width,
        windowHeight: height,
        backgroundColor: null,
      }))

      ctx.drawImage(
        layer,
        0,
        0,
        layer.width,
        layer.height,
        Math.round(rect.left * scale),
        Math.round(rect.top * scale),
        Math.round(rect.width * scale),
        Math.round(rect.height * scale),
      )
    } catch {
      /* skip layer if capture fails */
    }
  }

  return out
}

/**
 * @param {string} [ignoreSelector]
 * @param {ScrollCaptureState | null | undefined} [scrollState]
 * @returns {Promise<HTMLCanvasElement>}
 */
export async function capturePageScreenshot(ignoreSelector = '.rs-ignore-capture', scrollState = null) {
  const { default: html2canvas } = await import('html2canvas')
  const state = scrollState || captureScrollState()
  const scrollX = Math.round(state.scrollX || 0)
  const scrollY = Math.round(state.scrollY || 0)
  const w = Math.round(state.viewportWidth || window.innerWidth)
  const h = Math.round(state.viewportHeight || window.innerHeight)

  const fixedRoots = collectFixedRoots(ignoreSelector)

  const inner = getMarkedScrollRoot()
  if (state.useInnerRoot && inner) {
    return html2canvas(inner, buildHtml2canvasOptions(ignoreSelector, [], {
      scrollX: -Math.round(inner.scrollLeft),
      scrollY: -Math.round(inner.scrollTop),
      width: inner.clientWidth,
      height: inner.clientHeight,
      windowWidth: inner.clientWidth,
      windowHeight: inner.clientHeight,
    }))
  }

  const base = await html2canvas(
    document.documentElement,
    buildHtml2canvasOptions(ignoreSelector, fixedRoots, {
      scrollX: -scrollX,
      scrollY: -scrollY,
      width: w,
      height: h,
      windowWidth: document.documentElement.clientWidth,
      windowHeight: document.documentElement.clientHeight,
    }),
  )

  if (!fixedRoots.length) {
    return base
  }

  return compositeFixedLayers(base, fixedRoots, ignoreSelector)
}

export function compositeCanvases(backgroundCanvas, drawCanvas, quality = 0.82) {
  const out = document.createElement('canvas')
  out.width = backgroundCanvas.width
  out.height = backgroundCanvas.height
  const ctx = out.getContext('2d')
  if (!ctx) return ''
  ctx.drawImage(backgroundCanvas, 0, 0)
  ctx.drawImage(drawCanvas, 0, 0)
  return out.toDataURL('image/jpeg', quality)
}
