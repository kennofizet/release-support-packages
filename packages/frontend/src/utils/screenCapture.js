/**
 * Capture the visible viewport for annotated screenshots (release-support UI excluded).
 * iOS: save scroll before overlay opens; lock body scroll so capture matches what the user saw.
 */

let lastScrollRootEl = null
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

function findInnerScrollRoot() {
  let best = null
  let bestTop = 0

  for (const child of document.body?.children || []) {
    if (!(child instanceof HTMLElement)) continue
    if (child.closest?.('.rs-ignore-capture')) continue
    const style = window.getComputedStyle(child)
    if (!['auto', 'scroll', 'overlay'].includes(style.overflowY)) continue
    if (child.scrollHeight <= child.clientHeight + 2) continue
    if (child.scrollTop > bestTop) {
      bestTop = child.scrollTop
      best = child
    }
  }

  return best
}

/**
 * Call immediately before opening the draw overlay (before scroll can jump on iOS).
 * @returns {ScrollCaptureState}
 */
export function captureScrollState() {
  const { scrollX, scrollY } = readWindowScroll()
  const vv = window.visualViewport
  const inner = findInnerScrollRoot()

  lastScrollRootEl =
    inner && inner.scrollTop > Math.max(scrollY, 20) ? inner : null

  return {
    scrollX: lastScrollRootEl ? lastScrollRootEl.scrollLeft : scrollX,
    scrollY: lastScrollRootEl ? lastScrollRootEl.scrollTop : scrollY,
    windowScrollX: scrollX,
    windowScrollY: scrollY,
    viewportWidth: Math.round(vv?.width ?? window.innerWidth),
    viewportHeight: Math.round(vv?.height ?? window.innerHeight),
    useInnerRoot: Boolean(lastScrollRootEl),
  }
}

/**
 * Freeze page scroll at the saved position while the draw overlay is open (iOS modal fix).
 * @param {ScrollCaptureState | null | undefined} state
 */
export function lockPageScrollForCapture(state) {
  unlockPageScrollForCapture()

  const scrollY = state?.windowScrollY ?? state?.scrollY ?? window.scrollY
  const scrollX = state?.windowScrollX ?? state?.scrollX ?? window.scrollX

  scrollLockRestore = {
    body: {
      position: document.body.style.position,
      top: document.body.style.top,
      left: document.body.style.left,
      right: document.body.style.right,
      width: document.body.style.width,
      overflow: document.body.style.overflow,
    },
    html: {
      overflow: document.documentElement.style.overflow,
    },
    scrollX,
    scrollY,
    inner: null,
  }

  document.body.style.position = 'fixed'
  document.body.style.top = `-${scrollY}px`
  document.body.style.left = '0'
  document.body.style.right = '0'
  document.body.style.width = '100%'
  document.body.style.overflow = 'hidden'
  document.documentElement.style.overflow = 'hidden'

  if (lastScrollRootEl) {
    scrollLockRestore.inner = {
      el: lastScrollRootEl,
      overflow: lastScrollRootEl.style.overflow,
      scrollTop: lastScrollRootEl.scrollTop,
      scrollLeft: lastScrollRootEl.scrollLeft,
    }
    lastScrollRootEl.style.overflow = 'hidden'
  }
}

export function unlockPageScrollForCapture() {
  if (!scrollLockRestore) return

  const { body, html, scrollX, scrollY, inner } = scrollLockRestore

  document.body.style.position = body.position
  document.body.style.top = body.top
  document.body.style.left = body.left
  document.body.style.right = body.right
  document.body.style.width = body.width
  document.body.style.overflow = body.overflow
  document.documentElement.style.overflow = html.overflow

  if (inner?.el) {
    inner.el.style.overflow = inner.overflow
    inner.el.scrollTop = inner.scrollTop
    inner.el.scrollLeft = inner.scrollLeft
  }

  window.scrollTo(scrollX, scrollY)
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

/**
 * @param {string} [ignoreSelector]
 * @param {ScrollCaptureState | null | undefined} [scrollState]
 */
export async function capturePageScreenshot(ignoreSelector = '.rs-ignore-capture', scrollState = null) {
  const { default: html2canvas } = await import('html2canvas')
  const state = scrollState || captureScrollState()
  const scrollX = Math.round(state.scrollX || 0)
  const scrollY = Math.round(state.scrollY || 0)
  const w = Math.round(state.viewportWidth || window.innerWidth)
  const h = Math.round(state.viewportHeight || window.innerHeight)

  const common = {
    ignoreElements: (el) => {
      if (!(el instanceof Element)) return false
      return Boolean(el.closest(ignoreSelector))
    },
    useCORS: true,
    allowTaint: true,
    logging: false,
    foreignObjectRendering: false,
    scale: Math.min(1.25, window.devicePixelRatio || 1),
  }

  const inner = lastScrollRootEl
  if (state.useInnerRoot && inner) {
    const left = scrollX
    const top = scrollY
    return html2canvas(inner, {
      ...common,
      scrollX: -left,
      scrollY: -top,
      width: inner.clientWidth,
      height: inner.clientHeight,
      windowWidth: inner.clientWidth,
      windowHeight: inner.clientHeight,
      x: left,
      y: top,
    })
  }

  return html2canvas(document.documentElement, {
    ...common,
    width: w,
    height: h,
    windowWidth: w,
    windowHeight: h,
    scrollX: -scrollX,
    scrollY: -scrollY,
    x: scrollX,
    y: scrollY,
  })
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
