import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { getFabPosition, setFabPosition } from '../storage/fabPosition'

const DRAG_THRESHOLD_PX = 8
const EDGE_INSET_PX = 0

function clampOffset(value) {
  return Math.min(0.92, Math.max(0.08, value))
}

function nearestEdge(clientX, clientY) {
  const w = window.innerWidth
  const h = window.innerHeight
  const distances = [
    { edge: 'top', d: clientY },
    { edge: 'right', d: w - clientX },
    { edge: 'bottom', d: h - clientY },
    { edge: 'left', d: clientX },
  ]
  distances.sort((a, b) => a.d - b.d)
  return distances[0].edge
}

function offsetAlongEdge(edge, clientX, clientY) {
  const w = window.innerWidth
  const h = window.innerHeight
  if (edge === 'top' || edge === 'bottom') {
    return clampOffset(clientX / w)
  }
  return clampOffset(clientY / h)
}

export function useReleaseSupportFabDock() {
  const saved = getFabPosition()
  const edge = ref(saved.edge)
  const offset = ref(saved.offset)
  const expanded = ref(false)
  const dragging = ref(false)
  const dragPoint = ref(null)

  let pointerId = null
  let startX = 0
  let startY = 0
  let moved = false
  let collapseTimer = null

  function persist() {
    setFabPosition(edge.value, offset.value)
  }

  function showFullArrow() {
    expanded.value = true
    if (collapseTimer) clearTimeout(collapseTimer)
    collapseTimer = setTimeout(() => {
      if (!dragging.value) expanded.value = false
    }, 2800)
  }

  const visualEdge = computed(() => {
    if (dragging.value && dragPoint.value) {
      return nearestEdge(dragPoint.value.x, dragPoint.value.y)
    }
    return edge.value
  })

  const dockClasses = computed(() => [
    `rs-fab-dock--${visualEdge.value}`,
    expanded.value ? 'rs-fab-dock--full' : 'rs-fab-dock--peek',
    dragging.value ? 'rs-fab-dock--dragging' : '',
  ])

  const dockStyle = computed(() => {
    if (dragging.value && dragPoint.value) {
      return {
        left: `${dragPoint.value.x}px`,
        top: `${dragPoint.value.y}px`,
        right: 'auto',
        bottom: 'auto',
        transform: 'translate(-50%, -50%)',
      }
    }

    const pct = `${offset.value * 100}%`
    const base = { zIndex: 1500 }

    if (edge.value === 'right') {
      return { ...base, top: pct, right: `${EDGE_INSET_PX}px`, left: 'auto', bottom: 'auto', transform: 'translateY(-50%)' }
    }
    if (edge.value === 'left') {
      return { ...base, top: pct, left: `${EDGE_INSET_PX}px`, right: 'auto', bottom: 'auto', transform: 'translateY(-50%)' }
    }
    if (edge.value === 'top') {
      return { ...base, left: pct, top: `${EDGE_INSET_PX}px`, right: 'auto', bottom: 'auto', transform: 'translateX(-50%)' }
    }
    return { ...base, left: pct, bottom: `${EDGE_INSET_PX}px`, right: 'auto', top: 'auto', transform: 'translateX(-50%)' }
  })

  function onPointerDown(e) {
    if (e.button !== 0) return
    pointerId = e.pointerId
    startX = e.clientX
    startY = e.clientY
    moved = false
    dragging.value = false
    dragPoint.value = { x: e.clientX, y: e.clientY }
    e.currentTarget.setPointerCapture(e.pointerId)
    e.preventDefault()
  }

  function onPointerMove(e) {
    if (e.pointerId !== pointerId) return
    const dx = e.clientX - startX
    const dy = e.clientY - startY
    if (!moved && Math.hypot(dx, dy) < DRAG_THRESHOLD_PX) return

    moved = true
    dragging.value = true
    expanded.value = false
    dragPoint.value = {
      x: Math.min(window.innerWidth - 24, Math.max(24, e.clientX)),
      y: Math.min(window.innerHeight - 24, Math.max(24, e.clientY)),
    }
  }

  function onPointerUp(e, onTap) {
    if (e.pointerId !== pointerId) return
    try {
      e.currentTarget.releasePointerCapture(e.pointerId)
    } catch {
      /* already released */
    }

    const wasDrag = moved
    pointerId = null
    dragging.value = false
    dragPoint.value = null

    if (wasDrag) {
      const nextEdge = nearestEdge(e.clientX, e.clientY)
      edge.value = nextEdge
      offset.value = offsetAlongEdge(nextEdge, e.clientX, e.clientY)
      persist()
      showFullArrow()
      return
    }

    onTap?.()
  }

  function onPointerCancel(e) {
    if (e.pointerId !== pointerId) return
    pointerId = null
    dragging.value = false
    dragPoint.value = null
  }

  function onHoverEnter() {
    if (!dragging.value) expanded.value = true
  }

  function onHoverLeave() {
    if (!dragging.value) expanded.value = false
  }

  onMounted(() => {
    showFullArrow()
  })

  onBeforeUnmount(() => {
    if (collapseTimer) clearTimeout(collapseTimer)
  })

  return {
    edge,
    offset,
    expanded,
    dragging,
    dockClasses,
    dockStyle,
    onPointerDown,
    onPointerMove,
    onPointerUp,
    onPointerCancel,
    onHoverEnter,
    onHoverLeave,
    showFullArrow,
  }
}
