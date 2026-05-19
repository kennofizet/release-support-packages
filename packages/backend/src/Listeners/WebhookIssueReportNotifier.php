<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Listeners;

use Kennofizet\ReleaseSupport\Contracts\AfterIssueReportSubmittedListener;
use Kennofizet\ReleaseSupport\Models\ReleaseSupportReport;
use Kennofizet\ReleaseSupport\Support\SensitiveDataSanitizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Optional listener: POST report summary to RELEASE_SUPPORT_WEBHOOK_URL (Slack-compatible JSON).
 */
class WebhookIssueReportNotifier implements AfterIssueReportSubmittedListener
{
    public function handle(ReleaseSupportReport $report): void
    {
        $url = (string) config('release-support.webhook_url', '');
        if ($url === '' || !SensitiveDataSanitizer::isAllowedWebhookUrl($url)) {
            if ($url !== '') {
                Log::warning('ReleaseSupport webhook URL rejected (HTTPS public host required)', [
                    'report_id' => $report->id,
                ]);
            }

            return;
        }

        $payload = [
            'text' => sprintf(
                'New issue report #%d: %s (v%s, status=%s)',
                $report->id,
                $report->title,
                $report->app_version ?: 'n/a',
                $report->status
            ),
            'report_id' => (int) $report->id,
            'user_id' => (int) $report->user_id,
            'title' => (string) $report->title,
            'app_version' => (string) $report->app_version,
        ];

        try {
            Http::timeout(5)->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('ReleaseSupport webhook failed', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
