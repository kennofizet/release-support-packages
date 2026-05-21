<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Models;

use Kennofizet\ReleaseSupport\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReleaseSupportVersionUpdate extends BaseModel
{
    protected $fillable = [
        'version',
        'title',
        'content',
        'is_force',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'is_force' => 'boolean',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public static function getTableName(): string
    {
        return config('release-support.version_updates_table', 'release_support_version_updates');
    }

    public function getTable(): string
    {
        return self::getTableName();
    }

    public function mergedReports(): HasMany
    {
        return $this->hasMany(ReleaseSupportReport::class, 'version_update_id', 'id');
    }
}
