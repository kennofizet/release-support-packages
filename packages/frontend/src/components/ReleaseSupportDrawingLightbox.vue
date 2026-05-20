<template>
  <Teleport to="body">
    <div
      v-if="open && currentSrc"
      class="rs-drawing-lightbox rs-root rs-ignore-capture"
      :class="{ 'rs-drawing-lightbox--dark': darkMode, 'rs-root--dark': darkMode }"
      role="dialog"
      aria-modal="true"
      :aria-label="title"
      @click.self="$emit('close')"
    >
      <button type="button" class="rs-drawing-lightbox__close" :aria-label="closeLabel" @click="$emit('close')">
        &times;
      </button>

      <button
        v-if="hasPrev"
        type="button"
        class="rs-drawing-lightbox__nav rs-drawing-lightbox__nav--prev"
        :aria-label="prevLabel"
        @click="$emit('prev')"
      >
        &#8249;
      </button>

      <div class="rs-drawing-lightbox__stage">
        <ReleaseSupportDrawingImg :key="currentSrc" :src="currentSrc" :alt="alt" full-size />
        <p v-if="counterText" class="rs-drawing-lightbox__counter">{{ counterText }}</p>
      </div>

      <button
        v-if="hasNext"
        type="button"
        class="rs-drawing-lightbox__nav rs-drawing-lightbox__nav--next"
        :aria-label="nextLabel"
        @click="$emit('next')"
      >
        &#8250;
      </button>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onUnmounted, watch } from 'vue'
import ReleaseSupportDrawingImg from './ReleaseSupportDrawingImg.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  sources: { type: Array, default: () => [] },
  index: { type: Number, default: 0 },
  darkMode: { type: Boolean, default: false },
  alt: { type: String, default: '' },
  title: { type: String, default: '' },
  closeLabel: { type: String, default: 'Close' },
  prevLabel: { type: String, default: 'Previous' },
  nextLabel: { type: String, default: 'Next' },
})

const emit = defineEmits(['close', 'prev', 'next'])

const currentSrc = computed(() => {
  const list = props.sources || []
  if (!list.length) return ''
  const i = Math.min(Math.max(0, props.index), list.length - 1)
  return String(list[i] || '')
})

const hasPrev = computed(() => props.index > 0)
const hasNext = computed(() => props.index < (props.sources?.length || 0) - 1)

const counterText = computed(() => {
  const total = props.sources?.length || 0
  if (total <= 1) return ''
  return `${props.index + 1} / ${total}`
})

function onKeydown(e) {
  if (!props.open) return
  if (e.key === 'Escape') emit('close')
  else if (e.key === 'ArrowLeft' && hasPrev.value) emit('prev')
  else if (e.key === 'ArrowRight' && hasNext.value) emit('next')
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) window.addEventListener('keydown', onKeydown)
    else window.removeEventListener('keydown', onKeydown)
  },
)

onUnmounted(() => window.removeEventListener('keydown', onKeydown))
</script>

<script>
export default {
  name: 'ReleaseSupportDrawingLightbox',
}
</script>
