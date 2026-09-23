<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Unit\Bitrix;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminEntrypointLoadOrderTest extends TestCase
{
    #[DataProvider('entrypointProvider')]
    public function testModuleIsLoadedBeforeAdminGuardIsReferenced(string $relativePath): void
    {
        $contents = file_get_contents(dirname(__DIR__, 3) . '/' . $relativePath);

        self::assertIsString($contents);
        $loaderPosition = strpos($contents, "Loader::includeModule('gorshkov.catalogsentinel')");
        $guardPosition = strpos($contents, 'AdminGuard::');

        self::assertNotFalse($loaderPosition, $relativePath . ' must load the module explicitly.');
        self::assertNotFalse($guardPosition, $relativePath . ' must contain an AdminGuard reference.');
        self::assertLessThan($guardPosition, $loaderPosition, $relativePath . ' references AdminGuard before loading the module.');
    }

    /** @return iterable<string, array{string}> */
    public static function entrypointProvider(): iterable
    {
        $paths = [
            'admin/menu.php',
            'admin/dashboard.php',
            'admin/scans.php',
            'admin/scan.php',
            'admin/baselines.php',
            'admin/dry_run.php',
            'admin/export.php',
            'admin/full_report.php',
            'options.php',
        ];
        foreach ($paths as $path) {
            yield $path => [$path];
        }
    }
}
