<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Jobs;

use Kennofizet\ReleaseSupport\Contracts\AfterIssueReportSubmittedListener;
use Kennofizet\ReleaseSupport\Models\ReleaseSupportReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunAfterIssueReportSubmittedListeners implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $reportId
    ) {
    }

    public function handle(): void
    {
        $report = ReleaseSupportReport::query()->find($this->reportId);
        if (!$report) {
            return;
        }

        $listeners = config('release-support.after_submitted_listeners', []);
        if (!is_array($listeners)) {
            return;
        }

        foreach ($listeners as $class) {
            if (!is_string($class) || !class_exists($class)) {
                continue;
            }
            $instance = app()->make($class);
            if ($instance instanceof AfterIssueReportSubmittedListener) {
                $instance->handle($report);
            }
        }
    }
}
