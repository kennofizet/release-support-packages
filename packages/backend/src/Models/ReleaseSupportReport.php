<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Models;

use Kennofizet\ReleaseSupport\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kennofizet\ReleaseSupport\Models\ReleaseSupportReportStatusLog;

class ReleaseSupportReport extends BaseModel
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

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
    ];

    protected $casts = [
        'user_id' => 'integer',
        'resolved_at' => 'datetime',
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
}
