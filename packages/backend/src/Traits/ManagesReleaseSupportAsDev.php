<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Traits;

use Kennofizet\ReleaseSupport\Models\ReleaseSupportReport;
use Kennofizet\ReleaseSupport\Services\ReleaseSupportService;
use RuntimeException;

/**
 * Dev-only helpers for host User (or staff) models listed in release-support.dev_user_ids.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait ManagesReleaseSupportAsDev
{
    public function isReleaseSupportDev(): bool
    {
        return $this->releaseSupportService()->isDevUser($this->releaseSupportActorId());
    }

    /**
     * @throws RuntimeException When the user is not a configured dev
     */
    public function releaseSupportUpdateReportStatus(int $reportId, string $status): ReleaseSupportReport
    {
        $this->assertReleaseSupportDev();

        return $this->releaseSupportService()->updateReportStatus(
            $reportId,
            $status,
            $this->releaseSupportActorId(),
        );
    }

    /**
     * @return array<string, mixed> Formatted comment row
     * @throws RuntimeException When the user is not a configured dev
     */
    public function releaseSupportCommentOnReport(int $reportId, string $comment): array
    {
        $this->assertReleaseSupportDev();

        return $this->releaseSupportService()->addComment(
            $reportId,
            $this->releaseSupportActorId(),
            $comment,
        );
    }

    /**
     * @return array<string, mixed> Same payload as GET dev/release-preview
     */
    public function releaseSupportReleasePreview(): array
    {
        $this->assertReleaseSupportDev();

        return $this->releaseSupportService()->getReleasePreview();
    }

    /**
     * Merge all resolved reports waiting for a release (auto version, default title & notes).
     *
     * @param  array<string, mixed>  $options  Optional: title, content, is_active, is_force, meta
     * @return array<string, mixed> Version release detail
     */
    public function releaseSupportMergeAllWaitingReports(array $options = []): array
    {
        $this->assertReleaseSupportDev();

        return $this->releaseSupportService()->createVersionReleaseMergeAllWaiting(
            $this->releaseSupportActorId(),
            $options,
        );
    }

    /**
     * Merge selected resolved report IDs (empty title/content use package defaults).
     *
     * @param  list<int>  $reportIds
     * @param  array<string, mixed>  $options  Optional: title, content, is_active, is_force, meta
     * @return array<string, mixed> Version release detail
     */
    public function releaseSupportMergeReports(array $reportIds, array $options = []): array
    {
        $this->assertReleaseSupportDev();

        return $this->releaseSupportService()->createVersionRelease([
            'report_ids' => array_values(array_map(static fn ($id) => (int) $id, $reportIds)),
            'title' => $options['title'] ?? '',
            'content' => $options['content'] ?? '',
            'is_active' => $options['is_active'] ?? true,
            'is_force' => $options['is_force'] ?? false,
            'meta' => $options['meta'] ?? [],
        ], $this->releaseSupportActorId());
    }

    protected function releaseSupportService(): ReleaseSupportService
    {
        return app(ReleaseSupportService::class);
    }

    protected function releaseSupportActorId(): int
    {
        return (int) $this->getKey();
    }

    protected function assertReleaseSupportDev(): void
    {
        if (!$this->isReleaseSupportDev()) {
            throw new RuntimeException('User is not a release-support dev (check RELEASE_SUPPORT_DEV_USER_IDS).');
        }
    }
}
