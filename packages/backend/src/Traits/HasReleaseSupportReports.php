<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Traits;

use Kennofizet\ReleaseSupport\Models\ReleaseSupportReport;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;

trait HasReleaseSupportReports
{
    public function releaseSupportReports(): HasMany
    {
        return $this->hasMany(ReleaseSupportReport::class, 'user_id', $this->getKeyName());
    }

    public function getReleaseSupportReports(?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->releaseSupportReports()->withCount('comments')->orderByDesc('id');
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }
        return $query->paginate($perPage);
    }
}
