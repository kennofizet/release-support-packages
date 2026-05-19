<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DrawingStorageService
{
    public function isDiskMode(): bool
    {
        return config('release-support.drawings_storage', 'disk') === 'disk';
    }

    /**
     * Convert base64 data URLs to files; store relative paths in DB.
     *
     * @param  array<int, string>  $drawings
     * @return array<int, string>
     */
    public function persistForReport(int $reportId, array $drawings): array
    {
        if (!$this->isDiskMode()) {
            return $this->sanitizeLegacyDrawings($drawings);
        }

        $disk = $this->diskName();
        $basePath = $this->basePath();
        $maxBytes = max(1, (int) config('release-support.max_drawing_bytes', 5 * 1024 * 1024));
        $stored = [];

        foreach ($drawings as $drawing) {
            if (!is_string($drawing) || $drawing === '') {
                continue;
            }
            // Only accept new uploads as data URLs — never trust client-supplied storage paths (IDOR / path traversal).
            if (!str_starts_with($drawing, 'data:image')) {
                continue;
            }
            $validated = $this->decodeValidatedDataUrl($drawing, $maxBytes);
            if ($validated === null) {
                continue;
            }
            [$ext, $binary] = $validated;
            $relative = $basePath . '/' . $reportId . '/' . Str::uuid() . '.' . $ext;
            Storage::disk($disk)->put($relative, $binary);
            $stored[] = $relative;
        }

        return $stored;
    }

    /**
     * @param  array<int, string>|null  $drawings  DB values (paths or legacy data URLs)
     * @return array<int, string> URLs for API / frontend <img src>
     */
    public function urlsForResponse(?array $drawings, int $reportId = 0): array
    {
        if (!is_array($drawings) || $drawings === []) {
            return [];
        }
        if (!$this->isDiskMode()) {
            return $drawings;
        }

        $out = [];
        foreach ($drawings as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }
            if (str_starts_with($path, 'data:image')) {
                $out[] = $path;
                continue;
            }
            // Never echo arbitrary external URLs stored in legacy rows.
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                continue;
            }
            $out[] = $this->urlForStoredPath($path, $reportId);
        }

        return $out;
    }

    public function storedDrawingMatchesFilename(string $stored, string $filename): bool
    {
        $filename = basename($filename);
        if ($filename === '') {
            return false;
        }

        return basename(str_replace('\\', '/', $stored)) === $filename;
    }

    public function mimeTypeForFilename(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    public function resolveStoredPath(int $reportId, string $filename): ?string
    {
        $filename = basename($filename);
        if ($filename === '' || preg_match('/[^a-zA-Z0-9._-]/', $filename)) {
            return null;
        }

        $candidate = $this->basePath() . '/' . $reportId . '/' . $filename;
        $disk = $this->diskName();
        if (!Storage::disk($disk)->exists($candidate)) {
            return null;
        }

        return $candidate;
    }

    public function readStream(string $relativePath)
    {
        return Storage::disk($this->diskName())->readStream($relativePath);
    }

    public function mimeType(string $relativePath): ?string
    {
        return Storage::disk($this->diskName())->mimeType($relativePath) ?: null;
    }

    public function usesPublicDisk(): bool
    {
        return $this->diskName() === 'public';
    }

    private function urlForStoredPath(string $relativePath, int $reportId): string
    {
        $disk = $this->diskName();

        if ($this->usesPublicDisk() && Storage::disk($disk)->exists($relativePath)) {
            return Storage::disk($disk)->url($relativePath);
        }

        if ($reportId > 0) {
            return $this->apiDrawingUrl($reportId, basename($relativePath));
        }

        return Storage::disk($disk)->url($relativePath);
    }

    /**
     * Relative to the release-support API base URL (same as frontend axios baseURL).
     * Frontend loads via authenticated GET, not raw &lt;img src&gt; to APP_URL.
     */
    private function apiDrawingUrl(int $reportId, string $filename): string
    {
        return 'drawings/' . $reportId . '/' . rawurlencode($filename);
    }

    /**
     * Legacy json storage: only validated data-URL images (no client paths).
     *
     * @param  array<int, mixed>  $drawings
     * @return array<int, string>
     */
    private function sanitizeLegacyDrawings(array $drawings): array
    {
        $maxCount = max(1, (int) config('release-support.max_drawings_per_report', 10));
        $maxBytes = max(1, (int) config('release-support.max_drawing_bytes', 5 * 1024 * 1024));
        $stored = [];

        foreach (array_slice($drawings, 0, $maxCount) as $drawing) {
            $validated = $this->decodeValidatedDataUrl(is_string($drawing) ? $drawing : '', $maxBytes);
            if ($validated === null) {
                continue;
            }
            [$ext, $binary] = $validated;
            $stored[] = 'data:image/' . ($ext === 'jpg' ? 'jpeg' : $ext) . ';base64,' . base64_encode($binary);
        }

        return $stored;
    }

    /**
     * @return array{0: string, 1: string}|null [ext, binary]
     */
    private function decodeValidatedDataUrl(string $drawing, int $maxBytes): ?array
    {
        if (!str_starts_with($drawing, 'data:image')) {
            return null;
        }
        if (!preg_match('/^data:image\/(\w+);base64,(.+)$/s', $drawing, $m)) {
            return null;
        }
        $binary = base64_decode($m[2], true);
        if ($binary === false || strlen($binary) > $maxBytes) {
            return null;
        }
        $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            return null;
        }
        if (!$this->isValidImageBinary($binary, $ext)) {
            return null;
        }

        return [$ext, $binary];
    }

    private function isValidImageBinary(string $binary, string $ext): bool
    {
        if ($binary === '') {
            return false;
        }
        $head = substr($binary, 0, 16);
        return match ($ext) {
            'png' => str_starts_with($head, "\x89PNG\r\n\x1a\n"),
            'gif' => str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a'),
            'jpg', 'jpeg' => str_starts_with($head, "\xFF\xD8\xFF"),
            'webp' => strlen($binary) >= 12
                && str_starts_with($head, 'RIFF')
                && substr($binary, 8, 4) === 'WEBP',
            default => false,
        };
    }

    private function diskName(): string
    {
        return (string) config('release-support.drawings_disk', 'local');
    }

    private function basePath(): string
    {
        return trim((string) config('release-support.drawings_path', 'release-support/drawings'), '/');
    }
}
