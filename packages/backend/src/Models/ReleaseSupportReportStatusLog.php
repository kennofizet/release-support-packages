<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Models;

use Kennofizet\ReleaseSupport\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseSupportReportStatusLog extends BaseModel
{
    protected $fillable = [
        'report_id',
        'user_id',
        'from_status',
        'to_status',
    ];

    protected $casts = [
        'report_id' => 'integer',
        'user_id' => 'integer',
    ];

    public static function getTableName(): string
    {
        return config('release-support.report_status_logs_table', 'release_support_report_status_logs');
    }

    public function getTable(): string
    {
        return self::getTableName();
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ReleaseSupportReport::class, 'report_id', 'id');
    }
}
