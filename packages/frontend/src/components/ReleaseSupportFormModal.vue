<template>
  <Teleport to="body">
    <div v-if="open" class="rs-form-backdrop rs-ignore-capture" @click.self="$emit('close')">
      <div
        class="rs-form-modal rs-root"
        :class="{ 'rs-form-modal--dark': darkMode, 'rs-root--dark': darkMode }"
        role="dialog"
        aria-modal="true"
        aria-labelledby="rs-form-title"
        @click.stop
      >
        <header class="rs-form-modal__head">
          <div class="rs-form-modal__head-icon" aria-hidden="true">&#9998;</div>
          <div class="rs-form-modal__head-text">
            <h2 id="rs-form-title">{{ labels.titleCreate }}</h2>
            <p>Capture issues with screenshots and console context</p>
          </div>
          <button type="button" class="rs-icon-btn" :aria-label="labels.closeAria" @click="$emit('close')">&times;</button>
        </header>

        <div class="rs-form-modal__body">
          <div v-if="submitError" class="rs-alert rs-alert--error" role="alert">
            {{ submitError }}
          </div>

          <div v-if="versionOutdated && latestUpdate" class="rs-alert rs-alert--warn">
            {{ versionBannerText }}
          </div>

          <div v-if="latestUpdate" class="rs-update-banner">
            <div>
              <strong>{{ latestUpdateText }}</strong>
              <p>{{ latestUpdate.title }}</p>
            </div>
          </div>

          <section class="rs-form-section">
            <h3 class="rs-form-section__label">{{ labels.tagLabel }}</h3>
            <div class="rs-tag-filters rs-tag-filters--form">
              <button
                v-for="opt in tagOptions"
                :key="'ft-' + opt.id"
                type="button"
                class="rs-tag-chip"
                :class="[`rs-tag-chip--${opt.id}`, { 'rs-tag-chip--active': selectedTag === opt.id }]"
                @click="$emit('update:tag', opt.id)"
              >
                {{ opt.label }}
              </button>
            </div>
          </section>

          <section class="rs-form-section">
            <h3 class="rs-form-section__label">{{ labels.fieldTitle }}</h3>
            <label class="rs-field" style="margin-bottom: 0">
              <input
                v-model="form.title"
                class="rs-input"
                type="text"
                :placeholder="labels.fieldTitlePlaceholder"
                autocomplete="off"
              />
            </label>
          </section>

          <section class="rs-form-section">
            <h3 class="rs-form-section__label">{{ labels.fieldAppVersion }}</h3>
            <label class="rs-field" style="margin-bottom: 0">
              <input
                v-model="form.app_version"
                class="rs-input"
                type="text"
                :placeholder="labels.fieldAppVersionPlaceholder"
              />
            </label>
          </section>

          <section class="rs-form-section">
            <h3 class="rs-form-section__label">{{ labels.fieldDescription }}</h3>
            <label class="rs-field" style="margin-bottom: 0">
              <textarea
                v-model="form.description"
                class="rs-textarea"
                rows="4"
                :placeholder="labels.fieldDescriptionPlaceholder"
              />
            </label>
          </section>

          <section class="rs-form-section">
            <h3 class="rs-form-section__label">{{ labels.drawLabel }}</h3>
            <div class="rs-draw-zone">
              <button type="button" class="rs-btn rs-btn--outline" @click="$emit('open-draw')">
                {{ labels.drawOnScreen }}
              </button>
              <p class="rs-draw-zone__hint">Highlight the problem on screen, then save to attach.</p>
              <div v-if="drawings.length" class="rs-thumb-grid">
                <img v-for="(d, i) in drawings" :key="'d-' + i" :src="d" :alt="labels.previewAlt" />
              </div>
            </div>
          </section>
        </div>

        <footer class="rs-form-modal__foot">
          <button type="button" class="rs-btn rs-btn--ghost" @click="$emit('close')">{{ labels.cancel }}</button>
          <button
            type="button"
            class="rs-btn rs-btn--primary"
            :disabled="submitting || !form.title.trim()"
            @click="$emit('submit')"
          >
            {{ submitting ? labels.submitting : labels.submit }}
          </button>
        </footer>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
defineProps({
  open: { type: Boolean, default: false },
  darkMode: { type: Boolean, default: false },
  labels: { type: Object, required: true },
  selectedTag: { type: String, default: 'bug' },
  form: { type: Object, required: true },
  drawings: { type: Array, default: () => [] },
  submitting: { type: Boolean, default: false },
  submitError: { type: String, default: '' },
  versionOutdated: { type: Boolean, default: false },
  latestUpdate: { type: Object, default: null },
  versionBannerText: { type: String, default: '' },
  latestUpdateText: { type: String, default: '' },
  tagOptions: { type: Array, default: () => [] },
})

defineEmits(['close', 'submit', 'open-draw', 'update:tag'])
</script>


