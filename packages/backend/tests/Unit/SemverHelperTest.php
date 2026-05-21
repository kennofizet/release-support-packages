<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Tests\Unit;

use Kennofizet\ReleaseSupport\Support\SemverHelper;
use PHPUnit\Framework\TestCase;

class SemverHelperTest extends TestCase
{
    public function test_compare_patch_versions(): void
    {
        $this->assertSame(-1, SemverHelper::compare('1.0.0', '1.0.1'));
        $this->assertSame(1, SemverHelper::compare('2.0.0', '1.9.9'));
        $this->assertSame(0, SemverHelper::compare('v1.2.3', '1.2.3'));
    }

    public function test_is_less_than(): void
    {
        $this->assertTrue(SemverHelper::isLessThan('1.0.0', '1.1.0'));
        $this->assertFalse(SemverHelper::isLessThan('2.0.0', '1.9.0'));
    }

    public function test_invalid_returns_null(): void
    {
        $this->assertNull(SemverHelper::compare('bad', '1.0.0'));
    }

    public function test_next_release_version_sequence(): void
    {
        $this->assertSame('0.0.1', SemverHelper::nextReleaseVersion(null));
        $this->assertSame('0.0.2', SemverHelper::nextReleaseVersion('0.0.1'));
        $this->assertSame('0.0.99', SemverHelper::nextReleaseVersion('0.0.98'));
        $this->assertSame('0.1.0', SemverHelper::nextReleaseVersion('0.0.99'));
        $this->assertSame('0.1.1', SemverHelper::nextReleaseVersion('0.1.0'));
    }
}
