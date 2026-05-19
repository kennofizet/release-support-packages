<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Support;

final class SensitiveDataSanitizer
{
    private const SENSITIVE_KEY_PATTERN = '/password|passwd|token|secret|authorization|cookie|api[_-]?key|bearer|credential/i';

    /** @var list<string> */
    private const LOG_TYPES_ALLOWED = [
        'console_error',
        'window_error',
        'unhandled_rejection',
        'api_error',
    ];

    /** @var list<string> */
    private const CONTEXT_ALLOWED_KEYS = [
        'href',
        'pathname',
        'user_agent',
        'viewport',
        'captured_at',
    ];

    /**
     * @param  array<int, mixed>  $logs
     * @return array<int, array<string, mixed>>
     */
    public static function sanitizeCapturedLogs(array $logs, int $maxEntries = 200): array
    {
        $out = [];
        foreach (array_slice($logs, 0, $maxEntries) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $type = (string) ($entry['type'] ?? '');
            if ($type === '' || !in_array($type, self::LOG_TYPES_ALLOWED, true)) {
                continue;
            }
            $sanitized = [
                'type' => self::truncate($type, 64),
                'message' => self::truncate(self::redactString((string) ($entry['message'] ?? '')), 4000),
                'at' => self::truncate((string) ($entry['at'] ?? ''), 64),
            ];
            if (isset($entry['extra']) && is_array($entry['extra'])) {
                $sanitized['extra'] = self::sanitizeArray($entry['extra'], 12, 512);
            }
            $out[] = $sanitized;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function sanitizeCapturedContext(array $context): array
    {
        $out = [];
        foreach (self::CONTEXT_ALLOWED_KEYS as $key) {
            if (!array_key_exists($key, $context)) {
                continue;
            }
            $value = $context[$key];
            if (!is_scalar($value)) {
                continue;
            }
            if ($key === 'href') {
                $href = self::sanitizeHref((string) $value);
                if ($href !== '') {
                    $out[$key] = $href;
                }
                continue;
            }
            $out[$key] = self::truncate(self::redactString((string) $value), 512);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function sanitizeLooseMeta(array $meta, int $maxKeys = 8): array
    {
        return self::sanitizeArray($meta, $maxKeys, 256);
    }

    public static function sanitizeReportMeta(array $meta): array
    {
        $allowed = ['tag', 'source', 'submit_fingerprint'];
        $out = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $meta)) {
                continue;
            }
            $out[$key] = self::truncate((string) $meta[$key], $key === 'submit_fingerprint' ? 128 : 64);
        }

        return $out;
    }

    public static function isAllowedWebhookUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== 'https') {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function sanitizeArray(array $data, int $maxKeys, int $maxScalarLen): array
    {
        $out = [];
        $i = 0;
        foreach ($data as $key => $value) {
            if ($i >= $maxKeys) {
                break;
            }
            $keyStr = (string) $key;
            if (preg_match(self::SENSITIVE_KEY_PATTERN, $keyStr)) {
                $out[$keyStr] = '[redacted]';
                $i++;
                continue;
            }
            if (is_scalar($value)) {
                $out[$keyStr] = self::truncate(self::redactString((string) $value), $maxScalarLen);
            } elseif (is_array($value)) {
                $out[$keyStr] = self::sanitizeArray($value, 8, $maxScalarLen);
            }
            $i++;
        }

        return $out;
    }

    public static function sanitizeHref(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }
        $parts = parse_url($href);
        if (!is_array($parts)) {
            return '';
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        return self::truncate(self::redactString($href), 2048);
    }

    private static function redactString(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $value = preg_replace(
            '#\b(Bearer\s+)[A-Za-z0-9._\-+=/]{8,}\b#i',
            '$1[redacted]',
            $value
        ) ?? $value;
        $value = preg_replace(
            '#\b(token|password|secret|api[_-]?key)\s*[=:]\s*\S+#i',
            '$1=[redacted]',
            $value
        ) ?? $value;

        return $value;
    }

    private static function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max);
    }
}
