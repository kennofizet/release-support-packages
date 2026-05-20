import { computed, isRef } from 'vue'
import { createTranslator, formatMessage } from '../i18n'
import { REPORT_TAG_IDS } from '../utils/reportTags'

export function useReleaseSupportLabels(languageRef) {
  const effectiveLanguage = computed(() => {
    const lang = isRef(languageRef) ? languageRef.value : languageRef
    return lang === 'en' ? 'en' : 'vi'
  })

  const translate = computed(() => createTranslator(effectiveLanguage.value))

  const t = computed(() => {
    const tr = translate.value
    return {
      fab: tr('widget.fab'),
      closeAria: tr('widget.closeAria'),
      back: tr('widget.back'),
      loading: tr('widget.loading'),
      fieldTitle: tr('widget.fields.title'),
      fieldTitlePlaceholder: tr('widget.fields.titlePlaceholder'),
      fieldAppVersion: tr('widget.fields.appVersion'),
      fieldAppVersionPlaceholder: tr('widget.fields.appVersionPlaceholder'),
      fieldDescription: tr('widget.fields.description'),
      fieldDescriptionPlaceholder: tr('widget.fields.descriptionPlaceholder'),
      drawLabel: tr('widget.draw.label'),
      drawOnScreen: tr('widget.draw.onScreen'),
      drawClear: tr('widget.draw.clear'),
      drawSave: tr('widget.draw.save'),
      drawCancel: tr('widget.draw.cancel'),
      pen: tr('widget.draw.pen'),
      eraser: tr('widget.draw.eraser'),
      capturing: tr('widget.draw.capturing'),
      captureFailed: tr('widget.draw.captureFailed'),
      myReports: tr('widget.actions.myReports'),
      newReport: tr('widget.actions.newReport'),
      submit: tr('widget.actions.submit'),
      submitting: tr('widget.actions.submitting'),
      submitFailed: tr('widget.actions.submitFailed'),
      submitRateLimited: tr('widget.actions.submitRateLimited'),
      devTriage: tr('widget.actions.devTriage'),
      refreshReports: tr('widget.actions.refreshReports'),
      metrics: tr('widget.actions.metrics'),
      updateStatus: tr('widget.actions.updateStatus'),
      addComment: tr('widget.actions.addComment'),
      statusAction: tr('widget.actions.status'),
      cancel: tr('widget.draw.cancel'),
      detailStatus: tr('widget.detail.status'),
      detailStatusCard: tr('widget.detail.statusCard'),
      detailCommentCard: tr('widget.detail.commentCard'),
      detailRecentComments: tr('widget.detail.recentComments'),
      detailUserAgent: tr('widget.detail.userAgent'),
      detailPageUrl: tr('widget.detail.pageUrl'),
      detailPathname: tr('widget.detail.pathname'),
      detailViewport: tr('widget.detail.viewport'),
      detailCapturedAt: tr('widget.detail.capturedAt'),
      detailCreatedAt: tr('widget.detail.createdAt'),
      timelineTitle: tr('widget.detail.timeline'),
      consoleLogs: tr('widget.detail.consoleLogs'),
      screenshot: tr('widget.detail.screenshot'),
      noConsoleLogs: tr('widget.detail.noConsoleLogs'),
      commentPlaceholder: tr('widget.commentPlaceholder'),
      commentPlaceholderShort: tr('widget.commentPlaceholderShort'),
      statusOpen: tr('widget.status.open'),
      statusInProgress: tr('widget.status.in_progress'),
      statusResolved: tr('widget.status.resolved'),
      statusClosed: tr('widget.status.closed'),
      timelineCreated: tr('widget.timeline.created'),
      timelineStatus: tr('widget.timeline.status'),
      timelineComment: tr('widget.timeline.comment'),
      drawingAlt: tr('widget.drawingAlt'),
      viewDrawing: tr('widget.viewDrawing'),
      drawingPrev: tr('widget.drawingPrev'),
      drawingNext: tr('widget.drawingNext'),
      previewAlt: tr('widget.previewAlt'),
      titleCreate: tr('widget.titles.create'),
      titleDetail: tr('widget.titles.detail'),
      titleDev: tr('widget.titles.dev'),
      titleHub: tr('widget.titles.hub'),
      emptyMy: tr('widget.empty.my'),
      emptyDev: tr('widget.empty.dev'),
      versionBanner: tr('widget.versionBanner'),
      latestUpdate: tr('widget.latestUpdate'),
      submitSuccess: tr('widget.submitSuccess'),
      submitSuccessDismiss: tr('widget.submitSuccessDismiss'),
      searchPlaceholder: tr('widget.list.searchPlaceholder'),
      filterAll: tr('widget.list.filterAll'),
      sectionActive: tr('widget.list.sectionActive'),
      sectionClosed: tr('widget.list.sectionClosed'),
      userMode: tr('widget.list.userMode'),
      devMode: tr('widget.list.devMode'),
      tagLabel: tr('widget.tags.label'),
      tagBug: tr('widget.tags.bug'),
      tagFeature: tr('widget.tags.feature'),
      tagQuestion: tr('widget.tags.question'),
      tagImprovement: tr('widget.tags.improvement'),
      tagOther: tr('widget.tags.other'),
    }
  })

  const tagLabelKeys = {
    bug: 'tagBug',
    feature: 'tagFeature',
    question: 'tagQuestion',
    improvement: 'tagImprovement',
    other: 'tagOther',
  }

  const tagOptions = computed(() =>
    REPORT_TAG_IDS.map((id) => ({
      id,
      label: t.value[tagLabelKeys[id]] || id,
    })),
  )

  function tagLabelFor(id) {
    const key = tagLabelKeys[String(id || 'other').toLowerCase()] || 'tagOther'
    return t.value[key] || t.value.tagOther
  }

  function normalizeTagId(tag) {
    const id = String(tag || 'other').toLowerCase()
    return REPORT_TAG_IDS.includes(id) ? id : 'other'
  }

  function titleForTag(tag) {
    const id = normalizeTagId(tag)
    const tr = translate.value
    return tr(`widget.titles.byTag.${id}`, tr('widget.titles.create'))
  }

  function subtitleForTag(tag) {
    const id = normalizeTagId(tag)
    const tr = translate.value
    return tr(`widget.formSubtitle.byTag.${id}`, tr('widget.formSubtitle.default'))
  }

  function titlePlaceholderForTag(tag) {
    const id = normalizeTagId(tag)
    const tr = translate.value
    return tr(`widget.fields.titlePlaceholderByTag.${id}`, tr('widget.fields.titlePlaceholder'))
  }

  const statusLabelKeys = {
    open: 'statusOpen',
    in_progress: 'statusInProgress',
    resolved: 'statusResolved',
    closed: 'statusClosed',
  }

  function statusLabelFor(status) {
    const key = statusLabelKeys[String(status || '').toLowerCase()]
    return key ? t.value[key] : String(status || '')
  }

  const drawLabels = computed(() => ({
    pen: t.value.pen,
    eraser: t.value.eraser,
    clear: t.value.drawClear,
    save: t.value.drawSave,
    cancel: t.value.drawCancel,
    capturing: t.value.capturing,
    captureFailed: t.value.captureFailed,
  }))

  function versionBannerText(latestVersion) {
    return formatMessage(t.value.versionBanner, { version: latestVersion || '' })
  }

  function latestUpdateText(latestVersion) {
    return formatMessage(t.value.latestUpdate, { version: latestVersion || '' })
  }

  function timelineLabel(entry) {
    if (entry.type === 'created') return `${t.value.timelineCreated} (${statusLabelFor(entry.status)})`
    if (entry.type === 'status') {
      return `${t.value.timelineStatus} ${statusLabelFor(entry.from_status) || '—'} → ${statusLabelFor(entry.to_status)}`
    }
    if (entry.type === 'comment') {
      const who = entry.user_name
        ? String(entry.user_name)
        : entry.user_id != null
          ? `#${entry.user_id}`
          : '—'
      return `${t.value.timelineComment} ${who}`
    }
    return entry.type
  }

  return {
    t,
    tagOptions,
    tagLabelFor,
    titleForTag,
    subtitleForTag,
    titlePlaceholderForTag,
    statusLabelFor,
    drawLabels,
    versionBannerText,
    latestUpdateText,
    timelineLabel,
  }
}
