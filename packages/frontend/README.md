# Release Support Frontend

Vue 3 module for **runtime issue capture**, **draw-on-screen reporting**, and **dev triage UI**. Works with **release-support-backend** and **packages-core** auth (`X-Knf-Token`). Not zone-based — pass `backendUrl`, `token`, and `appVersion` only.

---

## Requirements

- Vue 3.2+
- **axios**
- Backend: `kennofizet/release-support-backend` + packages-core token middleware

---

## Install

```bash
npm install @kennofizet/release-support-frontend
# or
yarn add @kennofizet/release-support-frontend
```

---

## Setup

**Plugin (recommended):**

```js
import { createApp } from 'vue'
import { installReleaseSupportModule } from '@kennofizet/release-support-frontend'
import App from './App.vue'

const app = createApp(App)

installReleaseSupportModule(app, {
  backendUrl: 'https://your-api/api/knf/release-support',
  token: 'your-knf-token',
  appVersion: '1.4.0',
  successRedirectUrl: '',
  successMessage: '',
  successTarget: 'body',
  autoStartCapture: true,
  captureMaxLogs: 200,
})

app.mount('#app')
```

Add the widget once in your root layout:

```vue
<template>
  <App />
  <ReleaseSupportWidget :language="lang" :dark-mode="isDark" />
</template>
```

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `language` | `string` or ref | `'vi'` | `vi` \| `en` |
| `darkMode` | `boolean` or ref | `false` | Dark theme |
| `successRedirectUrl` | `string` | from install | Redirect after success dismiss |
| `successMessage` | `string` | from install | Override success banner text |
| `successTarget` | `string` | `'body'` | Teleport target for success banner |

This registers **ReleaseSupportWidget** (create only) and **ReleaseSupportList** (reports UI).

```vue
<!-- App layout — submit issues -->
<ReleaseSupportWidget :language="lang" :dark-mode="isDark" />

<!-- Support page — list for users & devs -->
<ReleaseSupportList :language="lang" :dark-mode="isDark" show-header />
```

**Manual setup:**

```js
import {
  createReleaseSupportApi,
  ReleaseSupportWidget,
  useReleaseSupportTracker,
} from '@kennofizet/release-support-frontend'

const api = createReleaseSupportApi(backendUrl, token)
app.provide('releaseSupportApi', api)
app.component('ReleaseSupportWidget', ReleaseSupportWidget)
```

---

## Background capture

When `autoStartCapture` is not `false`, the module records:

| Source | Log `type` |
|--------|------------|
| `console.error` | `console_error` |
| `window.onerror` | `window_error` |
| `unhandledrejection` | `unhandled_rejection` |
| Failed axios calls (this package API client) | `api_error` |

Logs are stored in **localStorage** (`release_support_logs`), trimmed to `capture_max_logs` from bootstrap (default 200).

On submit, the widget sends:

- `captured_logs` — stored logs
- `captured_context` — URL, user agent, cookies, localStorage snapshot, timestamp

---

## Bootstrap & force open

On mount, the widget calls `GET /bootstrap` and applies:

| Field | Effect |
|-------|--------|
| `force_show_reporter` | Open reporter once (tracked in localStorage) |
| `is_dev_user` | Dev tabs in **ReleaseSupportList** |
| `capture_max_logs` | Used by capture layer when configured |
| `latest_update` | Show version/title/content banner; prefill `app_version` if empty |

Configure force open on the server:

```env
RELEASE_SUPPORT_FORCE_SHOW_REPORTER=true
```

---

## ReleaseSupportWidget

Floating **Support** button → **create form modal** only (no list popup).

1. Click Support or force-open from bootstrap.
2. Fill title, description, version; optional screen capture + annotate.
3. Submit → success banner on page; optional `successRedirectUrl`.

## ReleaseSupportList

Embed on a support/admin page. Tabs: **My reports** and **Dev triage** (if dev user). Open a report for detail, timeline, console logs, screenshots, status updates, and comments.

---

## API client

`createReleaseSupportApi(backendUrl, token)` returns:

| Method | Description |
|--------|-------------|
| `bootstrap()` | GET config + latest update |
| `submitReport(payload)` | POST new report |
| `myReports({ status, per_page })` | GET own reports |
| `reportDetail(reportId)` | GET one report |
| `devReports({ status, per_page })` | GET all reports (dev) |
| `devUpdateStatus(reportId, status)` | POST status change |
| `devAddComment(reportId, comment)` | POST comment |
| `devVersionUpdates({ per_page })` | GET version list |
| `devCreateVersionUpdate(payload)` | POST version notice |
| `devUpdateVersionUpdate(id, payload)` | PUT version notice |

All requests send **`X-Knf-Token`**.

`bootstrap(appVersion)` sends `?app_version=` for semver comparison. Widget shows update banner when outdated.

**Views:** create report, report detail (timeline + logs + drawings), dev triage (inline comments, metrics, status updates).

## i18n

- `src/i18n/translations/en.js`
- `src/i18n/translations/vi.js`
- `src/i18n/index.js` — `t(lang, key)`, `createTranslator(lang)`, `formatMessage(template, params)`

```js
import { createTranslator } from '@kennofizet/release-support-frontend'
const tr = createTranslator('vi')
tr('widget.fab') // "Hỗ trợ"
```

---

## Composable: useReleaseSupportTracker

For custom UI without the default widget:

```js
import { useReleaseSupportTracker } from '@kennofizet/release-support-frontend'

const tracker = useReleaseSupportTracker()
tracker.startCapture(200)
tracker.openReporter()
const { captured_logs, captured_context } = tracker.getPayloadParts()
```

Shared singleton state: `isOpen`, `bootstrapData`, `started`.

---

## Inject keys

| Key | Content |
|-----|---------|
| `releaseSupportApi` | API client instance |
| `releaseSupportOptions` | `{ appVersion }` from install options |

---

## Summary

| Step | Action |
|------|--------|
| Install | `npm install @kennofizet/release-support-frontend` |
| Setup | `installReleaseSupportModule(app, { backendUrl, token, appVersion })` |
| UI | `<ReleaseSupportWidget />` in root layout |
| Server | Set `RELEASE_SUPPORT_DEV_USER_IDS`, optional `RELEASE_SUPPORT_FORCE_SHOW_REPORTER` |

See **packages/backend/README.md** for API payloads, events, and migrations.
