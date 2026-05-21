<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Events;

use Kennofizet\ReleaseSupport\Models\ReleaseSupportReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ReleaseSupportReport $report,
        public readonly string $fromStatus,
        public readonly string $toStatus,
        public readonly ?int $actorUserId = null,
    ) {
    }
}
