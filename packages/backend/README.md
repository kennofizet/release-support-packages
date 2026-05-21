# Release Support Backend

Laravel package for **in-app issue reporting**, runtime log capture payloads, **draw annotations**, and **version/update notices** for dev workflows. Uses **packages-core** for API auth and current user context. This package is **not zone-scoped** — support data is global per app install (dev access is controlled by config user ids).

---

## Requirements

- PHP 8.2+, Laravel 12.x
- **kennofizet/packages-core-backend** (token middleware, `currentUserId`, shared `BaseModel`)

---

## Install

```bash
composer require kennofizet/release-support-backend
php artisan vendor:publish --tag=release-support-config
php artisan vendor:publish --tag=release-support-migrations
php artisan migrate
```

**Optional .env:**

```env
RELEASE_SUPPORT_API_PREFIX=release-support
RELEASE_SUPPORT_REPORTS_TABLE=release_support_reports
RELEASE_SUPPORT_REPORT_COMMENTS_TABLE=release_support_report_comments
RELEASE_SUPPORT_VERSION_UPDATES_TABLE=release_support_version_updates

# Open reporter UI once on first app load (frontend reads bootstrap)
RELEASE_SUPPORT_FORCE_SHOW_REPORTER=false

# Comma-separated user ids allowed to use /dev/* APIs and dev UI
RELEASE_SUPPORT_DEV_USER_IDS=1

# Max client log lines the frontend should keep (also returned in bootstrap)
RELEASE_SUPPORT_CAPTURE_MAX_LOGS=200
# disk = save PNG/JPG files on storage; DB keeps paths only (recommended)
# json = legacy: store base64 data URLs in DB (large rows)
RELEASE_SUPPORT_DRAWINGS_STORAGE=disk
RELEASE_SUPPORT_DRAWINGS_DISK=local
RELEASE_SUPPORT_DRAWINGS_PATH=release-support/drawings
RELEASE_SUPPORT_MAX_DRAWING_BYTES=5242880
RELEASE_SUPPORT_REPORT_SUBMIT_RATE_LIMIT=10
RELEASE_SUPPORT_DEDUPE_ENABLED=true
RELEASE_SUPPORT_DEDUPE_WINDOW_MINUTES=5
RELEASE_SUPPORT_QUEUE_LISTENERS=false
RELEASE_SUPPORT_WEBHOOK_URL=
```

User table mapping comes from **packages-core** (`table_user`, `user_col_name`) when the host app enriches responses — this package stores `user_id` only.

---

## Config

**config/release-support.php**

| Key | Description |
|-----|-------------|
| `api_prefix` | URL segment under packages-core API prefix |
| `reports_table` / `report_comments_table` / `version_updates_table` | Table names |
| `force_show_reporter` | Passed to frontend via bootstrap |
| `dev_user_ids` | Users with dev API + UI access |
| `capture_max_logs` | Client log buffer size hint |
| `drawings_storage` | `disk` (default): files on storage, paths in DB. `json`: legacy base64 in DB |
| `drawings_disk` | Laravel disk name (default `local`, private). Use `public` only if you accept unauthenticated `/storage` URLs |
| `report_tags` | Allowed category ids: `bug`, `feature`, `question`, `improvement`, `other` — **required** in published config |
| `drawings_path` | Folder under disk root (default `release-support/drawings`) |
| `max_drawing_bytes` | Max decoded image size per drawing (default 5MB) |
| `report_event_class` | Event dispatched after report create (default `IssueReportSubmitted`) |
| `report_status_changed_event_class` | Event when dev changes report status (default `ReportStatusChanged`) |
| `report_comment_added_event_class` | Event when dev adds a comment (default `ReportCommentAdded`) |
| `version_released_event_class` | Event when a version release merges completed reports (default `VersionReleased`) |
| `after_submitted_listeners` | Classes implementing `AfterIssueReportSubmittedListener` |
| `after_status_changed_listeners` | Callable listeners: `handle($subject, array $context)` |
| `after_comment_added_listeners` | Callable listeners: `handle($subject, array $context)` |
| `after_version_released_listeners` | Callable listeners: `handle($subject, array $context)` |

---

## Drawings / screenshots (storage)

The frontend still sends **base64 data URLs** on submit. With `drawings_storage=disk` (default), the backend:

1. Decodes each `data:image/...;base64,...` payload
2. Writes a file under `storage/app/public/release-support/drawings/{reportId}/`
3. Saves only the **relative path** in the `drawings` JSON column

API responses return full URLs (`/storage/...` for the `public` disk, or an authenticated API URL for private disks).

**Host app setup (public disk):**

```bash
php artisan storage:link
```

```env
RELEASE_SUPPORT_DRAWINGS_STORAGE=disk
RELEASE_SUPPORT_DRAWINGS_DISK=local
```

For a private disk (`local`), the API returns relative paths like `drawings/{reportId}/{file}.png`. The frontend loads them with `GET …/drawings/{reportId}/{filename}` and **`X-Knf-Token`** (blob URL for `<img>`). Do not rely on Laravel `APP_URL` in `img src`.

---

## Events & listeners

After a report is saved, the package:

1. Dispatches `IssueReportSubmitted` (or class from `report_event_class`).
2. Runs each class in `after_submitted_listeners` that implements:

```php
use Kennofizet\ReleaseSupport\Contracts\AfterIssueReportSubmittedListener;
use Kennofizet\ReleaseSupport\Models\ReleaseSupportReport;

class NotifyTeamOnIssueReport implements AfterIssueReportSubmittedListener
{
    public function handle(ReleaseSupportReport $report): void
    {
        // Slack, email, internal ticket, etc.
    }
}
```

Register in config:

```php
'after_submitted_listeners' => [
    \App\Listeners\NotifyTeamOnIssueReport::class,
],
```

### Status, comment, and version events

When a dev updates status or adds a comment, the package dispatches the configured event class, then runs `after_status_changed_listeners` / `after_comment_added_listeners`. Each listener receives the event instance and a context array (report id, old/new status, comment text, actor user id, etc.).

When a dev creates a **version release** (merge), the package dispatches `VersionReleased` (or `version_released_event_class`) and runs `after_version_released_listeners`.

Override event classes via `.env`:

```env
RELEASE_SUPPORT_STATUS_CHANGED_EVENT_CLASS=\App\Events\MyReportStatusChanged
RELEASE_SUPPORT_COMMENT_ADDED_EVENT_CLASS=\App\Events\MyReportCommentAdded
RELEASE_SUPPORT_VERSION_RELEASED_EVENT_CLASS=\App\Events\MyVersionReleased
```

Example generic listener:

```php
class LogReleaseSupportActivity
{
    public function handle(object $subject, array $context): void
    {
        // $subject is the dispatched event; $context has ids and payloads
    }
}
```

```php
'after_version_released_listeners' => [
    \App\Listeners\LogReleaseSupportActivity::class,
],
```

---

## Traits for host User model

### `HasReleaseSupportReports` — reporter's own reports

```php
use Kennofizet\ReleaseSupport\Traits\HasReleaseSupportReports;

class User extends Authenticatable
{
    use HasReleaseSupportReports;
}

$reports = $user->getReleaseSupportReports('open', 20);
```

### `ManagesReleaseSupportAsDev` — dev workflow from app code

Use on staff/dev `User` models whose IDs are in `RELEASE_SUPPORT_DEV_USER_IDS`:

```php
use Kennofizet\ReleaseSupport\Traits\HasReleaseSupportReports;
use Kennofizet\ReleaseSupport\Traits\ManagesReleaseSupportAsDev;

class User extends Authenticatable
{
    use HasReleaseSupportReports;
    use ManagesReleaseSupportAsDev;
}

// Status (open, in_progress, resolved, closed, cancelled)
$dev->releaseSupportUpdateReportStatus($reportId, 'resolved');

// Dev comment on any report
$dev->releaseSupportCommentOnReport($reportId, 'Fixed in build 42.');

// Preview next version + waiting queue (same as GET dev/release-preview)
$preview = $dev->releaseSupportReleasePreview();

// Merge ALL waiting resolved reports — auto semver, default title & release notes
$release = $dev->releaseSupportMergeAllWaitingReports();

// Optional overrides
$release = $dev->releaseSupportMergeAllWaitingReports([
    'title' => 'Hotfix '.$preview['next_version'],
    'content' => $preview['suggested_content'],
    'is_force' => true,
]);

// Merge only selected report IDs (empty title/content → package defaults)
$release = $dev->releaseSupportMergeReports([12, 15, 18]);

if ($dev->isReleaseSupportDev()) { /* ... */ }
```

Service equivalent (e.g. Artisan command without a User instance):

```php
app(ReleaseSupportService::class)->createVersionReleaseMergeAllWaiting($actorUserId);
```

---

## Report statuses

| Status | Meaning |
|--------|---------|
| `open` | New report |
| `in_progress` | Being handled |
| `resolved` | Fixed / answered |
| `closed` | Closed without shipping in a release (not merged) |
| `cancelled` | Refused / cancelled (not merged) |

**Merge eligible**: `resolved` only, and not yet linked to a version (`version_update_id` is null).  
**Waiting merge**: resolved reports in that queue.  
**Open / in progress** reports do **not** block creating a release; pick which **resolved** reports to merge.  
**Closed** and **cancelled** are not merge-eligible.

---

## Version releases (merge workflow)

Releases work like merging completed “pull requests” into the next semver:

1. Auto version: `0.0.1` → `0.0.2` → … → `0.0.99` → `0.1.0` (see `SemverHelper::nextReleaseVersion()`).
2. **Create release** when at least one **resolved** report is waiting merge (open reports are allowed).
3. On create, pass `report_ids` — only selected resolved reports are merged; release notes default to their titles.
4. `GET dev/release-preview` returns `can_create`, `blockers`, `next_version`, `waiting_reports`, and suggested title/content.
5. `POST dev/version-updates` creates the release (body: `title`, `content`, `is_active`, `is_force` — **no** manual `version`).
6. `GET dev/version-updates/{id}` returns merged reports (PR-style list) for the detail UI.

Run migrations after upgrade (adds `version_update_id`, `merged_at`, and index `rs_reports_waiting_merge_idx` on reports).

`RELEASE_SUPPORT_WAITING_MERGE_PREVIEW_LIMIT` (default `100`) caps rows in `dev/release-preview` waiting list; merge on create still includes **all** waiting reports.

---

## Security

All routes use middleware: `knf.core.token`, **`knf.core.validator`** (sanitizes input, `per_page` / `perPage` max **50**).

| Control | Behavior |
|---------|----------|
| Report access | Owner or dev only; others get **404** (no ID enumeration) |
| Drawings upload | Data-URL images only; magic-byte check; no client file paths |
| Drawings download | Auth + filename must belong to report |
| Payload | Logs/context/meta sanitized; sensitive keys redacted |
| Webhook | HTTPS only; blocks localhost / private IPs |
| Pagination | `knf.core.validator` + `ListReportsRequest` (max 50) |
| Metrics | `days` max 365 (`DevMetricsRequest`) |

Use a **private** `drawings_disk` (`local`) in production. If you publish `config/release-support.php`, include `report_tags` from the package default.

**Note:** Browser `<img src>` cannot send `X-Knf-Token`. Private-disk screenshots are served only via authenticated API URLs — they will not load in `<img>` unless you add **signed URL** support or use the `public` disk (less secure). Plan UI accordingly (e.g. fetch blob with axios + `Authorization` / token header, then `URL.createObjectURL`).

---

## API

Base path: `{packages-core.api_prefix}/{release-support.api_prefix}/`  
Example: `api/knf/release-support/`

Requires header **`X-Knf-Token`** (packages-core). No zone header required.

| Method | Endpoint | Access | Description |
|--------|----------|--------|-------------|
| GET | `bootstrap` | Authenticated | `force_show_reporter`, `capture_max_logs`, `is_dev_user`, `latest_update` |
| POST | `reports` | Authenticated | Submit issue (see body below) |
| GET | `reports/my?status=&page=&per_page=` | Authenticated | Own reports (paginated, `per_page` ≤ 50) |
| GET | `version-updates?page=&per_page=` | Authenticated | **Read-only** active release notes (users) |
| GET | `version-updates/{id}` | Authenticated | **Read-only** release detail (users) |
| GET | `reports/{reportId}` | Owner or dev | Detail with comments |
| GET | `dev/reports?status=&page=&per_page=` | Dev user | All reports (paginated) |
| POST | `dev/reports/{reportId}/status` | Dev user | Body: `{ "status": "in_progress" }` |
| POST | `dev/reports/{reportId}/comments` | Dev user | Body: `{ "comment": "..." }` |
| GET | `dev/release-preview` | Dev user | Next version, blockers, waiting queue, suggested notes |
| GET | `dev/version-updates?page=&per_page=` | Dev user | Paginated releases (`merges_count` per row) |
| GET | `dev/version-updates/{id}` | Dev user | Release detail with `merges` (merged reports) |
| POST | `dev/version-updates` | Dev user | **Merge & publish** release (auto version + merge all waiting) |
| PUT | `dev/version-updates/{id}` | Dev user | Edit title, content, flags (version is read-only) |
| GET | `dev/metrics?days=30` | Dev user | Reports/day, median hours to resolved, open count |

`GET /bootstrap` accepts optional `?app_version=` and returns `version_outdated`, `version_compare`, `drawings_storage`.

`POST /reports` is rate-limited separately (`report_submit_rate_limit`). Duplicate submits within `dedupe_window_minutes` are rejected.

### POST `reports` body

```json
{
  "title": "Button submit fails",
  "description": "Happens after login",
  "app_version": "1.4.0",
  "captured_logs": [],
  "captured_context": {},
  "drawings": ["data:image/png;base64,..."],
  "meta": {}
}
```

`title` is required. Arrays default to `[]` if omitted.

---

## Service (direct use in host app)

```php
use Kennofizet\ReleaseSupport\Services\ReleaseSupportService;

$service = app(ReleaseSupportService::class);

if ($service->isDevUser()) {
    // ...
}

$payload = $service->getBootstrapPayload();
```

---

## Summary

| Step | Action |
|------|--------|
| Install | `composer require kennofizet/release-support-backend` |
| Config | `php artisan vendor:publish --tag=release-support-config` |
| Migrations | `php artisan vendor:publish --tag=release-support-migrations` then `php artisan migrate` |
| Dev users | Set `RELEASE_SUPPORT_DEV_USER_IDS` |
| Hooks | `after_submitted_listeners`, `after_status_changed_listeners`, `after_comment_added_listeners`, `after_version_released_listeners` |
| Model (optional) | `HasReleaseSupportReports` (reporter), `ManagesReleaseSupportAsDev` (staff) on User |

Pair with **@kennofizet/release-support-frontend** for the reporter UI and background capture.
