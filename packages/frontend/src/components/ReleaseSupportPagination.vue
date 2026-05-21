<template>
  <nav
    v-if="showPagination"
    class="rs-pagination"
    :aria-label="labels.paginationAria"
  >
    <p class="rs-pagination__summary">
      {{ summaryText }}
    </p>
    <div class="rs-pagination__controls">
      <button
        type="button"
        class="rs-btn rs-btn--ghost rs-btn--sm"
        :disabled="disabled || page <= 1"
        @click="go(page - 1)"
      >
        {{ labels.paginationPrev }}
      </button>
      <span class="rs-pagination__pages">
        {{ labels.paginationPage }} {{ page }} / {{ lastPage }}
      </span>
      <button
        type="button"
        class="rs-btn rs-btn--ghost rs-btn--sm"
        :disabled="disabled || page >= lastPage"
        @click="go(page + 1)"
      >
        {{ labels.paginationNext }}
      </button>
    </div>
  </nav>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  page: { type: Number, default: 1 },
  lastPage: { type: Number, default: 1 },
  total: { type: Number, default: 0 },
  perPage: { type: Number, default: 20 },
  loading: { type: Boolean, default: false },
  labels: { type: Object, required: true },
})

const emit = defineEmits(['update:page'])

const disabled = computed(() => props.loading)

const showPagination = computed(() => props.total > 0 && props.lastPage > 1)

const summaryText = computed(() => {
  const start = props.total === 0 ? 0 : (props.page - 1) * props.perPage + 1
  const end = Math.min(props.page * props.perPage, props.total)
  return props.labels.paginationRange
    .replace('{start}', String(start))
    .replace('{end}', String(end))
    .replace('{total}', String(props.total))
})

function go(next) {
  const p = Math.max(1, Math.min(props.lastPage, next))
  if (p !== props.page) emit('update:page', p)
}
</script>

<script>
export default {
  name: 'ReleaseSupportPagination',
}
</script>
