<template>
  <div class="rs-dev-ops">
    <div class="rs-dev-ops__tabs" role="tablist">
      <button
        type="button"
        role="tab"
        class="rs-dev-ops__tab"
        :class="{ 'rs-dev-ops__tab--active': tab === 'reports' }"
        :aria-selected="tab === 'reports'"
        @click="$emit('update:tab', 'reports')"
      >
        {{ labels.devTabReports }}
      </button>
      <button
        type="button"
        role="tab"
        class="rs-dev-ops__tab"
        :class="{ 'rs-dev-ops__tab--active': tab === 'metrics' }"
        :aria-selected="tab === 'metrics'"
        @click="$emit('update:tab', 'metrics')"
      >
        {{ labels.metrics }}
      </button>
      <button
        type="button"
        role="tab"
        class="rs-dev-ops__tab"
        :class="{ 'rs-dev-ops__tab--active': tab === 'versions' }"
        :aria-selected="tab === 'versions'"
        @click="$emit('update:tab', 'versions')"
      >
        {{ labels.devTabVersions }}
      </button>
    </div>

    <section v-if="tab === 'metrics'" class="rs-dev-ops__panel">
      <div v-if="metricsLoading" class="rs-dev-ops__loading">{{ labels.loading }}</div>
      <template v-else-if="metrics">
        <div class="rs-metrics-grid">
          <article class="rs-metrics-card">
            <span class="rs-metrics-card__label">{{ labels.metricsOpen }}</span>
            <strong class="rs-metrics-card__value">{{ metrics.open_count ?? 0 }}</strong>
          </article>
          <article class="rs-metrics-card">
            <span class="rs-metrics-card__label">{{ labels.metricsMedian }}</span>
            <strong class="rs-metrics-card__value">
              {{ metrics.median_hours_to_resolved != null ? metrics.median_hours_to_resolved : '—' }}
            </strong>
          </article>
        </div>
        <h4 class="rs-dev-ops__subhead">{{ labels.metricsPerDay }}</h4>
        <ul v-if="(metrics.reports_per_day || []).length" class="rs-metrics-bars">
          <li v-for="row in metrics.reports_per_day" :key="row.date" class="rs-metrics-bars__row">
            <span class="rs-metrics-bars__date">{{ row.date }}</span>
            <div class="rs-metrics-bars__track">
              <div class="rs-metrics-bars__fill" :style="{ width: barWidth(row.count) }" />
            </div>
            <span class="rs-metrics-bars__count">{{ row.count }}</span>
          </li>
        </ul>
        <p v-else class="rs-dev-ops__empty">{{ labels.metricsNoData }}</p>
      </template>
      <p v-else class="rs-dev-ops__empty">{{ labels.metricsNoData }}</p>
    </section>

    <section v-else-if="tab === 'versions'" class="rs-dev-ops__panel rs-dev-ops__panel--versions">
      <!-- Full-page version detail -->
      <div v-if="versionView === 'detail'" class="rs-version-detail-page">
        <button type="button" class="rs-btn rs-btn--ghost rs-btn--sm rs-version-detail-page__back" @click="closeVersionDetail">
          ← {{ labels.versionsBackToList }}
        </button>

        <div v-if="versionDetailLoading" class="rs-version-detail-page__card rs-version-detail-page__card--state">
          <span class="rs-loading__spinner rs-version-detail-page__spinner" aria-hidden="true" />
          <p>{{ labels.loading }}</p>
        </div>

        <article v-else-if="selectedVersion" class="rs-version-detail-page__card">
          <header class="rs-version-detail-page__hero">
            <div class="rs-version-detail-page__badge" aria-hidden="true">v{{ selectedVersion.version }}</div>
            <div class="rs-version-detail-page__hero-main">
              <p class="rs-version-detail-page__eyebrow">{{ labels.versionsDetailTitle }}</p>
              <h2 class="rs-version-detail-page__title">
                {{ selectedVersion.title || `Release v${selectedVersion.version}` }}
              </h2>
              <p v-if="selectedVersion.created_at" class="rs-version-detail-page__meta">
                <time :datetime="selectedVersion.created_at">{{ formatDateLong(selectedVersion.created_at) }}</time>
              </p>
              <div class="rs-version-detail-page__badges">
                <span v-if="selectedVersion.is_active" class="rs-badge rs-badge--ok">{{ labels.versionsActive }}</span>
                <span v-else class="rs-badge">{{ labels.versionsInactive }}</span>
                <span v-if="selectedVersion.is_force" class="rs-badge rs-badge--warn">{{ labels.versionsForce }}</span>
                <span class="rs-merge-count">{{ selectedVersion.merges_count }} {{ labels.releaseMergedLabel }}</span>
              </div>
            </div>
          </header>

          <section class="rs-version-detail-page__section">
            <h3 class="rs-version-detail-page__section-title">{{ labels.versionsContent }}</h3>
            <div v-if="selectedVersion.content" class="rs-version-detail-page__body">{{ selectedVersion.content }}</div>
            <p v-else class="rs-version-detail-page__empty">{{ labels.userUpdatesNoNotes }}</p>
          </section>

          <section class="rs-version-detail-page__section">
            <h3 class="rs-version-detail-page__section-title">
              {{ labels.releaseMergedHead }}
              <span class="rs-version-detail-page__section-count">{{ selectedVersion.merges_count }}</span>
            </h3>
            <ul v-if="(selectedVersion.merges || []).length" class="rs-merge-queue rs-merge-queue--detail">
              <li v-for="m in selectedVersion.merges" :key="'m-' + m.id" class="rs-merge-pr">
                <span class="rs-merge-pr__icon" aria-hidden="true">✓</span>
                <div class="rs-merge-pr__body">
                  <span class="rs-merge-pr__title">
                    #{{ m.id }} {{ m.title }}
                    <span v-if="m.user_name" class="rs-merge-pr__by">— {{ m.user_name }}</span>
                  </span>
                  <span class="rs-merge-pr__meta">{{ m.tag }} · {{ m.status }}</span>
                </div>
              </li>
            </ul>
            <p v-else class="rs-version-detail-page__empty">{{ labels.releaseNoMerges }}</p>
          </section>

          <footer class="rs-version-detail-page__footer">
            <form
              v-if="editingId === selectedVersion.id"
              class="rs-version-form rs-version-form--edit"
              @submit.prevent="submitEdit(selectedVersion.id)"
            >
              <h3 class="rs-version-detail-page__section-title">{{ labels.versionsEdit }}</h3>
              <label class="rs-field rs-version-form__field">
                <span class="rs-field__label">{{ labels.versionsTitle }}</span>
                <input v-model="editForm.title" class="rs-input" type="text" />
              </label>
              <label class="rs-field rs-version-form__field">
                <span class="rs-field__label">{{ labels.versionsContent }}</span>
                <textarea v-model="editForm.content" class="rs-textarea" rows="8" />
              </label>
              <div class="rs-version-form__flags">
                <label class="rs-check">
                  <input v-model="editForm.is_active" type="checkbox" />
                  {{ labels.versionsActive }}
                </label>
                <label class="rs-check">
                  <input v-model="editForm.is_force" type="checkbox" />
                  {{ labels.versionsForce }}
                </label>
              </div>
              <div class="rs-version-form__actions">
                <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm" :disabled="versionSaving">
                  {{ labels.versionsSave }}
                </button>
                <button type="button" class="rs-btn rs-btn--ghost rs-btn--sm" @click="cancelEdit">
                  {{ labels.cancel }}
                </button>
              </div>
            </form>
            <button
              v-else
              type="button"
              class="rs-btn rs-btn--sm rs-version-detail-page__edit"
              @click="startEdit(selectedVersion)"
            >
              {{ labels.versionsEdit }}
            </button>
          </footer>
        </article>

        <p v-else class="rs-dev-ops__empty">{{ labels.versionsEmpty }}</p>
      </div>

      <!-- Split: create (9/12) + list (3/12) -->
      <div v-else class="rs-release-layout rs-release-layout--split">
        <div class="rs-release-layout__create">
          <div v-if="releasePreviewLoading" class="rs-dev-ops__loading">{{ labels.loading }}</div>
          <template v-else-if="releasePreview">
            <h4 class="rs-dev-ops__subhead">{{ labels.versionsPublish }}</h4>
            <p class="rs-release-next">
              {{ labels.releaseNextVersion }}:
              <strong>v{{ releasePreview.next_version }}</strong>
              <span class="rs-release-next__meta">
                ({{ selectedMergeIds.length }} / {{ releasePreview.waiting_merge_count }}
                {{ labels.releaseWaitingLabel }})
              </span>
              <span v-if="releasePreview.can_create" class="rs-release-next__tools">
                <button type="button" class="rs-btn rs-btn--ghost rs-btn--sm" @click="selectAllWaiting">
                  {{ labels.releaseSelectAll }}
                </button>
                <button type="button" class="rs-btn rs-btn--ghost rs-btn--sm" @click="clearMergeSelection">
                  {{ labels.releaseSelectNone }}
                </button>
              </span>
            </p>

            <div v-if="!(releasePreview.blockers || []).length" class="rs-release-ready">
              {{ labels.releaseReady }}
            </div>
            <div v-else>
              <p class="rs-release-blockers-intro">{{ labels.releaseBlockersIntro }}</p>
              <ul class="rs-release-blockers">
                <li v-for="(code, i) in releasePreview.blockers" :key="'b-' + i">{{ blockerLabel(code) }}</li>
              </ul>
            </div>

            <p v-if="releasePreview.waiting_reports_truncated" class="rs-dev-ops__hint">
              {{ labels.releaseWaitingTruncated }}
            </p>
            <ul v-if="(releasePreview.waiting_reports || []).length" class="rs-merge-queue">
              <li
                v-for="r in releasePreview.waiting_reports"
                :key="'w-' + r.id"
                class="rs-merge-pr"
                :class="{ 'rs-merge-pr--selected': isMergeSelected(r.id) }"
              >
                <label class="rs-merge-pr__check">
                  <input
                    type="checkbox"
                    :checked="isMergeSelected(r.id)"
                    :disabled="!releasePreview.can_create"
                    @change="toggleMergeReport(r.id, $event.target.checked)"
                  />
                  <span class="rs-sr-only">{{ labels.releaseSelectForMerge }} #{{ r.id }}</span>
                </label>
                <span class="rs-merge-pr__icon" aria-hidden="true">⎇</span>
                <div class="rs-merge-pr__body">
                  <span class="rs-merge-pr__title">#{{ r.id }} {{ r.title }}</span>
                  <span class="rs-merge-pr__meta">{{ r.tag }} · {{ r.status }}</span>
                </div>
              </li>
            </ul>

            <form class="rs-version-form" @submit.prevent="submitRelease">
              <p v-if="releasePreview.can_create && !selectedMergeIds.length" class="rs-release-hint">
                {{ labels.releaseSelectAtLeastOne }}
              </p>
              <label class="rs-field rs-version-form__field">
                <span class="rs-field__label">{{ labels.versionsTitle }}</span>
                <input v-model="createForm.title" class="rs-input" type="text" />
              </label>
              <label class="rs-field rs-version-form__field">
                <span class="rs-field__label">{{ labels.versionsContent }}</span>
                <textarea v-model="createForm.content" class="rs-textarea" rows="6" />
              </label>
              <div class="rs-version-form__flags">
                <label class="rs-check">
                  <input v-model="createForm.is_active" type="checkbox" />
                  {{ labels.versionsActive }}
                </label>
                <label class="rs-check">
                  <input v-model="createForm.is_force" type="checkbox" />
                  {{ labels.versionsForce }}
                </label>
              </div>
              <button
                type="submit"
                class="rs-btn rs-btn--primary rs-btn--sm"
                :disabled="versionSaving || !canSubmitRelease"
              >
                {{ versionSaving ? labels.submitting : labels.versionsCreateMerge }}
              </button>
            </form>
          </template>
        </div>

        <aside class="rs-release-layout__list">
          <h4 class="rs-release-sidebar__title">{{ labels.versionsList }}</h4>
          <div v-if="versionsLoading" class="rs-dev-ops__loading">{{ labels.loading }}</div>
          <p v-else-if="!versionItems.length" class="rs-dev-ops__empty">{{ labels.versionsEmpty }}</p>
          <ul v-else class="rs-version-list rs-version-list--sidebar">
            <li
              v-for="item in versionItems"
              :key="item.id"
              class="rs-version-item rs-version-item--compact"
              :class="{ 'rs-version-item--selected': selectedVersionId === item.id && versionView === 'detail' }"
            >
              <div class="rs-version-item__compact-body">
                <strong class="rs-version-item__ver">v{{ item.version }}</strong>
                <p v-if="item.title" class="rs-version-item__title">{{ item.title }}</p>
                <p class="rs-version-item__meta">
                  <span>{{ item.merges_count }} {{ labels.releaseMergedLabel }}</span>
                  <span v-if="item.is_active" class="rs-badge rs-badge--ok rs-badge--xs">{{ labels.versionsActive }}</span>
                </p>
              </div>
              <button type="button" class="rs-btn rs-btn--sm rs-version-item__view" @click="openVersion(item.id)">
                {{ labels.versionsView }}
              </button>
            </li>
          </ul>
          <ReleaseSupportPagination
            :page="versionPagination.current_page"
            :last-page="versionPagination.last_page"
            :total="versionPagination.total"
            :per-page="versionPagination.per_page"
            :loading="versionsLoading"
            :labels="labels"
            @update:page="$emit('update:versions-page', $event)"
          />
        </aside>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { buildReleaseNotesFromRows, buildReleaseTitleFromVersion } from '../utils/releaseNotes'
import ReleaseSupportPagination from './ReleaseSupportPagination.vue'

const props = defineProps({
  tab: { type: String, default: 'reports' },
  labels: { type: Object, required: true },
  metrics: { type: Object, default: null },
  metricsLoading: { type: Boolean, default: false },
  releasePreview: { type: Object, default: null },
  releasePreviewLoading: { type: Boolean, default: false },
  versionItems: { type: Array, default: () => [] },
  versionsLoading: { type: Boolean, default: false },
  versionSaving: { type: Boolean, default: false },
  selectedVersion: { type: Object, default: null },
  versionDetailLoading: { type: Boolean, default: false },
  createVersionRelease: { type: Function, required: true },
  updateVersion: { type: Function, required: true },
  loadVersionDetail: { type: Function, required: true },
  versionPagination: {
    type: Object,
    default: () => ({ current_page: 1, last_page: 1, per_page: 20, total: 0 }),
  },
})

const emit = defineEmits(['update:tab', 'update:versions-page'])

const versionView = ref('split')
const selectedVersionId = ref(null)
const editingId = ref(null)
const selectedMergeIds = ref([])
const mergeSelectionTouched = ref(false)
const createForm = reactive({
  title: '',
  content: '',
  is_active: true,
  is_force: false,
})
const editForm = reactive({
  title: '',
  content: '',
  is_active: true,
  is_force: false,
})

const maxDayCount = computed(() => {
  const rows = props.metrics?.reports_per_day || []
  return Math.max(1, ...rows.map((r) => Number(r.count) || 0))
})

const selectedMergeRows = computed(() => {
  const ids = new Set(selectedMergeIds.value.map((id) => Number(id)))
  const fromPreview = (props.releasePreview?.waiting_reports || []).filter((r) => ids.has(Number(r.id)))
  if (fromPreview.length === ids.size) return fromPreview
  return fromPreview
})

const canSubmitRelease = computed(
  () => !!props.releasePreview?.can_create && selectedMergeIds.value.length > 0,
)

function syncCreateFormFromSelection() {
  const preview = props.releasePreview
  if (!preview) return
  const rows = selectedMergeRows.value
  const count = selectedMergeIds.value.length
  if (!mergeSelectionTouched.value || count === 0) {
    createForm.title = preview.suggested_title || ''
    createForm.content = preview.suggested_content || ''
    return
  }
  createForm.title = buildReleaseTitleFromVersion(preview.next_version || '', count)
  createForm.content = buildReleaseNotesFromRows(rows)
}

function resetMergeSelection(preview) {
  mergeSelectionTouched.value = false
  const ids = Array.isArray(preview?.waiting_report_ids) ? preview.waiting_report_ids : []
  if (ids.length) {
    selectedMergeIds.value = ids.map((id) => Number(id)).filter((id) => id > 0)
    return
  }
  selectedMergeIds.value = (preview?.waiting_reports || [])
    .map((r) => Number(r.id))
    .filter((id) => id > 0)
}

watch(
  () => props.releasePreview,
  (preview) => {
    if (!preview) {
      selectedMergeIds.value = []
      return
    }
    resetMergeSelection(preview)
    syncCreateFormFromSelection()
  },
  { immediate: true },
)

watch(selectedMergeIds, () => {
  syncCreateFormFromSelection()
}, { deep: true })

function isMergeSelected(id) {
  return selectedMergeIds.value.includes(Number(id))
}

function toggleMergeReport(id, checked) {
  mergeSelectionTouched.value = true
  const numId = Number(id)
  if (checked) {
    if (!selectedMergeIds.value.includes(numId)) {
      selectedMergeIds.value = [...selectedMergeIds.value, numId]
    }
  } else {
    selectedMergeIds.value = selectedMergeIds.value.filter((x) => x !== numId)
  }
}

function selectAllWaiting() {
  mergeSelectionTouched.value = true
  const preview = props.releasePreview
  const ids = Array.isArray(preview?.waiting_report_ids) ? preview.waiting_report_ids : []
  selectedMergeIds.value = ids.length
    ? ids.map((id) => Number(id)).filter((id) => id > 0)
    : (preview?.waiting_reports || []).map((r) => Number(r.id)).filter((id) => id > 0)
}

function clearMergeSelection() {
  mergeSelectionTouched.value = true
  selectedMergeIds.value = []
}

watch(
  () => props.tab,
  (t) => {
    if (t !== 'versions') {
      versionView.value = 'split'
      selectedVersionId.value = null
      editingId.value = null
    }
  },
)

function formatDate(value) {
  if (!value) return ''
  try {
    const d = new Date(value)
    if (Number.isNaN(d.getTime())) return String(value)
    return d.toLocaleString()
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

const BLOCKER_LEGACY_NO_WAITING = 'No resolved reports are waiting to merge into a release.'

function blockerLabel(code) {
  if (code === 'no_waiting_reports' || code === BLOCKER_LEGACY_NO_WAITING) {
    return props.labels.releaseBlockerNoWaiting
  }
  return String(code ?? '')
}

function closeVersionDetail() {
  versionView.value = 'split'
  editingId.value = null
}

function barWidth(count) {
  const n = Number(count) || 0
  return `${Math.round((n / maxDayCount.value) * 100)}%`
}

function releasePayload(form) {
  return {
    report_ids: [...selectedMergeIds.value],
    title: String(form.title || '').trim(),
    content: String(form.content || '').trim(),
    is_active: !!form.is_active,
    is_force: !!form.is_force,
  }
}

async function submitRelease() {
  if (!canSubmitRelease.value) return
  const ok = await props.createVersionRelease(releasePayload(createForm))
  if (ok) {
    editingId.value = null
    mergeSelectionTouched.value = false
    const newId = props.selectedVersion?.id
    if (newId) {
      selectedVersionId.value = Number(newId)
      versionView.value = 'detail'
    }
  }
}

async function openVersion(id) {
  selectedVersionId.value = id
  editingId.value = null
  versionView.value = 'detail'
  await props.loadVersionDetail(id)
}

function startEdit(item) {
  editingId.value = item.id
  editForm.title = item.title
  editForm.content = item.content
  editForm.is_active = item.is_active
  editForm.is_force = item.is_force
}

function cancelEdit() {
  editingId.value = null
}

async function submitEdit(id) {
  const ok = await props.updateVersion(id, releasePayload(editForm))
  if (ok) editingId.value = null
}
</script>

<script>
export default {
  name: 'ReleaseSupportDevOpsPanel',
}
</script>
