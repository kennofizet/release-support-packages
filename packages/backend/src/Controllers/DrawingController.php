<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Controllers;

use Kennofizet\ReleaseSupport\Models\ReleaseSupportReport;
use Kennofizet\ReleaseSupport\Services\DrawingStorageService;
use Kennofizet\ReleaseSupport\Services\ReleaseSupportService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DrawingController extends Controller
{
    public function __construct(
        private readonly DrawingStorageService $drawingStorage,
        private readonly ReleaseSupportService $releaseSupportService,
    ) {
    }

    public function show(int $reportId, string $filename): StreamedResponse
    {
        if (!$this->drawingStorage->isDiskMode()) {
            throw new HttpException(404, 'Drawing files are not stored on disk.');
        }

        $report = ReleaseSupportReport::query()->find($reportId);
        if ($report === null) {
            throw new HttpException(404, 'Report not found.');
        }

        if (!$this->releaseSupportService->canAccessReport($reportId, self::currentUserId())) {
            throw new HttpException(404, 'Report not found.');
        }

        $relativePath = $this->drawingStorage->resolveStoredPath($reportId, $filename);
        if ($relativePath === null) {
            throw new HttpException(404, 'Drawing not found.');
        }

        $drawings = is_array($report->drawings) ? $report->drawings : [];
        $allowed = false;
        foreach ($drawings as $stored) {
            if (is_string($stored) && $this->drawingStorage->storedDrawingMatchesFilename($stored, $filename)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            throw new HttpException(404, 'Drawing not found.');
        }

        $mime = $this->drawingStorage->mimeTypeForFilename($filename);
        if ($mime === 'application/octet-stream') {
            throw new HttpException(404, 'Drawing not found.');
        }
        $stream = $this->drawingStorage->readStream($relativePath);
        if ($stream === false || $stream === null) {
            throw new HttpException(404, 'Drawing not found.');
        }

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="' . basename($filename) . '"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

}
