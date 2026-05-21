<?php declare(strict_types=1);

return [
    'api_prefix' => env('RELEASE_SUPPORT_API_PREFIX', 'release-support'),
    'reports_table' => env('RELEASE_SUPPORT_REPORTS_TABLE', 'release_support_reports'),
    'report_comments_table' => env('RELEASE_SUPPORT_REPORT_COMMENTS_TABLE', 'release_support_report_comments'),
    'version_updates_table' => env('RELEASE_SUPPORT_VERSION_UPDATES_TABLE', 'release_support_version_updates'),
    'report_status_logs_table' => env('RELEASE_SUPPORT_REPORT_STATUS_LOGS_TABLE', 'release_support_report_status_logs'),

    'force_show_reporter' => (bool) env('RELEASE_SUPPORT_FORCE_SHOW_REPORTER', false),

    // drawings_storage: disk (recommended) saves image files; DB stores paths only. json = legacy base64 in DB.
    'drawings_storage' => env('RELEASE_SUPPORT_DRAWINGS_STORAGE', 'disk'),
    // Use a private disk (e.g. local); public disk exposes screenshots without auth.
    'drawings_disk' => env('RELEASE_SUPPORT_DRAWINGS_DISK', 'local'),
    'drawings_path' => env('RELEASE_SUPPORT_DRAWINGS_PATH', 'release-support/drawings'),
    // Max decoded bytes per drawing when saving to disk (default 5MB).
    'max_drawing_bytes' => (int) env('RELEASE_SUPPORT_MAX_DRAWING_BYTES', 5 * 1024 * 1024),
    'max_drawings_per_report' => (int) env('RELEASE_SUPPORT_MAX_DRAWINGS_PER_REPORT', 10),

    'report_submit_rate_limit' => (int) env('RELEASE_SUPPORT_REPORT_SUBMIT_RATE_LIMIT', 10),
    'dedupe_enabled' => (bool) env('RELEASE_SUPPORT_DEDUPE_ENABLED', true),
    'dedupe_window_minutes' => (int) env('RELEASE_SUPPORT_DEDUPE_WINDOW_MINUTES', 5),

    'webhook_url' => env('RELEASE_SUPPORT_WEBHOOK_URL'),

    'dev_user_ids' => array_values(array_filter(array_map(
        static fn ($v) => (int) trim((string) $v),
        explode(',', (string) env('RELEASE_SUPPORT_DEV_USER_IDS', ''))
    ))),

    'capture_max_logs' => (int) env('RELEASE_SUPPORT_CAPTURE_MAX_LOGS', 200),

    // Max rows returned in dev release-preview waiting queue (count is still full).
    'waiting_merge_preview_limit' => (int) env('RELEASE_SUPPORT_WAITING_MERGE_PREVIEW_LIMIT', 100),

    'report_tags' => ['bug', 'feature', 'question', 'improvement', 'other'],

    'report_event_class' => env('RELEASE_SUPPORT_REPORT_EVENT_CLASS', \Kennofizet\ReleaseSupport\Events\IssueReportSubmitted::class),

    'report_status_changed_event_class' => env(
        'RELEASE_SUPPORT_STATUS_CHANGED_EVENT_CLASS',
        \Kennofizet\ReleaseSupport\Events\ReportStatusChanged::class,
    ),

    'report_comment_added_event_class' => env(
        'RELEASE_SUPPORT_COMMENT_ADDED_EVENT_CLASS',
        \Kennofizet\ReleaseSupport\Events\ReportCommentAdded::class,
    ),

    'version_released_event_class' => env(
        'RELEASE_SUPPORT_VERSION_RELEASED_EVENT_CLASS',
        \Kennofizet\ReleaseSupport\Events\VersionReleased::class,
    ),

    'queue_listeners' => (bool) env('RELEASE_SUPPORT_QUEUE_LISTENERS', false),

    'after_submitted_listeners' => [],

    'after_status_changed_listeners' => [],

    'after_comment_added_listeners' => [],

    'after_version_released_listeners' => [],
];
