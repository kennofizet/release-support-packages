/**
 * Capture the visible page (excluding release-support UI) for annotated screenshots.
 */
export async function capturePageScreenshot(ignoreSelector = '.rs-ignore-capture') {
  const { default: html2canvas } = await import('html2canvas')
  return html2canvas(document.documentElement, {
    ignoreElements: (el) => {
      if (!(el instanceof Element)) return false
      return Boolean(el.closest(ignoreSelector))
    },
    useCORS: true,
    allowTaint: true,
    logging: false,
    foreignObjectRendering: false,
    scale: Math.min(1.25, window.devicePixelRatio || 1),
    width: window.innerWidth,
    height: window.innerHeight,
    windowWidth: window.innerWidth,
    windowHeight: window.innerHeight,
    scrollX: -window.scrollX,
    scrollY: -window.scrollY,
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
