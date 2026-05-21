<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Events;

use Kennofizet\ReleaseSupport\Models\ReleaseSupportVersionUpdate;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VersionReleased
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<int>  $mergedReportIds
     */
    public function __construct(
        public readonly ReleaseSupportVersionUpdate $versionUpdate,
        public readonly array $mergedReportIds,
        public readonly ?int $actorUserId = null,
    ) {
    }
}
