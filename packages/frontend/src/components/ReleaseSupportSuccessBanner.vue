<template>
  <Teleport :to="teleportTarget">
    <div
      v-if="open"
      class="rs-success-banner rs-ignore-capture"
      :class="{ 'rs-root--dark': darkMode }"
      role="status"
    >
      <span class="rs-success-banner__icon" aria-hidden="true">✓</span>
      <p class="rs-success-banner__text">{{ message }}</p>
      <button type="button" class="rs-icon-btn rs-success-banner__close" :aria-label="dismissLabel" @click="$emit('close')">
        ×
      </button>
    </div>
  </Teleport>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  message: { type: String, default: '' },
  darkMode: { type: Boolean, default: false },
  target: { type: String, default: 'body' },
  dismissLabel: { type: String, default: 'Close' },
  autoCloseMs: { type: Number, default: 0 },
})

const emit = defineEmits(['close'])

const teleportTarget = ref('body')

function resolveTarget() {
  const sel = String(props.target || 'body').trim()
  if (!sel || sel === 'body') {
    teleportTarget.value = 'body'
    return
  }
  try {
    const el = document.querySelector(sel)
    teleportTarget.value = el || 'body'
  } catch {
    teleportTarget.value = 'body'
  }
}

onMounted(resolveTarget)
watch(() => props.target, resolveTarget)

let timer = null
watch(
  () => props.open,
  (isOpen) => {
    if (timer) clearTimeout(timer)
    if (!isOpen || !props.autoCloseMs) return
    timer = setTimeout(() => emit('close'), props.autoCloseMs)
  },
)
</script>
