<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitReportRequest extends FormRequest
{
    private const MAX_LOG_MESSAGE = 4000;
    private const MAX_LOG_TYPE = 64;
    private const MAX_LOG_AT = 64;
    private const MAX_LOG_EXTRA_KEYS = 12;
    private const MAX_LOG_EXTRA_STRING = 512;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $logs = $this->input('captured_logs');
        if (!is_array($logs)) {
            return;
        }

        $normalized = [];
        foreach ($logs as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $row = $entry;
            if (isset($row['type'])) {
                $row['type'] = $this->truncate((string) $row['type'], self::MAX_LOG_TYPE);
            }
            if (isset($row['message'])) {
                $row['message'] = $this->truncate((string) $row['message'], self::MAX_LOG_MESSAGE);
            }
            if (isset($row['at'])) {
                $row['at'] = $this->truncate((string) $row['at'], self::MAX_LOG_AT);
            }
            if (isset($row['extra']) && is_array($row['extra'])) {
                $extra = [];
                $i = 0;
                foreach ($row['extra'] as $key => $value) {
                    if ($i >= self::MAX_LOG_EXTRA_KEYS) {
                        break;
                    }
                    $extra[(string) $key] = is_scalar($value)
                        ? $this->truncate((string) $value, self::MAX_LOG_EXTRA_STRING)
                        : $value;
                    $i++;
                }
                $row['extra'] = $extra;
            }
            $normalized[] = $row;
        }

        $this->merge(['captured_logs' => $normalized]);
    }

    public function rules(): array
    {
        $maxDrawings = (int) config('release-support.max_drawings_per_report', 10);
        $maxLogs = (int) config('release-support.capture_max_logs', 200);

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'app_version' => ['nullable', 'string', 'max:120'],
            'captured_logs' => ['nullable', 'array', 'max:' . max(1, $maxLogs)],
            'captured_logs.*' => ['nullable', 'array'],
            'captured_logs.*.type' => ['nullable', 'string', 'max:64'],
            'captured_logs.*.message' => ['nullable', 'string', 'max:4000'],
            'captured_logs.*.at' => ['nullable', 'string', 'max:64'],
            'captured_logs.*.extra' => ['nullable', 'array', 'max:12'],
            'captured_context' => ['nullable', 'array', 'max:12'],
            'captured_context.href' => ['nullable', 'string', 'max:2048'],
            'captured_context.pathname' => ['nullable', 'string', 'max:512'],
            'captured_context.user_agent' => ['nullable', 'string', 'max:512'],
            'captured_context.viewport' => ['nullable', 'string', 'max:64'],
            'captured_context.captured_at' => ['nullable', 'string', 'max:64'],
            'drawings' => ['nullable', 'array', 'max:' . max(1, $maxDrawings)],
            'drawings.*' => ['nullable', 'string', 'max:8388608'],
            'meta' => ['nullable', 'array', 'max:8'],
            'meta.tag' => ['nullable', 'string', 'max:32'],
            'meta.source' => ['nullable', 'string', 'max:64'],
            'tag' => ['nullable', 'string', 'max:32'],
        ];
    }

    private function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max);
    }
}
