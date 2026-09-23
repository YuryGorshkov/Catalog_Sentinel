<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Unit\Bitrix;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminHelpCoverageTest extends TestCase
{
    #[DataProvider('pageProvider')]
    public function testEveryProductPageHasContextualBitrixHints(string $relativePath, int $minimumHints): void
    {
        $contents = file_get_contents(dirname(__DIR__, 3) . '/' . $relativePath);

        self::assertIsString($contents);
        self::assertGreaterThanOrEqual(
            $minimumHints,
            substr_count($contents, 'ShowJSHint('),
            $relativePath . ' must keep contextual Bitrix help next to its main fields and metrics.',
        );
    }

    /** @return iterable<string, array{string, int}> */
    public static function pageProvider(): iterable
    {
        yield 'overview' => ['admin/dashboard.php', 5];
        yield 'scans' => ['admin/scans.php', 9];
        yield 'scan result' => ['admin/scan.php', 7];
        yield 'baselines' => ['admin/baselines.php', 8];
        yield 'dry run' => ['admin/dry_run.php', 5];
        yield 'full product report' => ['admin/full_report.php', 6];
        yield 'settings' => ['options.php', 10];
    }

    public function testRuleThresholdInputsStayAlignedWhenLabelsWrap(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 3) . '/options.php');

        self::assertIsString($contents);
        self::assertStringContainsString(
            '.gcs-thresholds .gcs-field{display:flex;flex-direction:column}',
            $contents,
        );
        self::assertStringContainsString(
            '.gcs-thresholds .gcs-field>input{margin-top:auto}',
            $contents,
        );
    }
}
