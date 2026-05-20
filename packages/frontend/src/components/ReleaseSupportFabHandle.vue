<template>
  <div
    class="rs-fab-dock rs-root rs-ignore-capture"
    :class="[
      ...dockClasses,
      { 'rs-fab-dock--dark': darkMode, 'rs-root--dark': darkMode },
    ]"
    :style="dockStyle"
    @mouseenter="onHoverEnter"
    @mouseleave="onHoverLeave"
  >
    <button
      type="button"
      class="rs-fab-handle"
      :aria-label="label"
      @pointerdown="onPointerDown"
      @pointermove="onPointerMove"
      @pointerup="(e) => onPointerUp(e, () => $emit('open'))"
      @pointercancel="onPointerCancel"
    >
      <span class="rs-fab-handle__accent" aria-hidden="true" />
      <span class="rs-fab-handle__icon" aria-hidden="true">
        <svg class="rs-fab-handle__chev rs-fab-handle__chev--a" viewBox="0 0 16 16" fill="none">
          <path d="M10 3L5 8L10 13" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <svg class="rs-fab-handle__chev rs-fab-handle__chev--b" viewBox="0 0 16 16" fill="none">
          <path d="M10 3L5 8L10 13" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </span>
      <span class="rs-fab-handle__label">{{ label }}</span>
    </button>
  </div>
</template>

<script setup>
import { useReleaseSupportFabDock } from '../composables/useReleaseSupportFabDock'

defineProps({
  label: { type: String, default: 'Report' },
  darkMode: { type: Boolean, default: false },
})

defineEmits(['open'])

const {
  dockClasses,
  dockStyle,
  onPointerDown,
  onPointerMove,
  onPointerUp,
  onPointerCancel,
  onHoverEnter,
  onHoverLeave,
} = useReleaseSupportFabDock()
</script>

<script>
export default {
  name: 'ReleaseSupportFabHandle',
}
</script>
