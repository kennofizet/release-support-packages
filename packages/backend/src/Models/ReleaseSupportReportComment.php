<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Models;

use Kennofizet\ReleaseSupport\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseSupportReportComment extends BaseModel
{
    protected $fillable = [
        'report_id',
        'user_id',
        'comment',
        'meta',
    ];

    protected $casts = [
        'report_id' => 'integer',
        'user_id' => 'integer',
        'meta' => 'array',
    ];

    public static function getTableName(): string
    {
        return config('release-support.report_comments_table', 'release_support_report_comments');
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
