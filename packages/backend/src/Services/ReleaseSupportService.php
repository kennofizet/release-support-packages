<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Services;

use Kennofizet\PackagesCore\Core\Model\BaseModelActions;
use Kennofizet\PackagesCore\Models\User;
use Kennofizet\ReleaseSupport\Contracts\AfterIssueReportSubmittedListener;
use Kennofizet\ReleaseSupport\Events\IssueReportSubmitted;
use Kennofizet\ReleaseSupport\Jobs\RunAfterIssueReportSubmittedListeners;
use Kennofizet\ReleaseSupport\Models\ReleaseSupportReport;
use Kennofizet\ReleaseSupport\Models\ReleaseSupportReportComment;
use Kennofizet\ReleaseSupport\Models\ReleaseSupportReportStatusLog;
use Kennofizet\ReleaseSupport\Models\ReleaseSupportVersionUpdate;
use Kennofizet\ReleaseSupport\Support\SensitiveDataSanitizer;
use Kennofizet\ReleaseSupport\Support\SemverHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

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
        $query = $this->reportListQuery()
            ->where('user_id', $userId)
            ->orderByDesc('id');
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        return $this->paginateReportList($query, $perPage);
    }

    public function getAllReports(?string $status = null, int $perPage = 20): array
    {
        $query = $this->reportListQuery()->orderByDesc('id');
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
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
        $allowed = [
            ReleaseSupportReport::STATUS_OPEN,
            ReleaseSupportReport::STATUS_IN_PROGRESS,
            ReleaseSupportReport::STATUS_RESOLVED,
            ReleaseSupportReport::STATUS_CLOSED,
        ];
        if (!in_array($status, $allowed, true)) {
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

        return $this->formatCommentRecord($entity, $nameMap);
    }

    public function listVersionUpdates(int $perPage = 20): array
    {
        $pager = ReleaseSupportVersionUpdate::query()->orderByDesc('id')->paginate(max(1, $perPage));
        return $this->formatPager($pager, false);
    }

    public function saveVersionUpdate(array $payload, ?int $id = null): ReleaseSupportVersionUpdate
    {
        $version = trim((string) ($payload['version'] ?? ''));
        if ($version === '') {
            throw new \InvalidArgumentException('version is required');
        }

        $entity = $id ? ReleaseSupportVersionUpdate::query()->findOrFail($id) : new ReleaseSupportVersionUpdate();
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
        if (config('release-support.queue_after_report_listeners', false)) {
            RunAfterIssueReportSubmittedListeners::dispatch((int) $report->id);
            return;
        }

        $listeners = config('release-support.after_report_submitted_listeners', []);
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
        ];
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
