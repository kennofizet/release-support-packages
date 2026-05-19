<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Controllers;

use Kennofizet\ReleaseSupport\Requests\AddReportCommentRequest;
use Kennofizet\ReleaseSupport\Requests\DevMetricsRequest;
use Kennofizet\ReleaseSupport\Requests\ListReportsRequest;
use Kennofizet\ReleaseSupport\Requests\SaveVersionUpdateRequest;
use Kennofizet\ReleaseSupport\Requests\SubmitReportRequest;
use Kennofizet\ReleaseSupport\Requests\UpdateReportStatusRequest;
use Kennofizet\ReleaseSupport\Services\ReleaseSupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReleaseSupportController extends Controller
{
    public function __construct(
        private readonly ReleaseSupportService $service
    ) {
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $clientVersion = (string) $request->input('app_version', '');
        return $this->apiResponseWithContext(
            $this->service->getBootstrapPayload($clientVersion !== '' ? $clientVersion : null)
        );
    }

    public function submitReport(SubmitReportRequest $request): JsonResponse
    {
        if (self::currentUserId() === null) {
            return $this->apiErrorResponse('Current user is required', 401);
        }
        try {
            $report = $this->service->createReport($request->validated());
            $includeLogs = $this->service->isDevUser(self::currentUserId());
            $detail = $this->service->getReportDetail((int) $report->id, $includeLogs);

            return $this->apiResponseWithContext(['report' => $detail ?? $report], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->apiErrorResponse($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->apiErrorResponse($e->getMessage(), 422);
        }
    }

    public function myReports(ListReportsRequest $request): JsonResponse
    {
        $userId = self::currentUserId();
        if ($userId === null) {
            return $this->apiErrorResponse('Current user is required', 401);
        }
        $data = $this->service->getMyReports(
            $userId,
            (string) $request->input('status', ''),
            $request->perPage(),
        );
        return $this->apiResponseWithContext($data);
    }

    public function reportDetail(int $reportId): JsonResponse
    {
        $userId = self::currentUserId();
        if (!$this->service->canAccessReport($reportId, $userId)) {
            return $this->apiErrorResponse('Report not found', 404);
        }

        $includeLogs = $this->service->isDevUser($userId);
        $report = $this->service->getReportDetail($reportId, $includeLogs);
        if (!$report) {
            return $this->apiErrorResponse('Report not found', 404);
        }

        return $this->apiResponseWithContext(['report' => $report]);
    }

    public function devReports(ListReportsRequest $request): JsonResponse
    {
        $this->guardDevUser();
        $data = $this->service->getAllReports(
            (string) $request->input('status', ''),
            $request->perPage(),
        );
        return $this->apiResponseWithContext($data);
    }

    public function devUpdateStatus(UpdateReportStatusRequest $request, int $reportId): JsonResponse
    {
        $this->guardDevUser();
        try {
            $this->service->updateReportStatus(
                $reportId,
                (string) $request->validated('status'),
                self::currentUserId()
            );
            $report = $this->service->getReportDetail($reportId, true);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e->getMessage(), 422);
        }
        return $this->apiResponseWithContext(['report' => $report]);
    }

    public function devAddComment(AddReportCommentRequest $request, int $reportId): JsonResponse
    {
        $this->guardDevUser();
        $userId = self::currentUserId();
        if ($userId === null) {
            return $this->apiErrorResponse('Current user is required', 401);
        }
        try {
            $comment = $this->service->addComment($reportId, $userId, (string) $request->validated('comment'));
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e->getMessage(), 422);
        }
        return $this->apiResponseWithContext(['comment' => $comment], 201);
    }

    public function devVersionUpdates(ListReportsRequest $request): JsonResponse
    {
        $this->guardDevUser();
        $data = $this->service->listVersionUpdates($request->perPage());
        return $this->apiResponseWithContext($data);
    }

    public function devCreateVersionUpdate(SaveVersionUpdateRequest $request): JsonResponse
    {
        $this->guardDevUser();
        try {
            $item = $this->service->saveVersionUpdate($request->validated());
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e->getMessage(), 422);
        }
        return $this->apiResponseWithContext(['item' => $item], 201);
    }

    public function devUpdateVersionUpdate(SaveVersionUpdateRequest $request, int $id): JsonResponse
    {
        $this->guardDevUser();
        try {
            $item = $this->service->saveVersionUpdate($request->validated(), $id);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e->getMessage(), 422);
        }
        return $this->apiResponseWithContext(['item' => $item]);
    }

    public function devMetrics(DevMetricsRequest $request): JsonResponse
    {
        $this->guardDevUser();
        $days = (int) $request->input('days', 30);

        return $this->apiResponseWithContext($this->service->getDevMetrics($days));
    }

    private function guardDevUser(): void
    {
        $userId = self::currentUserId();
        if (!$this->service->isDevUser($userId)) {
            throw new HttpException(403, 'Forbidden');
        }
    }
}
