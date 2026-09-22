<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Unit\Tools;

use Gorshkov\CatalogSentinel\Tools\ReleaseArchive;
use PHPUnit\Framework\TestCase;

final class ReleaseArchiveTest extends TestCase
{
    public function testBuildAndValidateReleaseAllowlist(): void
    {
        $output = sys_get_temp_dir() . '/gcs-test-1.0.0-' . bin2hex(random_bytes(4)) . '.zip';
        try {
            $archive = new ReleaseArchive();
            $built = $archive->build(dirname(__DIR__, 3), $output);
            $validated = $archive->validate($output);

            self::assertFileExists($output);
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $built['sha256']);
            self::assertSame($built['files'], $validated['files']);
            self::assertSame('1.0.0', $validated['version']);
        } finally {
            @unlink($output);
        }
    }
}
