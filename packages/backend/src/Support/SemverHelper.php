<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Support;

final class SemverHelper
{
    /**
     * Compare two semver-ish strings (supports optional leading "v").
     *
     * @return int -1 if $a < $b, 0 if equal, 1 if $a > $b, null if either invalid
     */
    public static function compare(?string $a, ?string $b): ?int
    {
        $pa = self::parse($a);
        $pb = self::parse($b);
        if ($pa === null || $pb === null) {
            return null;
        }
        foreach ([0, 1, 2] as $i) {
            if ($pa[$i] < $pb[$i]) {
                return -1;
            }
            if ($pa[$i] > $pb[$i]) {
                return 1;
            }
        }
        return 0;
    }

    public static function isLessThan(?string $current, ?string $target): ?bool
    {
        $cmp = self::compare($current, $target);
        if ($cmp === null) {
            return null;
        }
        return $cmp < 0;
    }

    public static function isGreaterThan(?string $current, ?string $target): ?bool
    {
        $cmp = self::compare($current, $target);
        if ($cmp === null) {
            return null;
        }
        return $cmp > 0;
    }

    /**
     * @return array{0:int,1:int,2:int}|null
     */
    public static function parse(?string $version): ?array
    {
        if ($version === null || trim($version) === '') {
            return null;
        }
        $normalized = ltrim(trim($version), 'vV');
        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)/', $normalized, $m)) {
            return null;
        }
        return [(int) $m[1], (int) $m[2], (int) $m[3]];
    }
}
