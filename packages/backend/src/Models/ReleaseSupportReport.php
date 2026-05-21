<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Models;

use Kennofizet\ReleaseSupport\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kennofizet\ReleaseSupport\Models\ReleaseSupportReportStatusLog;

class ReleaseSupportReport extends BaseModel
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function allStatuses(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
            self::STATUS_CANCELLED,
        ];
    }

    /** @return list<string> Resolved reports eligible to merge into a release (not closed/cancelled). */
    public static function mergeEligibleStatuses(): array
    {
        return [self::STATUS_RESOLVED];
    }

    /** @return list<string> @deprecated Use mergeEligibleStatuses() */
    public static function completedStatuses(): array
    {
        return self::mergeEligibleStatuses();
    }

    /** @return list<string> Shown at the bottom of report lists (open/in_progress stay on top). */
    public static function inactiveStatuses(): array
    {
        return [
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
            self::STATUS_CANCELLED,
        ];
    }

    /** Columns for merge queues / release preview (avoids loading large JSON blobs). */
    public static function mergeListColumns(): array
    {
        return [
            'id',
            'user_id',
            'title',
            'status',
            'meta',
            'resolved_at',
            'version_update_id',
            'merged_at',
        ];
    }

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
        'app_version',
        'captured_logs',
        'captured_context',
        'drawings',
        'meta',
        'resolved_at',
        'version_update_id',
        'merged_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'version_update_id' => 'integer',
        'resolved_at' => 'datetime',
        'merged_at' => 'datetime',
        'captured_logs' => 'array',
        'captured_context' => 'array',
        'drawings' => 'array',
        'meta' => 'array',
    ];

    public static function getTableName(): string
    {
        return config('release-support.reports_table', 'release_support_reports');
    }

    public function getTable(): string
    {
        return self::getTableName();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ReleaseSupportReportComment::class, 'report_id', 'id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ReleaseSupportReportStatusLog::class, 'report_id', 'id');
    }

    public function versionUpdate(): BelongsTo
    {
        return $this->belongsTo(ReleaseSupportVersionUpdate::class, 'version_update_id', 'id');
    }
}
