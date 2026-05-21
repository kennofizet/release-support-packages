<template>
  <div class="rs-user-updates">
    <div v-if="updatesView === 'detail'" class="rs-user-version-detail">
      <button type="button" class="rs-user-version-detail__back rs-btn rs-btn--ghost rs-btn--sm" @click="closeDetail">
        ← {{ labels.back }}
      </button>

      <div v-if="detailLoading" class="rs-user-version-detail__card rs-user-version-detail__card--state">
        <span class="rs-loading__spinner rs-user-version-detail__spinner" aria-hidden="true" />
        <p>{{ labels.loading }}</p>
      </div>

      <article v-else-if="selected" class="rs-user-version-detail__card">
        <header class="rs-user-version-detail__hero">
          <div class="rs-user-version-detail__badge" aria-hidden="true">v{{ selected.version }}</div>
          <div class="rs-user-version-detail__hero-text">
            <p class="rs-user-version-detail__eyebrow">{{ labels.userTabUpdates }}</p>
            <h2 class="rs-user-version-detail__title">{{ selected.title || labels.userUpdateUntitled }}</h2>
            <p v-if="selected.created_at" class="rs-user-version-detail__meta">
              <time :datetime="selected.created_at">{{ formatDateLong(selected.created_at) }}</time>
            </p>
          </div>
        </header>

        <section class="rs-user-version-detail__section">
          <h3 class="rs-user-version-detail__section-title">{{ labels.versionsContent }}</h3>
          <div v-if="selected.content" class="rs-user-version-detail__body">{{ selected.content }}</div>
          <p v-else class="rs-user-version-detail__empty">{{ labels.userUpdatesNoNotes }}</p>
        </section>
      </article>
    </div>

    <template v-else>
      <div v-if="loading" class="rs-dev-ops__loading">{{ labels.loading }}</div>
      <p v-else-if="!items.length" class="rs-user-updates__empty">{{ labels.userUpdatesEmpty }}</p>
      <ul v-else class="rs-user-updates__list">
        <li v-for="item in items" :key="item.id" class="rs-user-update-card">
          <div class="rs-user-update-card__main">
            <span class="rs-user-update-card__ver">v{{ item.version }}</span>
            <h3 class="rs-user-update-card__title">{{ item.title || labels.userUpdateUntitled }}</h3>
            <p v-if="item.excerpt" class="rs-user-update-card__excerpt">{{ item.excerpt }}</p>
            <p v-if="item.created_at" class="rs-user-update-card__date">{{ formatDate(item.created_at) }}</p>
          </div>
          <button type="button" class="rs-btn rs-btn--primary rs-btn--sm" @click="openDetail(item.id)">
            {{ labels.userUpdatesRead }}
          </button>
        </li>
      </ul>
      <ReleaseSupportPagination
        :page="pagination.current_page"
        :last-page="pagination.last_page"
        :total="pagination.total"
        :per-page="pagination.per_page"
        :loading="loading"
        :labels="labels"
        @update:page="$emit('update:page', $event)"
      />
    </template>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import ReleaseSupportPagination from './ReleaseSupportPagination.vue'

const props = defineProps({
  labels: { type: Object, required: true },
  items: { type: Array, default: () => [] },
  pagination: {
    type: Object,
    default: () => ({ current_page: 1, last_page: 1, per_page: 20, total: 0 }),
  },
  loading: { type: Boolean, default: false },
  detailLoading: { type: Boolean, default: false },
  selected: { type: Object, default: null },
  loadDetail: { type: Function, required: true },
})

const emit = defineEmits(['update:page'])

const updatesView = ref('list')

async function openDetail(id) {
  updatesView.value = 'detail'
  await props.loadDetail(id)
}

function closeDetail() {
  updatesView.value = 'list'
}

function formatDate(value) {
  if (!value) return ''
  try {
    const d = new Date(value)
    if (Number.isNaN(d.getTime())) return String(value)
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
  } catch {
    return String(value)
  }
}

function formatDateLong(value) {
  if (!value) return ''
  try {
    const d = new Date(value)
    if (Number.isNaN(d.getTime())) return String(value)
    return d.toLocaleString(undefined, {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return String(value)
  }
}
</script>

<script>
export default {
  name: 'ReleaseSupportUserUpdates',
}
</script>
