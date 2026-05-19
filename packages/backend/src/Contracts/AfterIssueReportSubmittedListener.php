<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Contracts;

use Kennofizet\ReleaseSupport\Models\ReleaseSupportReport;

interface AfterIssueReportSubmittedListener
{
    public function handle(ReleaseSupportReport $report): void;
}
