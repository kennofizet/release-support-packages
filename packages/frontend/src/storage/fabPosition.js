const FAB_POSITION_KEY = 'release_support_fab_position'

const DEFAULT = { edge: 'right', offset: 0.55 }

export function getFabPosition() {
  try {
    const raw = localStorage.getItem(FAB_POSITION_KEY)
    if (!raw) return { ...DEFAULT }
    const parsed = JSON.parse(raw)
    const edge = ['top', 'right', 'bottom', 'left'].includes(parsed?.edge) ? parsed.edge : DEFAULT.edge
    const offset = Number(parsed?.offset)
    return {
      edge,
      offset: Number.isFinite(offset) ? Math.min(0.92, Math.max(0.08, offset)) : DEFAULT.offset,
    }
  } catch {
    return { ...DEFAULT }
  }
}

export function setFabPosition(edge, offset) {
  localStorage.setItem(
    FAB_POSITION_KEY,
    JSON.stringify({
      edge,
      offset: Math.min(0.92, Math.max(0.08, offset)),
    }),
  )
}
