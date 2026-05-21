<template>
  <Teleport to="body">
    <div v-if="open" class="rs-draw-overlay rs-ignore-capture" :class="{ 'rs-draw-overlay--dark': darkMode }">
      <canvas ref="bgCanvasRef" class="rs-draw-overlay__bg" aria-hidden="true" />
      <canvas
        ref="drawCanvasRef"
        class="rs-draw-overlay__ink"
        @pointerdown="onPointerDown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUp"
        @pointercancel="onPointerUp"
        @pointerleave="onPointerUp"
      />

      <div v-if="capturing && !bgSnapshotReady" class="rs-draw-overlay__loading" aria-live="polite">
        <div class="rs-draw-overlay__loading-card">
          <span class="rs-draw-overlay__spinner" aria-hidden="true" />
          <p>{{ labels.capturing }}</p>
        </div>
      </div>

      <div class="rs-draw-toolbar">
        <div class="rs-draw-toolbar__tools">
          <button
            type="button"
            class="rs-draw-tool"
            :class="{ 'rs-draw-tool--active': tool === 'pen' }"
            :title="labels.pen"
            @click="tool = 'pen'"
          >
            ✎
          </button>
          <button
            type="button"
            class="rs-draw-tool"
            :class="{ 'rs-draw-tool--active': tool === 'eraser' }"
            :title="labels.eraser"
            @click="tool = 'eraser'"
          >
            ◻
          </button>
          <span class="rs-draw-toolbar__sep" />
          <button
            v-for="w in strokeWidths"
            :key="'w-' + w"
            type="button"
            class="rs-draw-tool rs-draw-tool--size"
            :class="{ 'rs-draw-tool--active': strokeWidth === w }"
            @click="strokeWidth = w"
          >
            <span :style="{ width: w + 'px', height: w + 'px' }" class="rs-draw-dot" />
          </button>
          <span class="rs-draw-toolbar__sep" />
          <button
            v-for="c in colors"
            :key="c"
            type="button"
            class="rs-draw-tool rs-draw-tool--color"
            :class="{ 'rs-draw-tool--active': color === c && tool === 'pen' }"
            :style="{ '--swatch': c }"
            @click="selectColor(c)"
          />
        </div>
        <div class="rs-draw-toolbar__actions">
          <button type="button" class="rs-btn rs-btn--ghost" @click="clearInk">{{ labels.clear }}</button>
          <button type="button" class="rs-btn rs-btn--ghost" @click="$emit('cancel')">{{ labels.cancel }}</button>
          <button type="button" class="rs-btn rs-btn--primary" :disabled="saving" @click="save">
            {{ saving ? labels.capturing : labels.save }}
          </button>
        </div>
      </div>

      <p v-if="captureFailed" class="rs-draw-overlay__warn">{{ labels.captureFailed }}</p>
    </div>
  </Teleport>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue'
import {
  capturePageScreenshot,
  compositeCanvases,
  lockPageScrollForCapture,
  unlockPageScrollForCapture,
} from '../utils/screenCapture'

const props = defineProps({
  open: { type: Boolean, default: false },
  darkMode: { type: Boolean, default: false },
  labels: { type: Object, required: true },
  /** Saved in parent before overlay opens — fixes iOS capturing top of page after scroll jump */
  scrollState: { type: Object, default: null },
})

const emit = defineEmits(['save', 'cancel'])

const bgCanvasRef = ref(null)
const drawCanvasRef = ref(null)
const tool = ref('pen')
const color = ref('#ef4444')
const strokeWidth = ref(4)
const drawing = ref(false)
const hasInk = ref(false)
const capturing = ref(false)
const saving = ref(false)
const captureFailed = ref(false)
const bgSnapshotReady = ref(false)

const colors = ['#ef4444', '#f59e0b', '#22c55e', '#3b82f6', '#ffffff']
const strokeWidths = [3, 6, 10]

let capturePromise = null

function selectColor(c) {
  color.value = c
  tool.value = 'pen'
}

function resizeCanvases() {
  const w = window.innerWidth
  const h = window.innerHeight
  for (const canvas of [bgCanvasRef.value, drawCanvasRef.value]) {
    if (!canvas) continue
    canvas.width = w
    canvas.height = h
    canvas.style.width = `${w}px`
    canvas.style.height = `${h}px`
  }
}

function paintBackground(shot) {
  const bg = bgCanvasRef.value
  const ctx = bg?.getContext('2d')
  if (!shot || !bg || !ctx) return false
  ctx.clearRect(0, 0, bg.width, bg.height)
  ctx.drawImage(shot, 0, 0, bg.width, bg.height)
  bgSnapshotReady.value = true
  return true
}

function fillPlaceholderBg() {
  const bg = bgCanvasRef.value
  const ctx = bg?.getContext('2d')
  if (!bg || !ctx) return
  ctx.fillStyle = '#1e293b'
  ctx.fillRect(0, 0, bg.width, bg.height)
}

function loadBackground() {
  capturing.value = true
  captureFailed.value = false
  bgSnapshotReady.value = false

  const run = (async () => {
    try {
      const shot = await capturePageScreenshot('.rs-ignore-capture', props.scrollState)
      if (paintBackground(shot)) return
      captureFailed.value = true
      fillPlaceholderBg()
    } catch {
      captureFailed.value = true
      fillPlaceholderBg()
    } finally {
      capturing.value = false
      if (capturePromise === run) capturePromise = null
    }
  })()

  capturePromise = run
  return run
}

async function waitForBackground() {
  if (bgSnapshotReady.value) return true
  if (capturePromise) {
    await capturePromise
    return bgSnapshotReady.value
  }
  await loadBackground()
  return bgSnapshotReady.value
}

function clearInk() {
  const canvas = drawCanvasRef.value
  const ctx = canvas?.getContext('2d')
  if (!canvas || !ctx) return
  ctx.clearRect(0, 0, canvas.width, canvas.height)
  hasInk.value = false
}

function getPoint(e) {
  const canvas = drawCanvasRef.value
  if (!canvas) return null
  const rect = canvas.getBoundingClientRect()
  return {
    x: e.clientX - rect.left,
    y: e.clientY - rect.top,
  }
}

function applyStrokeStyle(ctx) {
  if (tool.value === 'eraser') {
    ctx.globalCompositeOperation = 'destination-out'
    ctx.strokeStyle = 'rgba(0,0,0,1)'
    ctx.lineWidth = strokeWidth.value * 2
  } else {
    ctx.globalCompositeOperation = 'source-over'
    ctx.strokeStyle = color.value
    ctx.lineWidth = strokeWidth.value
  }
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
}

function onPointerDown(e) {
  if (e.pointerType === 'mouse' && e.button !== 0) return
  const canvas = drawCanvasRef.value
  const ctx = canvas?.getContext('2d')
  const p = getPoint(e)
  if (!canvas || !ctx || !p) return
  canvas.setPointerCapture(e.pointerId)
  drawing.value = true
  applyStrokeStyle(ctx)
  ctx.beginPath()
  ctx.moveTo(p.x, p.y)
}

function onPointerMove(e) {
  if (!drawing.value) return
  const ctx = drawCanvasRef.value?.getContext('2d')
  const p = getPoint(e)
  if (!ctx || !p) return
  applyStrokeStyle(ctx)
  ctx.lineTo(p.x, p.y)
  ctx.stroke()
  hasInk.value = true
}

function onPointerUp(e) {
  drawing.value = false
  const canvas = drawCanvasRef.value
  if (canvas?.hasPointerCapture?.(e.pointerId)) {
    canvas.releasePointerCapture(e.pointerId)
  }
}

async function save() {
  const bg = bgCanvasRef.value
  const ink = drawCanvasRef.value
  if (!bg || !ink) {
    emit('cancel')
    return
  }
  saving.value = true
  try {
    await waitForBackground()
  } finally {
    saving.value = false
  }
  const dataUrl = compositeCanvases(bg, ink)
  if (dataUrl) emit('save', dataUrl)
  else emit('cancel')
}

watch(
  () => props.open,
  async (active) => {
    if (!active) {
      capturePromise = null
      capturing.value = false
      unlockPageScrollForCapture()
      return
    }
    hasInk.value = false
    tool.value = 'pen'
    captureFailed.value = false
    lockPageScrollForCapture(props.scrollState)
    await nextTick()
    resizeCanvases()
    clearInk()
    fillPlaceholderBg()
    loadBackground()
  },
)
</script>

<style scoped>
.rs-draw-overlay {
  position: fixed;
  inset: 0;
  z-index: 2500;
  background: #0f172a;
  touch-action: none;
  overscroll-behavior: none;
}
.rs-draw-overlay__bg,
.rs-draw-overlay__ink {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}
.rs-draw-overlay__bg {
  z-index: 0;
}
.rs-draw-overlay__ink {
  z-index: 2;
  cursor: crosshair;
  touch-action: none;
}
.rs-draw-overlay__loading {
  position: absolute;
  inset: 0;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 23, 42, 0.5);
  pointer-events: none;
}
.rs-draw-overlay__loading-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 20px 28px;
  border-radius: 14px;
  background: rgba(15, 23, 42, 0.92);
  border: 1px solid rgba(255, 255, 255, 0.12);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.35);
}
.rs-draw-overlay__loading-card p {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: #e2e8f0;
}
.rs-draw-overlay__spinner {
  width: 32px;
  height: 32px;
  border: 3px solid rgba(255, 255, 255, 0.2);
  border-top-color: #38bdf8;
  border-radius: 50%;
  animation: rs-draw-spin 0.75s linear infinite;
}
@keyframes rs-draw-spin {
  to {
    transform: rotate(360deg);
  }
}
.rs-draw-toolbar {
  position: fixed;
  left: 50%;
  bottom: 20px;
  transform: translateX(-50%);
  z-index: 3;
  display: flex;
  flex-direction: column;
  gap: 10px;
  align-items: center;
  width: calc(100vw - 24px);
}
.rs-draw-toolbar__tools,
.rs-draw-toolbar__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  background: rgba(15, 23, 42, 0.92);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 14px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.35);
  backdrop-filter: blur(8px);
}
.rs-draw-toolbar__sep {
  width: 1px;
  height: 22px;
  background: rgba(255, 255, 255, 0.15);
  margin: 0 4px;
}
.rs-draw-tool {
  width: 36px;
  height: 36px;
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.06);
  color: #f8fafc;
  cursor: pointer;
  font-size: 16px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.rs-draw-tool--active {
  border-color: #38bdf8;
  background: rgba(56, 189, 248, 0.2);
}
.rs-draw-tool--color {
  --swatch: #ef4444;
  background: var(--swatch);
  border-color: rgba(255, 255, 255, 0.35);
}
.rs-draw-tool--color.rs-draw-tool--active {
  box-shadow: 0 0 0 2px #38bdf8;
}
.rs-draw-dot {
  display: block;
  border-radius: 50%;
  background: #f8fafc;
}
.rs-draw-overlay__warn {
  position: fixed;
  top: 16px;
  left: 50%;
  transform: translateX(-50%);
  margin: 0;
  padding: 8px 14px;
  border-radius: 8px;
  background: rgba(251, 191, 36, 0.15);
  color: #fde68a;
  font-size: 13px;
  z-index: 4;
}
.rs-btn {
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 10px;
  padding: 8px 14px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  background: rgba(255, 255, 255, 0.08);
  color: #f8fafc;
}
.rs-btn--ghost:hover {
  background: rgba(255, 255, 255, 0.14);
}
.rs-btn--primary {
  background: #5865f2;
  border-color: transparent;
  color: #fff;
}
.rs-btn--primary:disabled {
  opacity: 0.6;
  cursor: wait;
}
</style>
