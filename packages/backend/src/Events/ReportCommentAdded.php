<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Events;

use Kennofizet\ReleaseSupport\Models\ReleaseSupportReport;
use Kennofizet\ReleaseSupport\Models\ReleaseSupportReportComment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportCommentAdded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ReleaseSupportReport $report,
        public readonly ReleaseSupportReportComment $comment,
    ) {
    }
}
