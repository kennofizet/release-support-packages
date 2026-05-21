<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Services;

use Kennofizet\PackagesCore\Core\Model\BaseModelActions;
use Kennofizet\PackagesCore\Models\User;
use Kennofizet\ReleaseSupport\Contracts\AfterIssueReportSubmittedListener;
use Kennofizet\ReleaseSupport\Events\IssueReportSubmitted;
use Kennofizet\ReleaseSupport\Events\ReportCommentAdded;
use Kennofizet\ReleaseSupport\Events\ReportStatusChanged;
use Kennofizet\ReleaseSupport\Events\VersionReleased;
use Kennofizet\ReleaseSupport\Jobs\RunAfterIssueReportSubmittedListeners;
use Kennofizet\ReleaseSupport\Models\ReleaseSupportReport;
use Kennofizet\ReleaseSupport\Models\ReleaseSupportReportComment;
use Kennofizet\ReleaseSupport\Models\ReleaseSupportReportStatusLog;
use Kennofizet\ReleaseSupport\Models\ReleaseSupportVersionUpdate;
use Kennofizet\ReleaseSupport\Support\SensitiveDataSanitizer;
use Kennofizet\ReleaseSupport\Support\SemverHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReleaseSupportService
{
    /** @var list<string> */
    private const DEFAULT_REPORT_TAGS = ['bug', 'feature', 'question', 'improvement', 'other'];

    /** @var list<string> Columns safe for paginated list queries (excludes large JSON blobs). */
    private const REPORT_LIST_COLUMNS = [
        'id',
        'user_id',
        'title',
        'status',
        'app_version',
        'created_at',
        'meta',
    ];

    public function __construct(
        private readonly DrawingStorageService $drawingStorage
    ) {
    }

    public function isDevUser(?int $userId = null): bool
    {
        $resolvedUserId = $userId ?? BaseModelActions::currentUserId();
        if ($resolvedUserId === null) {
            return false;
        }
        $devUserIds = array_map(static fn ($v) => (int) $v, (array) config('release-support.dev_user_ids', []));
        return in_array($resolvedUserId, $devUserIds, true);
    }

    public function getBootstrapPayload(?string $clientAppVersion = null): array
    {
        $latestActive = ReleaseSupportVersionUpdate::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        $latestVersion = $latestActive ? (string) $latestActive->version : null;
        $clientVersion = $clientAppVersion !== null && $clientAppVersion !== ''
            ? $clientAppVersion
            : null;

        $versionCompare = null;
        $versionOutdated = null;
        if ($clientVersion !== null && $latestVersion !== null) {
            $versionCompare = SemverHelper::compare($clientVersion, $latestVersion);
            $versionOutdated = SemverHelper::isLessThan($clientVersion, $latestVersion);
        }

        return [
            'force_show_reporter' => (bool) config('release-support.force_show_reporter', false),
            'capture_max_logs' => (int) config('release-support.capture_max_logs', 200),
            'is_dev_user' => $this->isDevUser(),
            'drawings_storage' => (string) config('release-support.drawings_storage', 'disk'),
            'report_tags' => array_values((array) config('release-support.report_tags', [])),
            'latest_update' => $latestActive ? [
                'id' => (int) $latestActive->id,
                'version' => (string) $latestActive->version,
                'title' => (string) $latestActive->title,
                'content' => (string) $latestActive->content,
                'is_force' => (bool) $latestActive->is_force,
            ] : null,
            'version_compare' => $versionCompare,
            'version_outdated' => $versionOutdated,
        ];
    }

    public function createReport(array $payload, ?int $userId = null): ReleaseSupportReport
    {
        $resolvedUserId = $userId ?? BaseModelActions::currentUserId();
        if ($resolvedUserId === null) {
            throw new \RuntimeException('Current user is required');
        }

        $this->assertNotDuplicate($resolvedUserId, $payload);

        $maxLogs = (int) config('release-support.capture_max_logs', 200);
        $capturedLogs = SensitiveDataSanitizer::sanitizeCapturedLogs(
            $this->normalizeArrayPayload($payload['captured_logs'] ?? []),
            $maxLogs,
        );
        $capturedContext = SensitiveDataSanitizer::sanitizeCapturedContext(
            $this->normalizeArrayPayload($payload['captured_context'] ?? []),
        );

        $meta = SensitiveDataSanitizer::sanitizeReportMeta($this->normalizeArrayPayload($payload['meta'] ?? []));
        $tag = (string) ($payload['tag'] ?? $meta['tag'] ?? '');
        $meta['tag'] = $this->normalizeReportTag($tag !== '' ? $tag : 'other');
        $meta['submit_fingerprint'] = $this->buildFingerprint($resolvedUserId, $payload);

        $report = ReleaseSupportReport::query()->create([
            'user_id' => $resolvedUserId,
            'title' => (string) ($payload['title'] ?? ''),
            'description' => (string) ($payload['description'] ?? ''),
            'status' => ReleaseSupportReport::STATUS_OPEN,
            'app_version' => (string) ($payload['app_version'] ?? ''),
            'captured_logs' => $capturedLogs,
            'captured_context' => $capturedContext,
            'drawings' => [],
            'meta' => $meta,
        ]);

        $drawings = $this->normalizeArrayPayload($payload['drawings'] ?? []);
        $storedDrawings = $this->drawingStorage->persistForReport((int) $report->id, $drawings);
        $report->drawings = $storedDrawings;
        $report->save();

        $this->logStatusChange($report, null, ReleaseSupportReport::STATUS_OPEN, $resolvedUserId);

        $eventClass = config('release-support.report_event_class', IssueReportSubmitted::class);
        event(new $eventClass($report));
        $this->runAfterSubmittedListeners($report);

        return $report->fresh(['comments', 'statusLogs']);
    }

    public function getMyReports(int $userId, ?string $status = null, int $perPage = 20): array
    {
        $query = $this->reportListQuery()->where('user_id', $userId);
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        } else {
            $this->applyReportListDisplayOrder($query);
        }

        return $this->paginateReportList($query, $perPage);
    }

    public function getAllReports(?string $status = null, int $perPage = 20): array
    {
        $query = $this->reportListQuery();
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        } else {
            $this->applyReportListDisplayOrder($query);
        }

        return $this->paginateReportList($query, $perPage);
    }

    public function canAccessReport(int $reportId, ?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }
        if ($this->isDevUser($userId)) {
            return ReleaseSupportReport::query()->where('id', $reportId)->exists();
        }

        return ReleaseSupportReport::query()
            ->where('id', $reportId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function getReportDetail(int $reportId, bool $includeCapturedLogs = true): ?array
    {
        $report = ReleaseSupportReport::query()
            ->with([
                'comments' => static fn ($q) => $q->orderBy('id'),
                'statusLogs' => static fn ($q) => $q->orderBy('id'),
            ])
            ->find($reportId);

        if (!$report) {
            return null;
        }

        return $this->formatReportDetail($report, $includeCapturedLogs);
    }

    public function updateReportStatus(int $reportId, string $status, ?int $actorUserId = null): ReleaseSupportReport
    {
        if (!in_array($status, ReleaseSupportReport::allStatuses(), true)) {
            throw new \InvalidArgumentException('Invalid status');
        }

        $report = ReleaseSupportReport::query()->findOrFail($reportId);
        $from = (string) $report->status;
        if ($from === $status) {
            return $report;
        }

        $report->status = $status;
        if ($status === ReleaseSupportReport::STATUS_RESOLVED) {
            $report->resolved_at = Carbon::now();
        }
        $report->save();

        $this->logStatusChange($report, $from, $status, $actorUserId ?? BaseModelActions::currentUserId());

        $actorId = $actorUserId ?? BaseModelActions::currentUserId();
        $this->dispatchStatusChangedEvent($report, $from, $status, $actorId);
        $this->runConfiguredListeners('release-support.after_status_changed_listeners', $report, [
            'from_status' => $from,
            'to_status' => $status,
            'actor_user_id' => $actorId,
        ]);

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    public function addComment(int $reportId, int $userId, string $comment): array
    {
        if (trim($comment) === '') {
            throw new \InvalidArgumentException('comment is required');
        }
        ReleaseSupportReport::query()->findOrFail($reportId);
        $entity = ReleaseSupportReportComment::query()->create([
            'report_id' => $reportId,
            'user_id' => $userId,
            'comment' => trim($comment),
            'meta' => null,
        ]);

        $nameMap = $this->resolveUserDisplayNames([$userId]);

        $formatted = $this->formatCommentRecord($entity, $nameMap);

        $report = ReleaseSupportReport::query()->findOrFail($reportId);
        $this->dispatchCommentAddedEvent($report, $entity);
        $this->runConfiguredListeners('release-support.after_comment_added_listeners', $report, [
            'comment' => $formatted,
            'actor_user_id' => $userId,
        ]);

        return $formatted;
    }

    public function listPublicVersionUpdates(int $perPage = 20): array
    {
        $pager = ReleaseSupportVersionUpdate::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->paginate(max(1, $perPage));

        $items = [];
        foreach ($pager->items() as $row) {
            if ($row instanceof ReleaseSupportVersionUpdate) {
                $items[] = $this->formatPublicVersionSummary($row);
            }
        }

        return [
            'items' => array_values(array_filter($items)),
            'meta' => [
                'current_page' => (int) $pager->currentPage(),
                'last_page' => (int) $pager->lastPage(),
                'per_page' => (int) $pager->perPage(),
                'total' => (int) $pager->total(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPublicVersionUpdateDetail(int $id): ?array
    {
        $entity = ReleaseSupportVersionUpdate::query()
            ->where('is_active', true)
            ->find($id);

        if (!$entity) {
            return null;
        }

        return $this->formatPublicVersionDetail($entity);
    }

    public function listVersionUpdates(int $perPage = 20): array
    {
        $pager = ReleaseSupportVersionUpdate::query()
            ->withCount('mergedReports')
            ->orderByDesc('id')
            ->paginate(max(1, $perPage));

        $items = [];
        foreach ($pager->items() as $row) {
            if ($row instanceof ReleaseSupportVersionUpdate) {
                $items[] = $this->formatVersionUpdateSummary($row);
            }
        }

        return [
            'items' => array_values(array_filter($items)),
            'meta' => [
                'current_page' => (int) $pager->currentPage(),
                'last_page' => (int) $pager->lastPage(),
                'per_page' => (int) $pager->perPage(),
                'total' => (int) $pager->total(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getVersionUpdateDetail(int $id): ?array
    {
        $entity = ReleaseSupportVersionUpdate::query()->with([
            'mergedReports' => fn ($q) => $this->applyMergedReportsListQuery($q),
        ])->find($id);

        return $entity ? $this->formatVersionUpdateDetail($entity) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getReleasePreview(): array
    {
        $blockers = $this->getReleaseBlockers();
        $waitingCount = $blockers['waiting_merge_count'];
        $waiting = $this->getWaitingMergeReports();
        $previewLimit = $this->waitingMergePreviewLimit();
        $latest = ReleaseSupportVersionUpdate::query()->orderByDesc('id')->first();
        $nextVersion = SemverHelper::nextReleaseVersion($latest ? (string) $latest->version : null);

        $allWaitingIds = $this->getWaitingMergeReportIds();

        return [
            'can_create' => $blockers['can_create'],
            'blockers' => $blockers['reasons'],
            'next_version' => $nextVersion,
            'waiting_merge_count' => $waitingCount,
            'waiting_report_ids' => $allWaitingIds,
            'waiting_reports' => $waiting,
            'waiting_reports_truncated' => $waitingCount > $previewLimit,
            'suggested_title' => $this->buildReleaseTitle($nextVersion, $waitingCount),
            'suggested_content' => $this->buildReleaseNotesFromReports($waiting),
        ];
    }

    /**
     * Merge every resolved report waiting for a release (same defaults as dev release preview).
     *
     * @param  array<string, mixed>  $overrides  Optional keys: title, content, is_active, is_force, meta
     * @return array<string, mixed> Version release detail (same shape as createVersionRelease)
     */
    public function createVersionReleaseMergeAllWaiting(?int $actorUserId = null, array $overrides = []): array
    {
        $preview = $this->getReleasePreview();
        if (!$preview['can_create']) {
            $blockers = $preview['blockers'] ?? [];
            $message = is_array($blockers) && $blockers !== []
                ? implode(' ', array_map('strval', $blockers))
                : 'Cannot create a release.';
            throw new \RuntimeException($message);
        }

        $ids = $preview['waiting_report_ids'] ?? [];
        if (!is_array($ids) || $ids === []) {
            throw new \InvalidArgumentException('no_waiting_reports');
        }

        return $this->createVersionRelease([
            'report_ids' => array_values(array_map(static fn ($id) => (int) $id, $ids)),
            'title' => $overrides['title'] ?? $preview['suggested_title'] ?? '',
            'content' => $overrides['content'] ?? $preview['suggested_content'] ?? '',
            'is_active' => $overrides['is_active'] ?? true,
            'is_force' => $overrides['is_force'] ?? false,
            'meta' => $overrides['meta'] ?? [],
        ], $actorUserId);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createVersionRelease(array $payload, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($payload, $actorUserId) {
            $waiting = $this->resolveMergeReportsForRelease($payload, lock: true);
            $mergedIds = $waiting->map(static fn (ReleaseSupportReport $r) => (int) $r->id)->all();

            $latest = ReleaseSupportVersionUpdate::query()->orderByDesc('id')->lockForUpdate()->first();
            $version = SemverHelper::nextReleaseVersion($latest ? (string) $latest->version : null);
            $noteRows = $waiting->map(fn (ReleaseSupportReport $r) => $this->formatMergedReportRow($r))->all();
            $notes = $this->buildReleaseNotesFromReports($noteRows);

            $title = trim((string) ($payload['title'] ?? ''));
            if ($title === '') {
                $title = $this->buildReleaseTitle($version, count($mergedIds));
            }

            $content = trim((string) ($payload['content'] ?? ''));
            if ($content === '') {
                $content = $notes;
            }

            $entity = new ReleaseSupportVersionUpdate();
            $entity->version = $version;
            $entity->title = $title;
            $entity->content = $content;
            $entity->is_force = (bool) ($payload['is_force'] ?? false);
            $entity->is_active = (bool) ($payload['is_active'] ?? true);
            $entity->meta = SensitiveDataSanitizer::sanitizeLooseMeta(
                $this->normalizeArrayPayload($payload['meta'] ?? []),
            );
            $entity->save();

            $now = Carbon::now();
            ReleaseSupportReport::query()
                ->whereIn('id', $mergedIds)
                ->update([
                    'version_update_id' => (int) $entity->id,
                    'merged_at' => $now,
                    'updated_at' => $now,
                ]);

            $entity->loadCount('mergedReports');
            $entity->load(['mergedReports' => fn ($q) => $this->applyMergedReportsListQuery($q)]);

            $actorId = $actorUserId ?? BaseModelActions::currentUserId();
            $this->dispatchVersionReleasedEvent($entity, $mergedIds, $actorId);
            $this->runConfiguredListeners('release-support.after_version_released_listeners', $entity, [
                'merged_report_ids' => $mergedIds,
                'actor_user_id' => $actorId,
            ]);

            return $this->formatVersionUpdateDetail($entity);
        });
    }

    public function saveVersionUpdate(array $payload, ?int $id = null): ReleaseSupportVersionUpdate
    {
        $entity = $id ? ReleaseSupportVersionUpdate::query()->findOrFail($id) : new ReleaseSupportVersionUpdate();

        if ($id) {
            $version = (string) $entity->version;
        } else {
            $version = trim((string) ($payload['version'] ?? ''));
            if ($version === '') {
                throw new \InvalidArgumentException('version is required');
            }
        }

        $entity->version = $version;
        $entity->title = (string) ($payload['title'] ?? '');
        $entity->content = (string) ($payload['content'] ?? '');
        $entity->is_force = (bool) ($payload['is_force'] ?? false);
        $entity->is_active = (bool) ($payload['is_active'] ?? true);
        $entity->meta = SensitiveDataSanitizer::sanitizeLooseMeta(
            $this->normalizeArrayPayload($payload['meta'] ?? []),
        );
        $entity->save();

        return $entity;
    }

    /**
     * @return array{reports_per_day: array<int, array{date: string, count: int}>, median_hours_to_resolved: float|null, open_count: int}
     */
    public function getDevMetrics(int $days = 30): array
    {
        $days = min(max(1, $days), 365);
        $since = Carbon::now()->subDays($days)->startOfDay();

        $perDay = ReleaseSupportReport::query()
            ->selectRaw('DATE(created_at) as report_date, COUNT(*) as total')
            ->where('created_at', '>=', $since)
            ->groupBy('report_date')
            ->orderBy('report_date')
            ->get()
            ->map(static fn ($row) => [
                'date' => (string) $row->report_date,
                'count' => (int) $row->total,
            ])
            ->values()
            ->all();

        $openCount = (int) ReleaseSupportReport::query()
            ->whereIn('status', [
                ReleaseSupportReport::STATUS_OPEN,
                ReleaseSupportReport::STATUS_IN_PROGRESS,
            ])
            ->count();

        $resolvedRows = ReleaseSupportReport::query()
            ->where('status', ReleaseSupportReport::STATUS_RESOLVED)
            ->whereNotNull('resolved_at')
            ->where('created_at', '>=', $since)
            ->get(['created_at', 'resolved_at']);

        $medianHours = null;
        if ($resolvedRows->isNotEmpty()) {
            $hours = $resolvedRows->map(static function (ReleaseSupportReport $r) {
                return $r->created_at->diffInMinutes($r->resolved_at) / 60;
            })->sort()->values();
            $mid = (int) floor($hours->count() / 2);
            $medianHours = $hours->count() % 2 === 0
                ? round(($hours[$mid - 1] + $hours[$mid]) / 2, 2)
                : round((float) $hours[$mid], 2);
        }

        return [
            'reports_per_day' => $perDay,
            'median_hours_to_resolved' => $medianHours,
            'open_count' => $openCount,
        ];
    }

    private function assertNotDuplicate(int $userId, array $payload): void
    {
        if (!config('release-support.dedupe_enabled', true)) {
            return;
        }

        $window = (int) config('release-support.dedupe_window_minutes', 5);
        $fingerprint = $this->buildFingerprint($userId, $payload);

        $exists = ReleaseSupportReport::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subMinutes($window))
            ->where('meta->submit_fingerprint', $fingerprint)
            ->exists();

        if ($exists) {
            throw new \RuntimeException('Duplicate report submitted too soon. Please wait before submitting again.');
        }
    }

    private function buildFingerprint(int $userId, array $payload): string
    {
        $title = strtolower(trim((string) ($payload['title'] ?? '')));
        $version = (string) ($payload['app_version'] ?? '');
        $href = '';
        $ctx = $payload['captured_context'] ?? [];
        if (is_array($ctx) && isset($ctx['href'])) {
            $href = (string) $ctx['href'];
        }
        return hash('sha256', $userId . '|' . $title . '|' . $version . '|' . $href);
    }

    private function logStatusChange(
        ReleaseSupportReport $report,
        ?string $from,
        string $to,
        ?int $userId
    ): void {
        ReleaseSupportReportStatusLog::query()->create([
            'report_id' => (int) $report->id,
            'user_id' => $userId,
            'from_status' => $from,
            'to_status' => $to,
        ]);
    }

    private function runAfterSubmittedListeners(ReleaseSupportReport $report): void
    {
        if (config('release-support.queue_listeners', false)) {
            RunAfterIssueReportSubmittedListeners::dispatch((int) $report->id);
            return;
        }

        $listeners = config('release-support.after_submitted_listeners', []);
        if (!is_array($listeners)) {
            return;
        }
        foreach ($listeners as $class) {
            if (!is_string($class) || !class_exists($class)) {
                continue;
            }
            $instance = app()->make($class);
            if ($instance instanceof AfterIssueReportSubmittedListener) {
                $instance->handle($report);
            }
        }
    }

    private function formatReportDetail(ReleaseSupportReport $report, bool $includeCapturedLogs = true): array
    {
        $userIds = [(int) $report->user_id];
        foreach ($report->comments as $comment) {
            $userIds[] = (int) $comment->user_id;
        }
        foreach ($report->statusLogs as $log) {
            if ($log->user_id !== null) {
                $userIds[] = (int) $log->user_id;
            }
        }
        $nameMap = $this->resolveUserDisplayNames($userIds);

        $timeline = [];

        $timeline[] = [
            'type' => 'created',
            'at' => $report->created_at?->toIso8601String(),
            'status' => ReleaseSupportReport::STATUS_OPEN,
            'user_id' => (int) $report->user_id,
            'user_name' => $nameMap[(int) $report->user_id] ?? null,
        ];

        foreach ($report->statusLogs as $log) {
            $actorId = $log->user_id !== null ? (int) $log->user_id : null;
            $timeline[] = [
                'type' => 'status',
                'at' => $log->created_at?->toIso8601String(),
                'from_status' => $log->from_status,
                'to_status' => $log->to_status,
                'user_id' => $actorId,
                'user_name' => $actorId !== null ? ($nameMap[$actorId] ?? null) : null,
            ];
        }

        foreach ($report->comments as $comment) {
            $commentUserId = (int) $comment->user_id;
            $timeline[] = [
                'type' => 'comment',
                'at' => $comment->created_at?->toIso8601String(),
                'user_id' => $commentUserId,
                'user_name' => $nameMap[$commentUserId] ?? null,
                'comment' => (string) $comment->comment,
            ];
        }

        usort($timeline, static function (array $a, array $b): int {
            return strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? ''));
        });

        return [
            'id' => (int) $report->id,
            'user_id' => (int) $report->user_id,
            'reporter_name' => $nameMap[(int) $report->user_id] ?? null,
            'title' => (string) $report->title,
            'description' => (string) $report->description,
            'status' => (string) $report->status,
            'app_version' => (string) $report->app_version,
            'resolved_at' => $report->resolved_at?->toIso8601String(),
            'version_update_id' => $report->version_update_id !== null ? (int) $report->version_update_id : null,
            'merged_at' => $report->merged_at?->toIso8601String(),
            'merge_state' => $this->reportMergeState($report),
            'captured_logs' => $includeCapturedLogs ? ($report->captured_logs ?? []) : [],
            'captured_context' => $report->captured_context ?? [],
            'drawings' => $this->drawingStorage->urlsForResponse($report->drawings ?? [], (int) $report->id),
            'meta' => $report->meta ?? [],
            'tag' => $this->reportTagFromMeta($report->meta),
            'comments' => $this->formatCommentsCollection($report->comments, $nameMap),
            'timeline' => $timeline,
            'created_at' => $report->created_at?->toIso8601String(),
            'updated_at' => $report->updated_at?->toIso8601String(),
        ];
    }

    private function normalizeArrayPayload(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * List query without large JSON columns (avoids MySQL sort-memory errors).
     */
    private function reportListQuery()
    {
        $table = ReleaseSupportReport::getTableName();

        return ReleaseSupportReport::query()->select(
            $this->qualifiedReportListColumns($table)
        );
    }

    /**
     * Active reports first (open, in_progress), inactive at bottom; newest id within each group.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ReleaseSupportReport>  $query
     */
    private function applyReportListDisplayOrder($query): void
    {
        $inactive = ReleaseSupportReport::inactiveStatuses();
        $bindings = implode(', ', array_fill(0, count($inactive), '?'));
        $query
            ->orderByRaw(
                "CASE WHEN status IN ({$bindings}) THEN 1 ELSE 0 END ASC",
                $inactive,
            )
            ->orderByDesc('id');
    }

    /**
     * @return list<string>
     */
    private function qualifiedReportListColumns(string $table): array
    {
        return array_map(
            static fn (string $column) => "{$table}.{$column}",
            self::REPORT_LIST_COLUMNS,
        );
    }

    private function paginateReportList($query, int $perPage): array
    {
        $pager = $query->paginate(max(1, $perPage));
        if ($pager->getCollection()->isNotEmpty()) {
            $pager->getCollection()->loadCount('comments');
        }

        return $this->formatPager($pager);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $pager
     */
    private function formatPager(LengthAwarePaginator $pager, bool $mapReports = true): array
    {
        $items = $pager->items();
        if ($mapReports) {
            $items = array_map(function ($r) {
                return $r instanceof ReleaseSupportReport
                    ? $this->formatReportSummary($r)
                    : $r;
            }, $items);
            if ($items !== []) {
                $userIds = array_map(
                    static fn (array $item): int => (int) ($item['user_id'] ?? 0),
                    $items,
                );
                $nameMap = $this->resolveUserDisplayNames($userIds);
                $items = array_map(static function (array $item) use ($nameMap): array {
                    $uid = (int) ($item['user_id'] ?? 0);
                    $item['reporter_name'] = $nameMap[$uid] ?? null;

                    return $item;
                }, $items);
            }
        }

        return [
            'items' => $items,
            'meta' => [
                'current_page' => (int) $pager->currentPage(),
                'last_page' => (int) $pager->lastPage(),
                'per_page' => (int) $pager->perPage(),
                'total' => (int) $pager->total(),
            ],
        ];
    }

    private function formatReportSummary(ReleaseSupportReport $report): array
    {
        return [
            'id' => (int) $report->id,
            'user_id' => (int) $report->user_id,
            'title' => (string) $report->title,
            'status' => (string) $report->status,
            'app_version' => (string) $report->app_version,
            'comments_count' => (int) ($report->comments_count ?? 0),
            'created_at' => $report->created_at?->toIso8601String(),
            'tag' => $this->reportTagFromMeta($report->meta),
            'version_update_id' => $report->version_update_id !== null ? (int) $report->version_update_id : null,
            'merged_at' => $report->merged_at?->toIso8601String(),
            'merge_state' => $this->reportMergeState($report),
        ];
    }

    /**
     * @return Builder<ReleaseSupportReport>
     */
    private function waitingMergePreviewLimit(): int
    {
        return max(1, (int) config('release-support.waiting_merge_preview_limit', 100));
    }

    /**
     * @return Builder<ReleaseSupportReport>
     */
    private function waitingMergeReportsQuery(): Builder
    {
        return ReleaseSupportReport::query()
            ->select(ReleaseSupportReport::mergeListColumns())
            ->whereIn('status', ReleaseSupportReport::mergeEligibleStatuses())
            ->whereNull('version_update_id');
    }

    /**
     * @param  Builder<ReleaseSupportReport>|Relation<ReleaseSupportReport, mixed, mixed>  $query
     */
    private function applyMergedReportsListQuery(Builder|Relation $query): void
    {
        $query
            ->select(ReleaseSupportReport::mergeListColumns())
            ->orderByDesc('merged_at')
            ->orderByDesc('id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getWaitingMergeReports(): array
    {
        return $this->waitingMergeReportsQuery()
            ->orderByDesc('resolved_at')
            ->orderByDesc('id')
            ->limit($this->waitingMergePreviewLimit())
            ->get()
            ->map(fn (ReleaseSupportReport $r) => $this->formatMergedReportRow($r))
            ->values()
            ->all();
    }

    /**
     * @return array{can_create: bool, reasons: list<string>, active_count: int, waiting_merge_count: int}
     */
    private function getReleaseBlockers(): array
    {
        $waitingCount = (int) $this->waitingMergeReportsQuery()->count();

        $reasons = [];
        if ($waitingCount === 0) {
            $reasons[] = 'no_waiting_reports';
        }

        return [
            'can_create' => $waitingCount > 0,
            'reasons' => $reasons,
            'waiting_merge_count' => $waitingCount,
        ];
    }

    /**
     * @return list<int>
     */
    private function getWaitingMergeReportIds(): array
    {
        return $this->waitingMergeReportsQuery()
            ->orderByDesc('resolved_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return \Illuminate\Support\Collection<int, ReleaseSupportReport>
     */
    private function resolveMergeReportsForRelease(array $payload, bool $lock = false)
    {
        $raw = $payload['report_ids'] ?? null;
        if (!is_array($raw) || $raw === []) {
            throw new \InvalidArgumentException('Select at least one resolved report to merge.');
        }

        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $raw),
            static fn (int $id) => $id > 0,
        )));
        if ($ids === []) {
            throw new \InvalidArgumentException('Select at least one resolved report to merge.');
        }

        $query = $this->waitingMergeReportsQuery()
            ->whereIn('id', $ids)
            ->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        $reports = $query->get();
        if ($reports->count() !== count($ids)) {
            throw new \InvalidArgumentException('One or more selected reports are not eligible to merge.');
        }

        return $reports;
    }

    private function reportMergeState(ReleaseSupportReport $report): string
    {
        if ($report->version_update_id !== null) {
            return 'merged';
        }

        $status = (string) $report->status;

        if ($status === ReleaseSupportReport::STATUS_CANCELLED) {
            return 'cancelled';
        }
        if ($status === ReleaseSupportReport::STATUS_CLOSED) {
            return 'closed';
        }
        if ($status === ReleaseSupportReport::STATUS_RESOLVED) {
            return 'waiting_merge';
        }

        return 'active';
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMergedReportRow(ReleaseSupportReport $report): array
    {
        return [
            'id' => (int) $report->id,
            'number' => (int) $report->id,
            'title' => (string) $report->title,
            'status' => (string) $report->status,
            'tag' => $this->reportTagFromMeta($report->meta),
            'user_id' => (int) $report->user_id,
            'resolved_at' => $report->resolved_at?->toIso8601String(),
            'merged_at' => $report->merged_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $reports
     */
    private function buildReleaseNotesFromReports(array $reports): string
    {
        if ($reports === []) {
            return '';
        }

        $lines = ['## Merged reports', ''];
        foreach ($reports as $row) {
            $id = (int) ($row['id'] ?? 0);
            $title = trim((string) ($row['title'] ?? ''));
            $tag = trim((string) ($row['tag'] ?? 'other'));
            $lines[] = sprintf('- [#%d] %s (%s)', $id, $title !== '' ? $title : 'Untitled', $tag);
        }

        return implode("\n", $lines);
    }

    private function buildReleaseTitle(string $version, int $count): string
    {
        return sprintf('Release %s — %d merged report%s', $version, $count, $count === 1 ? '' : 's');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPublicVersionSummary(ReleaseSupportVersionUpdate $entity): array
    {
        $content = trim((string) $entity->content);

        return [
            'id' => (int) $entity->id,
            'version' => (string) $entity->version,
            'title' => (string) $entity->title,
            'excerpt' => $this->excerptText($content, 200),
            'created_at' => $entity->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPublicVersionDetail(ReleaseSupportVersionUpdate $entity): array
    {
        return [
            'id' => (int) $entity->id,
            'version' => (string) $entity->version,
            'title' => (string) $entity->title,
            'content' => (string) $entity->content,
            'created_at' => $entity->created_at?->toIso8601String(),
        ];
    }

    private function excerptText(string $text, int $maxLen): string
    {
        if ($text === '') {
            return '';
        }
        if (strlen($text) <= $maxLen) {
            return $text;
        }

        return rtrim(substr($text, 0, $maxLen - 1)) . '…';
    }

    /**
     * @return array<string, mixed>
     */
    private function formatVersionUpdateSummary(?ReleaseSupportVersionUpdate $entity): array
    {
        if (!$entity) {
            return [];
        }

        $mergedCount = (int) ($entity->merged_reports_count ?? $entity->mergedReports()->count());

        return [
            'id' => (int) $entity->id,
            'version' => (string) $entity->version,
            'title' => (string) $entity->title,
            'content' => (string) $entity->content,
            'is_force' => (bool) $entity->is_force,
            'is_active' => (bool) $entity->is_active,
            'merges_count' => $mergedCount,
            'created_at' => $entity->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatVersionUpdateDetail(ReleaseSupportVersionUpdate $entity): array
    {
        $summary = $this->formatVersionUpdateSummary($entity);
        $reports = $entity->relationLoaded('mergedReports')
            ? $entity->mergedReports
            : tap($entity->mergedReports(), fn ($q) => $this->applyMergedReportsListQuery($q))->get();

        $userIds = $reports->pluck('user_id')->map(static fn ($id) => (int) $id)->all();
        $nameMap = $this->resolveUserDisplayNames($userIds);

        $merges = $reports->map(function (ReleaseSupportReport $report) use ($nameMap) {
            $row = $this->formatMergedReportRow($report);
            $row['user_name'] = $nameMap[(int) $report->user_id] ?? null;

            return $row;
        })->values()->all();

        $summary['merges'] = $merges;

        return $summary;
    }

    private function dispatchStatusChangedEvent(
        ReleaseSupportReport $report,
        string $from,
        string $to,
        ?int $actorUserId,
    ): void {
        $class = (string) config('release-support.report_status_changed_event_class', ReportStatusChanged::class);
        event(new $class($report, $from, $to, $actorUserId));
    }

    private function dispatchCommentAddedEvent(
        ReleaseSupportReport $report,
        ReleaseSupportReportComment $comment,
    ): void {
        $class = (string) config('release-support.report_comment_added_event_class', ReportCommentAdded::class);
        event(new $class($report, $comment));
    }

    /**
     * @param  list<int>  $mergedReportIds
     */
    private function dispatchVersionReleasedEvent(
        ReleaseSupportVersionUpdate $versionUpdate,
        array $mergedReportIds,
        ?int $actorUserId,
    ): void {
        $class = (string) config('release-support.version_released_event_class', VersionReleased::class);
        event(new $class($versionUpdate, $mergedReportIds, $actorUserId));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function runConfiguredListeners(string $configKey, object $subject, array $context = []): void
    {
        if (config('release-support.queue_listeners', false)) {
            return;
        }

        $listeners = config($configKey, []);
        if (!is_array($listeners)) {
            return;
        }

        foreach ($listeners as $class) {
            if (!is_string($class) || !class_exists($class)) {
                continue;
            }
            $instance = app()->make($class);
            if (method_exists($instance, 'handle')) {
                $instance->handle($subject, $context);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function allowedReportTags(): array
    {
        $configured = array_map(
            static fn ($v) => strtolower(trim((string) $v)),
            (array) config('release-support.report_tags', []),
        );
        $configured = array_values(array_filter($configured, static fn ($v) => $v !== ''));

        return $configured !== [] ? $configured : self::DEFAULT_REPORT_TAGS;
    }

    private function normalizeReportTag(string $tag): string
    {
        $tag = strtolower(trim($tag));
        $allowed = $this->allowedReportTags();

        return in_array($tag, $allowed, true) ? $tag : 'other';
    }

    /**
     * @param  array<string, mixed>|string|null  $meta
     */
    private function reportTagFromMeta(mixed $meta): string
    {
        $metaArray = $this->normalizeArrayPayload(
            is_array($meta) ? $meta : (is_string($meta) && $meta !== '' ? json_decode($meta, true) : []),
        );
        $raw = (string) ($metaArray['tag'] ?? '');

        return $this->normalizeReportTag($raw !== '' ? $raw : 'other');
    }

    /**
     * @param  iterable<int, ReleaseSupportReportComment>  $comments
     * @param  array<int, string>  $nameMap
     * @return list<array<string, mixed>>
     */
    private function formatCommentsCollection(iterable $comments, array $nameMap): array
    {
        $out = [];
        foreach ($comments as $comment) {
            $out[] = $this->formatCommentRecord($comment, $nameMap);
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $nameMap
     * @return array<string, mixed>
     */
    private function formatCommentRecord(ReleaseSupportReportComment $comment, array $nameMap): array
    {
        $userId = (int) $comment->user_id;

        return [
            'id' => (int) $comment->id,
            'report_id' => (int) $comment->report_id,
            'user_id' => $userId,
            'user_name' => $nameMap[$userId] ?? null,
            'comment' => (string) $comment->comment,
            'created_at' => $comment->created_at?->toIso8601String(),
            'updated_at' => $comment->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, string>
     */
    private function resolveUserDisplayNames(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id) => $id > 0)));
        if ($userIds === []) {
            return [];
        }

        $nameCol = (string) config('packages-core.user_col_name', 'name');
        if ($nameCol === '') {
            return [];
        }

        $columns = ['id', $nameCol];
        $map = [];
        foreach (User::query()->whereIn('id', $userIds)->get($columns) as $user) {
            $id = (int) $user->id;
            $name = trim((string) ($user->getAttribute($nameCol) ?? ''));
            if ($name !== '') {
                $map[$id] = $name;
            }
        }

        return $map;
    }
}
