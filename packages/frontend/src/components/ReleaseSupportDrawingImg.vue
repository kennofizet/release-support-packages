<template>
  <img v-if="displaySrc" :src="displaySrc" :alt="alt" class="rs-drawing-img" loading="lazy" />
</template>

<script setup>
import { inject, onMounted, onUnmounted, ref, watch } from 'vue'
import { isInlineDrawingSrc, parseApiDrawingSrc } from '../utils/drawingUrl'

const props = defineProps({
  src: { type: String, required: true },
  alt: { type: String, default: '' },
})

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
