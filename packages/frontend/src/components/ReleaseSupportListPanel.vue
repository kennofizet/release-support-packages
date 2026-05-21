<template>
  <div class="rs-list-panel" :class="{ 'rs-list-panel--dark': darkMode, 'rs-root--dark': darkMode }">
    <div v-if="view === 'list'" class="rs-list-layout">
      <nav v-if="mode === 'user'" class="rs-user-hub__tabs" role="tablist">
        <button
          type="button"
          role="tab"
          class="rs-user-hub__tab"
          :class="{ 'rs-user-hub__tab--active': userTab === 'reports' }"
          :aria-selected="userTab === 'reports'"
          @click="$emit('update:user-tab', 'reports')"
        >
          {{ labels.userTabReports }}
        </button>
        <button
          type="button"
          role="tab"
          class="rs-user-hub__tab"
          :class="{ 'rs-user-hub__tab--active': userTab === 'updates' }"
          :aria-selected="userTab === 'updates'"
          @click="$emit('update:user-tab', 'updates')"
        >
          {{ labels.userTabUpdates }}
        </button>
      </nav>

      <ReleaseSupportDevOpsPanel
        v-if="mode === 'dev'"
        :tab="devTab"
        :labels="labels"
        :metrics="devMetrics"
        :metrics-loading="devMetricsLoading"
        :release-preview="releasePreview"
        :release-preview-loading="releasePreviewLoading"
        :version-items="versionUpdates"
        :versions-loading="versionUpdatesLoading"
        :version-saving="versionSaving"
        :selected-version="selectedVersion"
        :version-detail-loading="versionDetailLoading"
        :create-version-release="createVersionRelease"
        :update-version="updateVersion"
        :load-version-detail="loadVersionDetail"
        :version-pagination="versionPagination"
        @update:tab="$emit('update:dev-tab', $event)"
        @update:versions-page="$emit('update:versions-page', $event)"
      />

      <ReleaseSupportUserUpdates
        v-if="mode === 'user' && userTab === 'updates'"
        :labels="labels"
        :items="userVersionItems"
        :pagination="userVersionPagination"
        :loading="userVersionUpdatesLoading"
        :detail-loading="userVersionDetailLoading"
        :selected="selectedUserVersion"
        :load-detail="loadUserVersionDetail"
        @update:page="$emit('update:user-versions-page', $event)"
      />

      <template v-if="showReportsPanel">
      <div class="rs-list-filters">
        <div class="rs-search">
          <span class="rs-search__icon" aria-hidden="true">⌕</span>
          <input
            v-model="searchQuery"
            type="search"
            class="rs-search__input"
            :placeholder="labels.searchPlaceholder"
            autocomplete="off"
          />
        </div>
        <div class="rs-tag-filters" role="group" :aria-label="labels.tagLabel">
          <button
            type="button"
            class="rs-tag-chip"
            :class="{ 'rs-tag-chip--active': tagFilter === 'all' }"
            @click="tagFilter = 'all'"
          >
            {{ labels.filterAll }}
          </button>
          <button
            v-for="opt in tagOptions"
            :key="opt.id"
            type="button"
            class="rs-tag-chip"
            :class="[`rs-tag-chip--${opt.id}`, { 'rs-tag-chip--active': tagFilter === opt.id }]"
            @click="tagFilter = opt.id"
          >
            {{ opt.label }}
          </button>
        </div>
      </div>

      <div v-if="reportsLoading" class="rs-dev-ops__loading">
        {{ labels.loading }}
      </div>

      <div v-else-if="reportsTotal === 0" class="rs-empty">
        <div class="rs-empty__icon" aria-hidden="true">◇</div>
        <p>{{ mode === 'dev' ? labels.emptyDev : labels.emptyMy }}</p>
      </div>

      <template v-else-if="reportsTotal > 0">
        <ul v-if="mode === 'user' && filteredCount" class="rs-user-report-list">
          <li v-for="r in filteredSource" :key="'u-' + r.id">
            <button
              type="button"
              class="rs-user-report-card"
              :class="{ 'rs-user-report-card--muted': isInactiveReport(r) }"
              @click="$emit('open-detail', r.id)"
            >
              <div class="rs-user-report-card__top">
                <span class="rs-tag-chip rs-tag-chip--xs" :class="`rs-tag-chip--${reportTag(r)}`">
                  {{ tagLabelFor(reportTag(r)) }}
                </span>
                <span class="rs-badge rs-user-report-card__status" :data-status="r.status">
                  {{ statusLabelFor(r.status) }}
                </span>
              </div>
              <h4 class="rs-user-report-card__title">{{ r.title }}</h4>
              <p class="rs-user-report-card__meta">
                <span v-if="r.created_at">{{ formatDateShort(r.created_at) }}</span>
                <span v-if="r.app_version">{{ labels.fieldAppVersion }} {{ r.app_version }}</span>
              </p>
            </button>
          </li>
        </ul>

        <section v-else-if="activeReports.length" class="rs-list-section">
          <h3 class="rs-list-section__title">
            {{ labels.sectionActive }}
            <span class="rs-list-section__count">{{ activeReports.length }}</span>
          </h3>

          <div class="rs-dev-list">
            <article v-for="r in activeReports" :key="'da-' + r.id" class="rs-dev-row">
              <button type="button" class="rs-dev-row__head" @click="$emit('open-detail', r.id)">
                <span class="rs-issue-row__icon" :data-status="r.status" aria-hidden="true" />
                <div class="rs-dev-row__info">
                  <div class="rs-issue-row__title-row">
                    <span class="rs-tag-chip rs-tag-chip--xs" :class="`rs-tag-chip--${reportTag(r)}`">{{ tagLabelFor(reportTag(r)) }}</span>
                    <span class="rs-dev-row__title">#{{ r.id }} · {{ r.title }}</span>
                  </div>
                  <p class="rs-issue-row__meta">
                    <span>{{ reporterLabel(r) }}</span>
                    <span v-if="r.app_version">{{ labels.fieldAppVersion }}: {{ r.app_version }}</span>
                    <span v-if="r.merge_state === 'waiting_merge'" class="rs-tag-chip rs-tag-chip--xs rs-tag-chip--feature">{{ labels.releaseWaitingBadge }}</span>
                    <span v-if="r.created_at">{{ formatDate(r.created_at) }}</span>
                  </p>
                </div>
                <span class="rs-badge" :data-status="r.status">{{ statusLabelFor(r.status) }}</span>
              </button>
              <div class="rs-dev-row__actions">
                <select v-model="devStatusMap[r.id]" class="rs-select rs-input--sm">
                  <option value="open">{{ labels.statusOpen }}</option>
                  <option value="in_progress">{{ labels.statusInProgress }}</option>
                  <option value="resolved">{{ labels.statusResolved }}</option>
                  <option value="closed">{{ labels.statusClosed }}</option>
                  <option value="cancelled">{{ labels.statusCancelled }}</option>
                </select>
                <button type="button" class="rs-btn rs-btn--primary rs-btn--sm" @click="$emit('update-status', r.id, devStatusMap[r.id])">
                  {{ labels.statusAction }}
                </button>
              </div>
              <textarea v-model="devCommentMap[r.id]" class="rs-textarea" rows="2" :placeholder="labels.commentPlaceholderShort" />
              <button type="button" class="rs-btn rs-btn--ghost rs-btn--sm" @click="$emit('add-comment', r.id, devCommentMap[r.id])">
                {{ labels.addComment }}
              </button>
            </article>
          </div>
        </section>

        <section v-if="mode === 'dev' && closedReports.length" class="rs-list-section rs-list-section--closed">
          <h3 class="rs-list-section__title">
            {{ labels.sectionClosed }}
            <span class="rs-list-section__count">{{ closedReports.length }}</span>
          </h3>
          <div class="rs-issue-list rs-issue-list--muted">
            <button
              v-for="r in closedReports"
              :key="'c-' + r.id"
              type="button"
              class="rs-issue-row rs-issue-row--muted"
              @click="$emit('open-detail', r.id)"
            >
              <span class="rs-issue-row__icon" :data-status="r.status" aria-hidden="true" />
              <div class="rs-issue-row__main">
                <div class="rs-issue-row__title-row">
                  <span class="rs-tag-chip rs-tag-chip--xs" :class="`rs-tag-chip--${reportTag(r)}`">{{ tagLabelFor(reportTag(r)) }}</span>
                  <span class="rs-issue-row__title">{{ r.title }}</span>
                </div>
                <div class="rs-issue-row__meta">
                  <span><strong>#{{ r.id }}</strong></span>
                  <span v-if="mode === 'dev'">{{ reporterLabel(r) }}</span>
                  <span v-if="r.created_at">{{ formatDate(r.created_at) }}</span>
                </div>
              </div>
              <span class="rs-badge" :data-status="r.status">{{ statusLabelFor(r.status) }}</span>
            </button>
          </div>
        </section>

        <p v-if="filteredCount === 0" class="rs-dev-ops__empty">{{ labels.paginationNoMatches }}</p>

        <ReleaseSupportPagination
          :page="reportsPagination.current_page"
          :last-page="reportsPagination.last_page"
          :total="reportsPagination.total"
          :per-page="reportsPagination.per_page"
          :loading="reportsLoading"
          :labels="labels"
          @update:page="$emit('update:reports-page', $event)"
        />
      </template>
      </template>

      <div v-if="mode === 'dev' && devTab !== 'reports'" class="rs-dev-ops-spacer" />
    </div>

    <div v-else-if="view === 'detail'" class="rs-detail-layout">
      <button type="button" class="rs-btn rs-btn--ghost rs-btn--sm" @click="$emit('navigate', 'list')">
        ← {{ labels.back }}
      </button>

      <div v-if="detailLoading" class="rs-loading">
        <span class="rs-loading__spinner" aria-hidden="true" />
        {{ labels.loading }}
      </div>

      <template v-else-if="report">
        <div class="rs-detail-grid">
          <aside class="rs-detail-sidebar">
            <div class="rs-detail-sidebar__card">
              <span class="rs-detail-sidebar__id">#{{ report.id }}</span>
              <h1 class="rs-detail-sidebar__title">{{ report.title }}</h1>
              <span class="rs-tag-chip rs-tag-chip--static" :class="`rs-tag-chip--${detailTag}`">{{ tagLabelFor(detailTag) }}</span>
              <span class="rs-badge" :data-status="report.status">{{ statusLabelFor(report.status) }}</span>
              <dl class="rs-detail-dl">
                <div>
                  <dt>{{ labels.fieldAppVersion }}</dt>
                  <dd>{{ report.app_version || '—' }}</dd>
                </div>
                <div v-if="report.created_at">
                  <dt>{{ labels.detailCreatedAt }}</dt>
                  <dd>{{ formatDate(report.created_at) }}</dd>
                </div>
                <div v-if="mode === 'dev'">
                  <dt>User</dt>
                  <dd>{{ reporterLabel(report) }}</dd>
                </div>
                <div v-if="reportContext.user_agent">
                  <dt>{{ labels.detailUserAgent }}</dt>
                  <dd class="rs-detail-dl__wrap">{{ reportContext.user_agent }}</dd>
                </div>
                <div v-if="reportContext.viewport">
                  <dt>{{ labels.detailViewport }}</dt>
                  <dd>{{ reportContext.viewport }}</dd>
                </div>
                <div v-if="reportContext.pathname">
                  <dt>{{ labels.detailPathname }}</dt>
                  <dd>{{ reportContext.pathname }}</dd>
                </div>
                <div v-if="reportContext.href">
                  <dt>{{ labels.detailPageUrl }}</dt>
                  <dd class="rs-detail-dl__wrap">
                    <a :href="reportContext.href" target="_blank" rel="noopener noreferrer">{{ reportContext.href }}</a>
                  </dd>
                </div>
                <div v-if="reportContext.captured_at">
                  <dt>{{ labels.detailCapturedAt }}</dt>
                  <dd>{{ formatDate(reportContext.captured_at) }}</dd>
                </div>
              </dl>
            </div>

            <div v-if="mode === 'dev'" class="rs-detail-sidebar__card">
              <h3 class="rs-detail-sidebar__head">{{ labels.detailStatusCard }}</h3>
              <select v-model="localStatus" class="rs-select">
                <option value="open">{{ labels.statusOpen }}</option>
                <option value="in_progress">{{ labels.statusInProgress }}</option>
                <option value="resolved">{{ labels.statusResolved }}</option>
                <option value="closed">{{ labels.statusClosed }}</option>
                <option value="cancelled">{{ labels.statusCancelled }}</option>
              </select>
              <button type="button" class="rs-btn rs-btn--primary rs-btn--sm rs-detail-sidebar__btn" @click="$emit('update-status', report.id, localStatus)">
                {{ labels.updateStatus }}
              </button>
            </div>

            <div class="rs-detail-sidebar__card">
              <h3 class="rs-detail-sidebar__head">{{ labels.detailCommentCard }}</h3>

              <div v-if="recentComments.length" class="rs-detail-comments-preview">
                <article v-for="c in recentComments" :key="'rc-' + commentKey(c)" class="rs-detail-comments-preview__item">
                  <header class="rs-detail-comments-preview__meta">
                    <span>{{ commentAuthor(c) }}</span>
                    <time v-if="commentDate(c)">{{ formatDate(commentDate(c)) }}</time>
                  </header>
                  <p class="rs-detail-comments-preview__text">{{ commentText(c) }}</p>
                </article>
              </div>

              <textarea v-if="mode === 'dev'" v-model="localComment" class="rs-textarea" rows="3" :placeholder="labels.commentPlaceholder" />
              <button v-if="mode === 'dev'" type="button" class="rs-btn rs-btn--sm rs-detail-sidebar__btn" @click="submitLocalComment">
                {{ labels.addComment }}
              </button>
            </div>
          </aside>

          <div class="rs-detail-main">
            <section v-if="report.description" class="rs-panel">
              <div class="rs-panel__head">Description</div>
              <div class="rs-panel__body rs-detail-prose">{{ report.description }}</div>
            </section>

            <section v-if="(report.drawings || []).length" class="rs-panel">
              <div class="rs-panel__head">{{ labels.screenshot }}</div>
              <div class="rs-panel__body">
                <div class="rs-gallery">
                  <ReleaseSupportDrawingImg
                    v-for="(d, i) in report.drawings"
                    :key="'img-' + i"
                    :src="d"
                    :alt="labels.drawingAlt"
                    :view-label="labels.viewDrawing"
                    clickable
                    @open="openDrawingLightbox(i)"
                  />
                </div>
              </div>
            </section>

            <section class="rs-panel">
              <div class="rs-panel__head">{{ labels.timelineTitle }}</div>
              <div class="rs-panel__body rs-panel__body--flush rs-panel__body--timeline">
                <ul class="rs-activity">
                  <li v-for="(e, i) in report.timeline || []" :key="'tl-' + i">
                    <span class="rs-activity__dot" aria-hidden="true" />
                    <div class="rs-activity__body">
                      <span class="rs-activity__text">{{ timelineLabel(e) }}</span>
                      <time>{{ e.at }}</time>
                    </div>
                  </li>
                </ul>
              </div>
            </section>

            <section v-if="mode === 'dev'" class="rs-panel">
              <div class="rs-panel__head">{{ labels.consoleLogs }}</div>
              <pre class="rs-console">{{ consoleLogText }}</pre>
            </section>
          </div>
        </div>
      </template>
    </div>

    <ReleaseSupportDrawingLightbox
      :open="drawingLightboxOpen"
      :sources="report?.drawings || []"
      :index="drawingLightboxIndex"
      :dark-mode="darkMode"
      :alt="labels.drawingAlt"
      :title="labels.screenshot"
      :close-label="labels.closeAria"
      :prev-label="labels.drawingPrev"
      :next-label="labels.drawingNext"
      @close="closeDrawingLightbox"
      @prev="drawingLightboxIndex = Math.max(0, drawingLightboxIndex - 1)"
      @next="drawingLightboxIndex = Math.min((report?.drawings?.length || 1) - 1, drawingLightboxIndex + 1)"
    />
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { filterConsoleLogs, formatConsoleLogLine } from '../utils/consoleLogs'
import {
  filterReports,
  getReportTag,
  isInactiveReport,
  partitionReports,
  sortReportsActiveFirst,
} from '../utils/reportTags'
import ReleaseSupportDevOpsPanel from './ReleaseSupportDevOpsPanel.vue'
import ReleaseSupportUserUpdates from './ReleaseSupportUserUpdates.vue'
import ReleaseSupportDrawingImg from './ReleaseSupportDrawingImg.vue'
import ReleaseSupportDrawingLightbox from './ReleaseSupportDrawingLightbox.vue'
import ReleaseSupportPagination from './ReleaseSupportPagination.vue'

const props = defineProps({
  mode: { type: String, required: true, validator: (v) => v === 'user' || v === 'dev' },
  darkMode: { type: Boolean, default: false },
  labels: { type: Object, required: true },
  tagOptions: { type: Array, default: () => [] },
  tagLabelFor: { type: Function, required: true },
  statusLabelFor: { type: Function, required: true },
  view: { type: String, default: 'list' },
  myReports: { type: Array, default: () => [] },
  myReportsPagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 20, total: 0 }) },
  myReportsLoading: { type: Boolean, default: false },
  devReports: { type: Array, default: () => [] },
  devReportsPagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 20, total: 0 }) },
  devReportsLoading: { type: Boolean, default: false },
  devStatusMap: { type: Object, default: () => ({}) },
  devCommentMap: { type: Object, default: () => ({}) },
  report: { type: Object, default: null },
  detailLoading: { type: Boolean, default: false },
  timelineLabelFn: { type: Function, default: null },
  devTab: { type: String, default: 'reports' },
  devMetrics: { type: Object, default: null },
  devMetricsLoading: { type: Boolean, default: false },
  releasePreview: { type: Object, default: null },
  releasePreviewLoading: { type: Boolean, default: false },
  versionUpdates: { type: Array, default: () => [] },
  versionUpdatesLoading: { type: Boolean, default: false },
  versionSaving: { type: Boolean, default: false },
  selectedVersion: { type: Object, default: null },
  versionDetailLoading: { type: Boolean, default: false },
  createVersionRelease: { type: Function, default: () => async () => false },
  updateVersion: { type: Function, default: () => async () => false },
  loadVersionDetail: { type: Function, default: async () => {} },
  versionPagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 20, total: 0 }) },
  userTab: { type: String, default: 'reports' },
  userVersionItems: { type: Array, default: () => [] },
  userVersionPagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 20, total: 0 }) },
  userVersionUpdatesLoading: { type: Boolean, default: false },
  selectedUserVersion: { type: Object, default: null },
  userVersionDetailLoading: { type: Boolean, default: false },
  loadUserVersionDetail: { type: Function, default: async () => {} },
})

const emit = defineEmits([
  'navigate',
  'open-detail',
  'update-status',
  'add-comment',
  'update:dev-tab',
  'update:reports-page',
  'update:versions-page',
  'update:user-versions-page',
  'update:user-tab',
])

const searchQuery = ref('')
const tagFilter = ref('all')
const localStatus = ref('open')
const localComment = ref('')
const drawingLightboxOpen = ref(false)
const drawingLightboxIndex = ref(0)

function openDrawingLightbox(index) {
  drawingLightboxIndex.value = index
  drawingLightboxOpen.value = true
}

function closeDrawingLightbox() {
  drawingLightboxOpen.value = false
}

const sourceReports = computed(() => (props.mode === 'dev' ? props.devReports : props.myReports))

const reportsPagination = computed(() =>
  props.mode === 'dev' ? props.devReportsPagination : props.myReportsPagination,
)

const reportsLoading = computed(() =>
  props.mode === 'dev' ? props.devReportsLoading : props.myReportsLoading,
)

const reportsTotal = computed(() => Number(reportsPagination.value?.total) || 0)

const filteredSource = computed(() =>
  sortReportsActiveFirst(
    filterReports(sourceReports.value, { query: searchQuery.value, tag: tagFilter.value }),
  ),
)

const partitioned = computed(() => partitionReports(filteredSource.value))
const activeReports = computed(() => partitioned.value.active)
const closedReports = computed(() => partitioned.value.closed)
const filteredCount = computed(() => filteredSource.value.length)

const showReportsPanel = computed(() =>
  props.mode === 'dev' ? props.devTab === 'reports' : props.userTab === 'reports',
)

const detailTag = computed(() => getReportTag(props.report || {}))

const reportContext = computed(() => {
  const ctx = props.report?.captured_context
  return ctx && typeof ctx === 'object' && !Array.isArray(ctx) ? ctx : {}
})

const recentComments = computed(() => {
  const list = props.report?.comments
  if (!Array.isArray(list)) return []
  return [...list]
    .sort((a, b) => {
      const ta = new Date(commentDate(a) || 0).getTime()
      const tb = new Date(commentDate(b) || 0).getTime()
      if (tb !== ta) return tb - ta
      return Number(b.id || 0) - Number(a.id || 0)
    })
    .slice(0, 2)
})

const consoleLogText = computed(() => {
  const lines = filterConsoleLogs(props.report?.captured_logs).map(formatConsoleLogLine)
  return lines.length ? lines.join('\n') : props.labels.noConsoleLogs
})

function reportTag(r) {
  return getReportTag(r)
}

function commentKey(c) {
  return c?.id != null ? c.id : `${c?.user_id}-${commentDate(c)}`
}

function commentDate(c) {
  return c?.created_at || c?.createdAt || ''
}

function commentText(c) {
  return c?.comment || c?.text || ''
}

function commentAuthor(c) {
  const name = String(c?.user_name || '').trim()
  if (name) return name
  if (c?.user_id != null) return `#${c.user_id}`
  return '—'
}

function reporterLabel(r) {
  const name = String(r?.reporter_name || '').trim()
  if (name) return name
  if (r?.user_id != null) return `#${r.user_id}`
  return '—'
}

watch(
  () => props.report,
  (r) => {
    if (r) {
      localStatus.value = r.status || 'open'
      localComment.value = ''
    }
    closeDrawingLightbox()
  },
  { immediate: true },
)

function timelineLabel(entry) {
  return props.timelineLabelFn ? props.timelineLabelFn(entry) : entry?.type
}

function submitLocalComment() {
  const text = String(localComment.value || '').trim()
  if (!text || !props.report?.id) return
  emit('add-comment', props.report.id, text)
  localComment.value = ''
}

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

function formatDateShort(value) {
  if (!value) return ''
  try {
    const d = new Date(value)
    if (Number.isNaN(d.getTime())) return String(value)
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
  } catch {
    return String(value)
  }
}
</script>

<script>
export default {
  name: 'ReleaseSupportListPanel',
}
</script>
