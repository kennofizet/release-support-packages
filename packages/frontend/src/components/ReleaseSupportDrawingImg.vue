<template>
  <button
    v-if="displaySrc && clickable"
    type="button"
    class="rs-drawing-img-btn"
    :aria-label="viewLabel || alt"
    @click="$emit('open')"
  >
    <img :src="displaySrc" :alt="alt" class="rs-drawing-img" :class="{ 'rs-drawing-img--full': fullSize }" loading="lazy" />
  </button>
  <img
    v-else-if="displaySrc"
    :src="displaySrc"
    :alt="alt"
    class="rs-drawing-img"
    :class="{ 'rs-drawing-img--full': fullSize }"
    loading="lazy"
  />
</template>

<script setup>
import { inject, onMounted, onUnmounted, ref, watch } from 'vue'
import { isInlineDrawingSrc, parseApiDrawingSrc } from '../utils/drawingUrl'

const props = defineProps({
  src: { type: String, required: true },
  alt: { type: String, default: '' },
  clickable: { type: Boolean, default: false },
  fullSize: { type: Boolean, default: false },
  viewLabel: { type: String, default: '' },
})

defineEmits(['open'])

const releaseSupportApi = inject('releaseSupportApi', null)
const displaySrc = ref('')
let objectUrl = ''

function revokeObjectUrl() {
  if (objectUrl) {
    URL.revokeObjectURL(objectUrl)
    objectUrl = ''
  }
}

async function load() {
  revokeObjectUrl()
  const src = props.src
  if (!src) {
    displaySrc.value = ''
    return
  }

  if (isInlineDrawingSrc(src)) {
    displaySrc.value = src
    return
  }

  const parsed = parseApiDrawingSrc(src)
  if (parsed && releaseSupportApi && typeof releaseSupportApi.fetchDrawing === 'function') {
    try {
      const blob = await releaseSupportApi.fetchDrawing(parsed.reportId, parsed.filename)
      if (blob && blob.size > 0) {
        objectUrl = URL.createObjectURL(blob)
        displaySrc.value = objectUrl
        return
      }
    } catch (e) {
      console.error('Load report drawing failed', e)
    }
    displaySrc.value = ''
    return
  }

  // Public disk /storage/... or other direct URL
  displaySrc.value = src
}

onMounted(load)
watch(() => props.src, load)
onUnmounted(revokeObjectUrl)
</script>

<script>
export default {
  name: 'ReleaseSupportDrawingImg',
}
</script>
